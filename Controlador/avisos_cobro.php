<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
include("../Modelo/pagos.php");
requireLogin();

$es_gestor = tieneAccesoTotal();
$unidades_filtro = buildUnidadFilter($connect);
$usuario_nombre = getNombreSession();

// ------------------------------------------------------------------
// ACCIÓN: GENERACIÓN MASIVA DE CUOTAS
// ------------------------------------------------------------------
if ($es_gestor && isset($_POST['generar-btn'])) {
    $mes = (int)($_POST['mes_generar'] ?? 0);
    $anio = (int)($_POST['anio_generar'] ?? 0);

    if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2200) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Seleccione un mes y año válidos para la generación";
        header("Location: ../Vista/avisos_cobro.php");
        exit();
    }

    $hoy = date('Y-m-d');
    $vencimiento = getFechaVencimiento($mes, $anio);

    try {
        $stmt_conceptos = $connect->query("SELECT id, monto FROM conceptos_cobro WHERE activo = 1");
        $conceptos = $stmt_conceptos->fetch_all(MYSQLI_ASSOC);
        $stmt_conceptos->close();

        $stmt_unidades = $connect->query("SELECT id FROM unidades WHERE estado = 'activa'");
        $unidades = $stmt_unidades->fetch_all(MYSQLI_ASSOC);
        $stmt_unidades->close();

        $insertados = 0;
        $saldo_consumido = 0.0;
        if ($conceptos && $unidades) {
            $connect->begin_transaction();
            $insert = $connect->prepare(
                "INSERT IGNORE INTO cuotas_emitidas
                   (unidad_id, concepto_id, periodo_mes, periodo_anio, monto, monto_pagado, fecha_emision, fecha_vencimiento, estado)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'pendiente')"
            );
            foreach ($unidades as $un) {
                foreach ($conceptos as $con) {
                    $insert->bind_param("iiiidss", $un['id'], $con['id'], $mes, $anio, $con['monto'], $hoy, $vencimiento);
                    if ($insert->execute() && $insert->affected_rows > 0) {
                        $insertados++;
                    }
                }
                // Consumir el saldo a favor de cada unidad contra sus cuotas abiertas
                if (obtenerSaldoAFavor($connect, $un['id']) > 0.001) {
                    $res = aplicarSaldoAFavor($connect, $un['id']);
                    $saldo_consumido += $res['abonado'];
                }
            }
            $insert->close();
            $connect->commit();
        }

        $_SESSION['tipo_mensaje'] = $insertados > 0 ? 'success' : 'info';
        $msg_saldo = $saldo_consumido > 0
            ? " · saldo a favor absorbido: $" . number_format($saldo_consumido, 2)
            : "";
        $_SESSION['mensaje'] = $insertados > 0
            ? "✅ Se emitieron $insertados aviso(s) de cobro para $mes/$anio.$msg_saldo"
            : "ℹ️ No se generaron avisos nuevos: ya estaban emitidos para $mes/$anio o no hay conceptos/unidades activos";

    } catch (Exception $e) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Error al generar: " . $e->getMessage();
    }

    header("Location: ../Vista/avisos_cobro.php");
    exit();
}

// ------------------------------------------------------------------
// ACCIÓN: CONDONAR LA DEUDA COMPLETA DE UNA UNIDAD (gestor)
// ------------------------------------------------------------------
if ($es_gestor && isset($_POST['condonar-unidad']) && isset($_POST['unidad_id'])) {
    $unidad_id = (int)$_POST['unidad_id'];
    try {
        $connect->begin_transaction();
        $upd = $connect->prepare(
            "UPDATE cuotas_emitidas SET estado = 'condonada'
             WHERE unidad_id = ? AND estado IN ('pendiente', 'vencida', 'parcial')"
        );
        $upd->bind_param("i", $unidad_id);
        if (!$upd->execute()) throw new Exception("Error al condonar: " . $upd->error);
        $afect = $upd->affected_rows;
        $upd->close();
        $connect->commit();
        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = $afect > 0
            ? "✅ Deuda de la unidad condonada ($afect aviso(s))"
            : "ℹ️ La unidad no tiene avisos pendientes por condonar";
    } catch (Exception $e) {
        if ($connect->errno) $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
    }
    header("Location: ../Vista/avisos_cobro.php");
    exit();
}

// ------------------------------------------------------------------
// AUTO-ACTUALIZACIÓN DE ESTADOS (vencidas / pagadas automáticas)
// ------------------------------------------------------------------
if ($es_gestor || !empty($_SESSION['persona_cedula'])) {
    $hoy = date('Y-m-d');
    $connect->query(
        "UPDATE cuotas_emitidas
         SET estado = CASE
             WHEN monto_pagado >= monto THEN 'pagada'
             WHEN monto_pagado > 0 THEN 'parcial'
             WHEN fecha_vencimiento < '$hoy' THEN 'vencida'
             ELSE 'pendiente'
         END
         WHERE estado NOT IN ('condonada')"
    );
}

// ------------------------------------------------------------------
// LISTADO CONSOLIDADO POR UNIDAD (un estado de cuenta por apartamento)
// ------------------------------------------------------------------
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$mes_f = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio_f = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$estado_f = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$concepto_f = isset($_GET['concepto']) ? (int)$_GET['concepto'] : 0;

$por_pagina = 15;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

$where = ["1=1"];
$params = [];
$types = '';

