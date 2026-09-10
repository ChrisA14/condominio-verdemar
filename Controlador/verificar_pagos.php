<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
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
                    cu.monto AS cuota_monto, cu.monto_pagado
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

        // Crear pago
        $insert = $connect->prepare(
            "INSERT INTO pagos (cuota_id, monto, metodo_pago, referencia, fecha_pago, registrado_por, nota, comprobante_id, tasa_bs, monto_bs)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $nota_pago = "Pago aprobado de comprobante #$comp_id";
        $insert->bind_param("idsssssidd", $comp['cuota_id'], $comp['monto'], $comp['metodo_pago'], $comp['referencia'], $comp['fecha_pago'], $usuario, $nota_pago, $comp_id, $comp['tasa_bs'], $comp['monto_bs']);
        if (!$insert->execute()) throw new Exception("Error al crear el pago: " . $insert->error);
        $pago_id = $insert->insert_id;
        $insert->close();

        // Actualizar cuota
        $nuevo_pagado = (float)$comp['monto_pagado'] + (float)$comp['monto'];
        $nuevo_pagado = min($nuevo_pagado, (float)$comp['cuota_monto']);
        $nuevo_estado = ($nuevo_pagado >= (float)$comp['cuota_monto'] - 0.001) ? 'pagada' : 'parcial';
        $upd = $connect->prepare("UPDATE cuotas_emitidas SET monto_pagado = ?, estado = ? WHERE id = ?");
        $upd->bind_param("dsi", $nuevo_pagado, $nuevo_estado, $comp['cuota_id']);
        if (!$upd->execute()) throw new Exception("Error al actualizar la cuota: " . $upd->error);
        $upd->close();

        // Marcar comprobante aprobado
        $upd = $connect->prepare(
            "UPDATE comprobantes_pago SET estado = 'aprobado', verificado_por = ?, verificado_en = NOW() WHERE id = ?"
        );
        $upd->bind_param("si", $usuario, $comp_id);
        if (!$upd->execute()) throw new Exception("Error al actualizar el comprobante: " . $upd->error);
        $upd->close();

        $connect->commit();

        $_SESSION['tipo_mensaje'] = 'success';
        $msg_bs = !empty($comp['monto_bs']) ? " (Bs " . number_format((float)$comp['monto_bs'], 2, ',', '.') . ")" : "";
        $_SESSION['mensaje'] = "✅ Comprobante #$comp_id aprobado: pago de $" . number_format((float)$comp['monto'], 2) . $msg_bs . " registrado (pago #$pago_id)";
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