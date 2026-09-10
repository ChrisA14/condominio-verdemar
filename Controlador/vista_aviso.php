<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
include("../Modelo/pagos.php");
requireLogin();

$cuota_id = (int)($_GET['cuota_id'] ?? 0);
$unidad_id = (int)($_GET['unidad_id'] ?? 0);
$aviso = null;

// Compatibilidad: si viene cuota_id, resolver su unidad_id
if ($cuota_id > 0 && $unidad_id <= 0) {
    $stmt = $connect->prepare("SELECT unidad_id FROM cuotas_emitidas WHERE id = ?");
    $stmt->bind_param("i", $cuota_id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) $unidad_id = (int)$fila['unidad_id'];
}

if ($unidad_id > 0) {
    $aviso = [];
    // Datos de la unidad
    $stmt = $connect->prepare("SELECT id, torre, numero, piso, tipo FROM unidades WHERE id = ?");
    $stmt->bind_param("i", $unidad_id);
    $stmt->execute();
    $aviso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verificar permisos
    if ($aviso && !tieneAccesoTotal()) {
        $ids = getUnidadesPermitidas($connect);
        if (!in_array((int)$aviso['id'], array_map('intval', $ids ?: []))) {
            $aviso = null;
        }
    }

    if ($aviso) {
        // Responsable (propietario/residente primero, inquilino después)
        $stmt = $connect->prepare(
            "SELECT p.nombre, p.telefono, p.correo, t.rol
             FROM tenencia t JOIN personas p ON p.cedula = t.persona_cedula
             WHERE t.unidad_id = ? AND t.estado = 'activo'
             ORDER BY FIELD(t.rol, 'propietario', 'residente', 'inquilino') ASC
             LIMIT 1"
        );
        $stmt->bind_param("i", $aviso['id']);
        $stmt->execute();
        $aviso['responsable'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Cuotas abiertas (pendiente/vencida/parcial) con saldo
        $stmt = $connect->prepare(
            "SELECT c.id, c.periodo_mes, c.periodo_anio, c.monto, c.monto_pagado,
                    (c.monto - c.monto_pagado) AS saldo, c.fecha_vencimiento, c.estado,
                    co.nombre AS concepto, co.descripcion AS concepto_desc
             FROM cuotas_emitidas c
             JOIN conceptos_cobro co ON co.id = c.concepto_id
             WHERE c.unidad_id = ?
               AND c.estado != 'condonada'
               AND c.monto - c.monto_pagado > 0.001
             ORDER BY c.periodo_anio, c.periodo_mes, co.nombre"
        );
        $stmt->bind_param("i", $aviso['id']);
        $stmt->execute();
        $aviso['cuotas_abiertas'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Totales
        $aviso['total_emitido'] = 0;
        $aviso['total_pagado'] = 0;
        $aviso['total_saldo'] = 0;
        foreach ($aviso['cuotas_abiertas'] as $cu) {
            $aviso['total_emitido'] += (float)$cu['monto'];
            $aviso['total_pagado'] += (float)$cu['monto_pagado'];
            $aviso['total_saldo'] += (float)$cu['saldo'];
        }
        $aviso['saldo_favor'] = obtenerSaldoAFavor($connect, $aviso['id']);
        $aviso['saldo_neto'] = round((float)$aviso['total_saldo'] - $aviso['saldo_favor'], 2);

        // Meses adeudados
        $aviso['meses'] = 0;
        $aviso['desde_periodo'] = '';
        $aviso['hasta_periodo'] = '';
        if (!empty($aviso['cuotas_abiertas'])) {
            $primer = $aviso['cuotas_abiertas'][0];
            $ultimo = end($aviso['cuotas_abiertas']);
            $aviso['desde_periodo'] = getNombreMes($primer['periodo_mes']) . ' ' . $primer['periodo_anio'];
            $aviso['hasta_periodo'] = getNombreMes($ultimo['periodo_mes']) . ' ' . $ultimo['periodo_anio'];
            $min = $primer['periodo_anio'] * 12 + $primer['periodo_mes'];
            $max = $ultimo['periodo_anio'] * 12 + $ultimo['periodo_mes'];
            $aviso['meses'] = $max - $min + 1;
        }

        // Pagos registrados de esta unidad (últimos 20)
        $stmt = $connect->prepare(
            "SELECT p.id, p.monto, p.metodo_pago, p.referencia, p.fecha_pago, p.registrado_por, p.nota, p.tipo,
                    COALESCE(co.nombre, 'Anticipo / Saldo a favor') AS concepto
             FROM pagos p
             LEFT JOIN cuotas_emitidas c ON p.cuota_id = c.id
             LEFT JOIN conceptos_cobro co ON c.concepto_id = co.id
             WHERE p.unidad_id = ?
             ORDER BY p.fecha_pago DESC, p.id DESC
             LIMIT 20"
        );
        $stmt->bind_param("i", $aviso['id']);
        $stmt->execute();
        $aviso['pagos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $aviso['total_pagos_registrados'] = 0;
        foreach ($aviso['pagos'] as $pg) {
            $aviso['total_pagos_registrados'] += (float)$pg['monto'];
        }
    }
}

$tasa_bs = obtenerTasaBCV();

$connect->close();