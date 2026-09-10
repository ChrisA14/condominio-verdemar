<?php
// =============================================================
// LÓGICA DE CUENTAS POR COBRAR - PAGOS VARIABLES Y SALDO A FAVOR
// =============================================================

/**
 * Saldo a favor (reportado) de una unidad.
 */
function obtenerSaldoAFavor($connect, $unidad_id) {
    $unidad_id = (int)$unidad_id;
    $stmt = $connect->prepare("SELECT saldo FROM saldos WHERE unidad_id = ?");
    $stmt->bind_param("i", $unidad_id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ? (float)$fila['saldo'] : 0.0;
}

/**
 * Suma (positiva) o resta (negativa) un monto al saldo a favor de una unidad.
 * Nunca deja el saldo por debajo de cero.
 */
function ajustarSaldoAFavor($connect, $unidad_id, $delta) {
    $unidad_id = (int)$unidad_id;
    $delta = round((float)$delta, 2);
    if (abs($delta) < 0.001) return;

    $stmt = $connect->prepare(
        "INSERT INTO saldos (unidad_id, saldo) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE saldo = saldo + VALUES(saldo)"
    );
    $stmt->bind_param("id", $unidad_id, $delta);
    $stmt->execute();
    $stmt->close();

    // Asegurar que no quede negativo
    $connect->query(
        "DELETE FROM saldos WHERE unidad_id = $unidad_id AND saldo <= 0.001"
    );
}

/**
 * Cuotas abiertas de una unidad con saldo pendiente (más antigua primero).
 */
function getCuotasAbiertas($connect, $unidad_id) {
    $stmt = $connect->prepare(
        "SELECT id, monto, monto_pagado, (monto - monto_pagado) AS saldo, concepto_id
         FROM cuotas_emitidas
         WHERE unidad_id = ? AND estado IN ('pendiente', 'vencida', 'parcial')
           AND monto - monto_pagado > 0.001
         ORDER BY periodo_anio ASC, periodo_mes ASC, id ASC"
    );
    $stmt->bind_param("i", $unidad_id);
    $stmt->execute();
    $cuotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $cuotas;
}

/**
 * Consume el saldo a favor existente de una unidad aplicándolo a sus cuotas
 * abiertas (más antiguas primero). Usar tras emitir nuevos cobros o cuando se
 * requiera "absorber" el anticipo. Asume transacción abierta.
 * Retorna [ 'abonado' => float, 'restante' => float ].
 */
function aplicarSaldoAFavor($connect, $unidad_id) {
    $saldo = obtenerSaldoAFavor($connect, $unidad_id);
    if ($saldo <= 0.001) return ['abonado' => 0.0, 'restante' => 0.0];

    $aplicado = 0.0;
    foreach (getCuotasAbiertas($connect, $unidad_id) as $cuota) {
        if ($saldo - $aplicado <= 0.001) break;
        $chunk = min((float)$cuota['saldo'], $saldo - $aplicado);
        if ($chunk <= 0.001) continue;

        $nuevo_pagado = (float)$cuota['monto_pagado'] + $chunk;
        $nuevo_estado = ($nuevo_pagado >= (float)$cuota['monto'] - 0.001) ? 'pagada' : 'parcial';
        $st = $connect->prepare("UPDATE cuotas_emitidas SET monto_pagado = ?, estado = ? WHERE id = ?");
        $st->bind_param("dsi", $nuevo_pagado, $nuevo_estado, $cuota['id']);
        $st->execute();
        $st->close();
        $aplicado += $chunk;
    }

    $restante = round($saldo - $aplicado, 2);
    if ($restante <= 0.001) {
        $connect->query("DELETE FROM saldos WHERE unidad_id = " . (int)$unidad_id);
    } else {
        $st = $connect->prepare("UPDATE saldos SET saldo = ? WHERE unidad_id = ?");
        $st->bind_param("di", $restante, $unidad_id);
        $st->execute();
        $st->close();
    }
    return ['abonado' => round($aplicado, 2), 'restante' => $restante];
}

/**
 * Aplica un pago de $monto a la unidad $unidad_id.
 * 1) Se distribuye sobre las cuotas abiertas (más antiguas primero).
 * 2) El excedente queda como saldo a favor (anticipo) de la unidad.
 * 3) Registra un pago por cada cuota afectada y uno adicional tipo 'anticipo'
 *    si hay sobrante.
 *
 * DEBE llamarse dentro de una transacción iniciada por el llamador.
 *
 * Retorna:
 *  [
 *    'aplicado'   => float (total distribuido a cuotas),
 *    'anticipo'   => float (sobrante a saldo a favor),
 *    'cuota_ids'  => [id, ...],
 *    'pago_ids'   => [id, ...],
 *  ]
 */
function aplicarPagoUnidad($connect, $unidad_id, $monto, $metodo_pago, $referencia,
                           $fecha_pago, $registrado_por, $nota = '',
                           $comprobante_id = null, $tasa_bs = null) {
    $unidad_id = (int)$unidad_id;
    $monto = round((float)$monto, 2);
    $metodo_pago = isset(METODOS_PAGO[$metodo_pago]) ? $metodo_pago : 'efectivo';

    $cuotas = getCuotasAbiertas($connect, $unidad_id);
    $restante = $monto;
    $resultado = ['aplicado' => 0.0, 'anticipo' => 0.0, 'cuota_ids' => [], 'pago_ids' => []];

    foreach ($cuotas as $cuota) {
        if ($restante <= 0.001) break;
        $chunk = min((float)$cuota['saldo'], $restante);
        if ($chunk <= 0.001) continue;

        $chunk_bs = $tasa_bs !== null && $tasa_bs > 0 ? round($chunk * $tasa_bs, 2) : null;

        // Registrar pago por cuota
        $st = $connect->prepare(
            "INSERT INTO pagos (cuota_id, unidad_id, tipo, monto, metodo_pago, referencia,
                                fecha_pago, registrado_por, nota, comprobante_id, tasa_bs, monto_bs)
             VALUES (?, ?, 'cuota', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $nota_cuota = trim($nota) !== '' ? $nota : null;
        $st->bind_param("iidssssssiidd",
            $cuota['id'], $unidad_id, $chunk, $metodo_pago, $referencia,
            $fecha_pago, $registrado_por, $nota_cuota,
            $comprobante_id, $tasa_bs, $chunk_bs);
        if (!$st->execute()) {
            throw new Exception("Error al registrar el pago: " . $st->error);
        }
        $resultado['pago_ids'][] = $st->insert_id;
        $resultado['cuota_ids'][] = (int)$cuota['id'];
        $st->close();

        // Actualizar cuota
        $nuevo_pagado = (float)$cuota['monto_pagado'] + $chunk;
        $nuevo_estado = ($nuevo_pagado >= (float)$cuota['monto'] - 0.001) ? 'pagada' : 'parcial';
        $up = $connect->prepare("UPDATE cuotas_emitidas SET monto_pagado = ?, estado = ? WHERE id = ?");
        $up->bind_param("dsi", $nuevo_pagado, $nuevo_estado, $cuota['id']);
        if (!$up->execute()) throw new Exception("Error al actualizar la cuota: " . $up->error);
        $up->close();

        $resultado['aplicado'] += $chunk;
        $restante = round($restante - $chunk, 2);
    }

    if ($restante > 0.001) {
        // Sobrante -> saldo a favor (anticipo)
        $nota_anticipo = trim($nota) !== '' ? "Anticipo / saldo a favor. $nota" : "Anticipo / saldo a favor";
        $chunk_bs = $tasa_bs !== null && $tasa_bs > 0 ? round($restante * $tasa_bs, 2) : null;
        $st = $connect->prepare(
            "INSERT INTO pagos (cuota_id, unidad_id, tipo, monto, metodo_pago, referencia,
                                fecha_pago, registrado_por, nota, comprobante_id, tasa_bs, monto_bs)
             VALUES (NULL, ?, 'anticipo', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $st->bind_param("idssssssidd",
            $unidad_id, $restante, $metodo_pago, $referencia,
            $fecha_pago, $registrado_por, $nota_anticipo,
            $comprobante_id, $tasa_bs, $chunk_bs);
        if (!$st->execute()) throw new Exception("Error al registrar el anticipo: " . $st->error);
        $resultado['pago_ids'][] = $st->insert_id;
        $st->close();

        ajustarSaldoAFavor($connect, $unidad_id, $restante);
        $resultado['anticipo'] = $restante;
    }

    $resultado['aplicado'] = round($resultado['aplicado'], 2);
    return $resultado;
}