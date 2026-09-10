<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
include("../Modelo/pagos.php");
requireRol(['admin', 'junta']);

$usuario = $_SESSION['usuario'] ?? '';

// ------------------------------------------------------------------
// ACCIÓN: APROBAR comprobante (crea pago y actualiza cuota)
// ------------------------------------------------------------------
if (isset($_POST['aprobar-comprobante']) && isset($_POST['comp_id'])) {
    $comp_id = (int)$_POST['comp_id'];
    try {
        $stmt = $connect->prepare(
            "SELECT c.id, c.cuota_id, c.monto, c.metodo_pago, c.referencia, c.fecha_pago,
                    c.tasa_bs, c.monto_bs,
                    cu.unidad_id, cu.monto AS cuota_monto, cu.monto_pagado
             FROM comprobantes_pago c
             JOIN cuotas_emitidas cu ON cu.id = c.cuota_id
             WHERE c.id = ? AND c.estado = 'pendiente'"
        );
        $stmt->bind_param("i", $comp_id);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$comp) throw new Exception("Comprobante no encontrado o ya procesado");

        $connect->begin_transaction();

        // Distribuir el pago aprobado sobre TODAS las cuotas abiertas de la
        // unidad (más antigua primero). El sobrante pasa a saldo a favor.
        $monto = (float)$comp['monto'];
        $tasa_comp = $comp['tasa_bs'] !== null ? (float)$comp['tasa_bs'] : null;
        $res = aplicarPagoUnidad(
            $connect, (int)$comp['unidad_id'], $monto,
            $comp['metodo_pago'], $comp['referencia'], $comp['fecha_pago'],
            $usuario, "Pago aprobado de comprobante #$comp_id",
            $comp_id, $tasa_comp
        );

        // Marcar comprobante aprobado
        $upd = $connect->prepare(
            "UPDATE comprobantes_pago SET estado = 'aprobado', verificado_por = ?, verificado_en = NOW() WHERE id = ?"
        );
        $upd->bind_param("si", $usuario, $comp_id);
        if (!$upd->execute()) throw new Exception("Error al actualizar el comprobante: " . $upd->error);
        $upd->close();

        $connect->commit();

        $_SESSION['tipo_mensaje'] = 'success';
        $msg_bs = $tasa_comp !== null ? " (Bs " . number_format($monto * $tasa_comp, 2, ',', '.') . ")" : "";
        $detalle = "Pagado a cuotas: $" . number_format($res['aplicado'], 2);
        if ($res['anticipo'] > 0) {
            $detalle .= " · saldo a favor: $" . number_format($res['anticipo'], 2);
        }
        $_SESSION['mensaje'] = "✅ Comprobante #$comp_id aprobado: $" . number_format($monto, 2) . $msg_bs
            . " aplicado ($detalle)";
    } catch (Exception $e) {
        if ($connect->errno) $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
    }
    header("Location: ../Vista/verificar_pagos.php");
    exit();
}

// ------------------------------------------------------------------
// ACCIÓN: RECHAZAR comprobante
// ------------------------------------------------------------------
if (isset($_POST['rechazar-comprobante']) && isset($_POST['comp_id'])) {
    $comp_id = (int)$_POST['comp_id'];
    $motivo = trim($_POST['motivo_rechazo'] ?? '');

    if (empty($motivo)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Debe indicar el motivo del rechazo";
        header("Location: ../Vista/verificar_pagos.php");
        exit();
    }

    try {
        $upd = $connect->prepare(
            "UPDATE comprobantes_pago SET estado = 'rechazado', motivo_rechazo = ?, verificado_por = ?, verificado_en = NOW()
             WHERE id = ? AND estado = 'pendiente'"
        );
        $upd->bind_param("ssi", $motivo, $usuario, $comp_id);
        if (!$upd->execute()) throw new Exception("Error al rechazar: " . $upd->error);
        $afect = $upd->affected_rows;
        $upd->close();

        if ($afect === 0) throw new Exception("Comprobante no encontrado o ya procesado");

        // Eliminar archivo físico (opcional: conservar para auditoría)
        // $comp_info = $connect->prepare("SELECT archivo FROM comprobantes_pago WHERE id = ?");
        // ... unlink

        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = "✅ Comprobante #$comp_id rechazado";
    } catch (Exception $e) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
    }
    header("Location: ../Vista/verificar_pagos.php");
    exit();
}

// ------------------------------------------------------------------
// LISTADO DE COMPROBANTES
// ------------------------------------------------------------------
$estado_f = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$where = ["1=1"];
$params = [];
$types = '';

if (!empty($estado_f)) {
    $where[] = "c.estado = ?";
    $params[] = $estado_f;
    $types .= 's';
}
if (!empty($busqueda)) {
    $where[] = "(u.torre LIKE ? OR u.numero LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $types .= 'ss';
}

$where_sql = " WHERE " . implode(' AND ', $where);

$sql = "SELECT c.*, co.nombre AS concepto, u.torre, u.numero,
               cu.periodo_mes AS periodo_mes, cu.periodo_anio AS periodo_anio,
               cu.monto AS cuota_monto, cu.monto_pagado AS cuota_pagado
        FROM comprobantes_pago c
        JOIN cuotas_emitidas cu ON cu.id = c.cuota_id
        JOIN unidades u ON cu.unidad_id = u.id
        JOIN conceptos_cobro co ON co.id = cu.concepto_id
        $where_sql
        ORDER BY c.estado = 'pendiente' DESC, c.fecha_solicitud DESC";

$stmt = $connect->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$comprobantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pendientes_count = 0;
foreach ($comprobantes as $c) {
    if ($c['estado'] === 'pendiente') $pendientes_count++;
}

$conceptos = $connect->query("SELECT id, nombre FROM conceptos_cobro WHERE activo = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$connect->close();