<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireLogin();

$unidades_filtro = buildUnidadFilter($connect);

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$fecha_desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$fecha_hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$metodo_f = isset($_GET['metodo']) ? trim($_GET['metodo']) : '';

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
if (!empty($fecha_desde)) {
    $where[] = "p.fecha_pago >= ?";
    $params[] = $fecha_desde;
    $types .= 's';
}
if (!empty($fecha_hasta)) {
    $where[] = "p.fecha_pago <= ?";
    $params[] = $fecha_hasta;
    $types .= 's';
}
if (!empty($metodo_f)) {
    $where[] = "p.metodo_pago = ?";
    $params[] = $metodo_f;
    $types .= 's';
}

$where_sql = " WHERE " . implode(' AND ', $where) . $unidades_filtro[0];
$params = array_merge($params, $unidades_filtro[1]);
$types .= str_repeat('i', count($unidades_filtro[1]));

$count_sql = "SELECT COUNT(*) AS total
              FROM pagos p
              JOIN unidades u ON u.id = p.unidad_id
              LEFT JOIN cuotas_emitidas c ON p.cuota_id = c.id
              $where_sql";
$stmt = $connect->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_pagos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_paginas = ceil($total_pagos / $por_pagina);

$sql = "SELECT p.id, p.monto, p.metodo_pago, p.referencia, p.fecha_pago, p.registrado_por, p.nota,
               p.tasa_bs, p.monto_bs, p.tipo,
               u.torre, u.numero,
               c.periodo_mes, c.periodo_anio,
               COALESCE(co.nombre, 'Anticipo / Saldo a favor') AS concepto,
               cp.solicitado_por_nombre AS declarado_por
        FROM pagos p
        JOIN unidades u ON u.id = p.unidad_id
        LEFT JOIN cuotas_emitidas c ON p.cuota_id = c.id
        LEFT JOIN conceptos_cobro co ON c.concepto_id = co.id
        LEFT JOIN comprobantes_pago cp ON cp.id = p.comprobante_id
        $where_sql
        ORDER BY p.fecha_pago DESC, p.id DESC
        LIMIT ?, ?";

$params_list = $params;
$params_list[] = $inicio;
$params_list[] = $por_pagina;
$types_list = $types . 'ii';

$stmt = $connect->prepare($sql);
if (!empty($params_list)) $stmt->bind_param($types_list, ...$params_list);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_suma = 0.0;
$total_suma_bs = 0.0;
foreach ($pagos as $pg) {
    $total_suma += (float)$pg['monto'];
    if (!empty($pg['monto_bs'])) $total_suma_bs += (float)$pg['monto_bs'];
}

// ------------------------------------------------------------------
// DECLARACIONES DE PAGO (comprobantes) - solo para propietarios/inquilinos
// ------------------------------------------------------------------
$comprobantes_usuario = [];
if (!tieneAccesoTotal()) {
    $unidades_ids = getUnidadesPermitidas($connect);
    if (!empty($unidades_ids)) {
        $placeholders = implode(',', array_fill(0, count($unidades_ids), '?'));
        $stmt = $connect->prepare(
            "SELECT comp.*, co.nombre AS concepto, u.torre, u.numero
             FROM comprobantes_pago comp
             JOIN cuotas_emitidas cu ON cu.id = comp.cuota_id
             JOIN unidades u ON cu.unidad_id = u.id
             JOIN conceptos_cobro co ON co.id = cu.concepto_id
             WHERE comp.cuota_id IN (
                 SELECT c.id FROM cuotas_emitidas c WHERE c.unidad_id IN ($placeholders)
             )
             ORDER BY comp.fecha_solicitud DESC"
        );
        $stmt->bind_param(str_repeat('i', count($unidades_ids)), ...$unidades_ids);
        $stmt->execute();
        $comprobantes_usuario = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$connect->close();