if (!empty($busqueda)) {
    $where[] = "(u.torre LIKE ? OR u.numero LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $types .= 'ss';
}
if ($mes_f >= 1 && $mes_f <= 12) {
    $where[] = "EXISTS (SELECT 1 FROM cuotas_emitidas ce
                        WHERE ce.unidad_id = u.id
                          AND ce.periodo_mes = ? AND ce.periodo_anio = ?
                          AND ce.estado != 'condonada' AND ce.monto - ce.monto_pagado > 0.001)";
    $params[] = $mes_f;
    $params[] = $anio_f > 0 ? $anio_f : (int)date('Y');
    $types .= 'ii';
} else {
    if ($anio_f >= 2000) {
        $where[] = "EXISTS (SELECT 1 FROM cuotas_emitidas ce
                            WHERE ce.unidad_id = u.id
                              AND ce.periodo_anio = ?
                              AND ce.estado != 'condonada' AND ce.monto - ce.monto_pagado > 0.001)";
        $params[] = $anio_f;
        $types .= 'i';
    }
}
if (!empty($estado_f)) {
    $where[] = "EXISTS (SELECT 1 FROM cuotas_emitidas ce
                        WHERE ce.unidad_id = u.id
                          AND ce.estado = ?
                          AND ce.monto - ce.monto_pagado > 0.001)";
    $params[] = $estado_f;
    $types .= 's';
}
if ($concepto_f > 0) {
    $where[] = "EXISTS (SELECT 1 FROM cuotas_emitidas ce
                        WHERE ce.unidad_id = u.id
                          AND ce.concepto_id = ?
                          AND ce.estado != 'condonada' AND ce.monto - ce.monto_pagado > 0.001)";
    $params[] = $concepto_f;
    $types .= 'i';
}

$where_sql = " WHERE " . implode(' AND ', $where) . $unidades_filtro[0];
$params = array_merge($params, $unidades_filtro[1]);
$types .= str_repeat('i', count($unidades_filtro[1]));

// Los filtros por período pueden apuntar a unidades sin deuda abierta; se
// requiere además que la unidad tenga saldo pendiente para aparecer aqui.
$where_open = "c.estado != 'condonada' AND c.monto - c.monto_pagado > 0.001";

$count_sql = "SELECT COUNT(*) AS total
              FROM (
                  SELECT u.id
                  FROM unidades u
                  JOIN cuotas_emitidas c ON c.unidad_id = u.id
                  LEFT JOIN saldos s ON s.unidad_id = u.id
                  $where_sql
                  GROUP BY u.id, s.saldo
                  HAVING COALESCE(SUM(c.monto - c.monto_pagado), 0) - COALESCE(s.saldo, 0) > 0.001
              ) AS deudores";

$stmt = $connect->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_unidades = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_paginas = ceil($total_unidades / $por_pagina);

$sql = "SELECT u.id, u.torre, u.numero, u.tipo,
        COALESCE(SUM(c.monto), 0) AS monto,
        COALESCE(SUM(c.monto_pagado), 0) AS pagado,
        COALESCE(SUM(c.monto - c.monto_pagado), 0) AS saldo,
        COALESCE(s.saldo, 0) AS saldo_favor,
        COALESCE(SUM(c.monto - c.monto_pagado), 0) - COALESCE(s.saldo, 0) AS saldo_neto,
        COALESCE(SUM(CASE WHEN c.estado = 'vencida' THEN c.monto - c.monto_pagado ELSE 0 END), 0) AS vencido,
        MIN(c.periodo_anio * 12 + c.periodo_mes) AS mes_min,
        MAX(c.periodo_anio * 12 + c.periodo_mes) AS mes_max,
        COUNT(DISTINCT c.periodo_anio * 12 + c.periodo_mes) AS meses,
        MAX(CASE WHEN c.estado = 'vencida' THEN 1 ELSE 0 END) AS hay_vencida,
        MAX(CASE WHEN c.estado = 'parcial' THEN 1 ELSE 0 END) AS hay_parcial,
        (SELECT p.nombre
           FROM tenencia t JOIN personas p ON p.cedula = t.persona_cedula
          WHERE t.unidad_id = u.id AND t.estado = 'activo'
            AND t.rol IN ('propietario', 'residente')
          LIMIT 1) AS responsable
        FROM unidades u
        JOIN cuotas_emitidas c ON c.unidad_id = u.id
        LEFT JOIN saldos s ON s.unidad_id = u.id
        $where_sql
        GROUP BY u.id, u.torre, u.numero, u.tipo, s.saldo
        HAVING COALESCE(SUM(c.monto - c.monto_pagado), 0) - COALESCE(s.saldo, 0) > 0.001
        ORDER BY vencido DESC, saldo_neto DESC, u.torre, u.numero
        LIMIT ?, ?";

$params_list = $params;
$params_list[] = $inicio;
$params_list[] = $por_pagina;
$types_list = $types . 'ii';

$stmt = $connect->prepare($sql);
if (!empty($params_list)) {
    $stmt->bind_param($types_list, ...$params_list);
}
$stmt->execute();
$unidades_deuda = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($unidades_deuda as &$ud) {
    $ud['desde_mes'] = (intval($ud['mes_min']) - 1) % 12 + 1;
    $ud['desde_anio'] = intval(($ud['mes_min'] - 1) / 12);
    $ud['hasta_mes'] = (intval($ud['mes_max']) - 1) % 12 + 1;
    $ud['hasta_anio'] = intval(($ud['mes_max'] - 1) / 12);
}
unset($ud);

$conceptos = $connect->query("SELECT id, nombre FROM conceptos_cobro WHERE activo = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$tasa_bs = obtenerTasaBCV();

$connect->close();