<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
include("../Modelo/pagos.php");
requireLogin();
requireRol(['admin', 'junta']);

// ------------------------------------------------------------------
// ACCIÓN: CREAR CARGO GENERAL / GASTO ESPECIAL
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear-cargo'])) {
    $mes       = (int)($_POST['mes_generar'] ?? 0);
    $anio      = (int)($_POST['anio_generar'] ?? 0);
    $monto     = round((float)($_POST['monto'] ?? 0), 2);
    $usar_saldo = isset($_POST['usar_saldo']);
    $concepto_id   = (int)($_POST['concepto_id'] ?? 0);
    $concepto_nuevo = trim($_POST['concepto_nuevo'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');

    if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2200) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Seleccione un mes y año válidos para el periodo";
        header("Location: ../Vista/gestion_cobros.php");
        exit();
    }
    if ($monto <= 0) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ El monto debe ser mayor a cero";
        header("Location: ../Vista/gestion_cobros.php");
        exit();
    }
    if ($concepto_id === 0 && $concepto_nuevo === '') {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Seleccione un concepto existente o ingrese el nombre de uno nuevo";
        header("Location: ../Vista/gestion_cobros.php");
        exit();
    }

    try {
        $connect->begin_transaction();

        // Si es concepto nuevo → insertarlo
        if ($concepto_id === 0) {
            $ins = $connect->prepare(
                "INSERT INTO conceptos_cobro (nombre, monto, descripcion, activo) VALUES (?, ?, ?, 1)"
            );
            $ins->bind_param("ssd", $concepto_nuevo, $monto, $descripcion);
            if (!$ins->execute()) throw new Exception("Error al crear el concepto: " . $ins->error);
            $concepto_id = $ins->insert_id;
            $ins->close();
        }

        $hoy = date('Y-m-d');
        $vencimiento = getFechaVencimiento($mes, $anio);

        $unidades = $connect->query("SELECT id FROM unidades WHERE estado = 'activa'")->fetch_all(MYSQLI_ASSOC);
        $insertados = 0;
        $saldo_consumido = 0.0;

        $ins = $connect->prepare(
            "INSERT IGNORE INTO cuotas_emitidas
               (unidad_id, concepto_id, periodo_mes, periodo_anio, monto, monto_pagado,
                fecha_emision, fecha_vencimiento, estado)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'pendiente')"
        );
        foreach ($unidades as $un) {
            $ins->bind_param("iiiidss", $un['id'], $concepto_id, $mes, $anio, $monto, $hoy, $vencimiento);
            if ($ins->execute() && $ins->affected_rows > 0) {
                $insertados++;
            }
        }
        $ins->close();

        // Absolver saldo a favor si se solicitó
        if ($usar_saldo) {
            foreach ($unidades as $un) {
                if (obtenerSaldoAFavor($connect, $un['id']) > 0.001) {
                    $res = aplicarSaldoAFavor($connect, $un['id']);
                    $saldo_consumido += $res['abonado'];
                }
            }
        }

        $connect->commit();

        $nombre_concepto = $concepto_nuevo !== '' ? $concepto_nuevo : '';
        if ($concepto_nuevo === '') {
            $row = $connect->query("SELECT nombre FROM conceptos_cobro WHERE id = $concepto_id")->fetch_assoc();
            $nombre_concepto = $row['nombre'] ?? '';
        }

        $_SESSION['tipo_mensaje'] = $insertados > 0 ? 'success' : 'info';
        $msg_saldo = $usar_saldo && $saldo_consumido > 0
            ? " · saldo a favor absorbido: $" . number_format($saldo_consumido, 2)
            : "";
        $_SESSION['mensaje'] = $insertados > 0
            ? "✅ Cargo «$nombre_concepto» emitido a $insertados unidad(es) para $mes/$anio · monto: $".number_format($monto,2).$msg_saldo
            : "ℹ️ Ya existía ese concepto para $mes/$anio en todas las unidades activas";

    } catch (Exception $e) {
        $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
    }

    header("Location: ../Vista/gestion_cobros.php");
    exit();
}

// ------------------------------------------------------------------
// DATOS PARA LA VISTA
// ------------------------------------------------------------------
$conceptos = $connect->query(
    "SELECT id, nombre, monto, activo FROM conceptos_cobro ORDER BY activo DESC, nombre"
)->fetch_all(MYSQLI_ASSOC);

// Saldo a favor total de la comunidad
$fila_sf = $connect->query("SELECT COALESCE(SUM(saldo), 0) AS sf FROM saldos")->fetch_assoc();
$saldo_total_comunidad = (float)($fila_sf['sf'] ?? 0);

// Últimos 25 cargos emitidos
$ultimas_emisiones = $connect->query(
    "SELECT c.id, u.numero, co.nombre AS concepto, c.periodo_mes, c.periodo_anio,
            c.monto, c.monto_pagado, c.estado, c.fecha_emision
     FROM cuotas_emitidas c
     JOIN unidades u ON u.id = c.unidad_id
     JOIN conceptos_cobro co ON co.id = c.concepto_id
     WHERE co.activo = 1
     ORDER BY c.id DESC
     LIMIT 25"
)->fetch_all(MYSQLI_ASSOC);

$connect->close();
