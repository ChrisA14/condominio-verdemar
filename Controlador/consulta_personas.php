<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($busqueda)) {
    $where_conditions[] = "(p.cedula LIKE ? OR p.nombre LIKE ? OR p.telefono LIKE ? OR p.correo LIKE ?)";
    $params = array_fill(0, 4, "%$busqueda%");
    $types = 'ssss';
}

switch ($tipo) {
    case 'propietario':
        $where_conditions[] = "EXISTS (SELECT 1 FROM tenencia t WHERE t.persona_cedula = p.cedula AND t.rol = 'propietario' AND t.estado = 'activo')";
        break;
    case 'inquilino':
        $where_conditions[] = "EXISTS (SELECT 1 FROM tenencia t WHERE t.persona_cedula = p.cedula AND t.rol = 'inquilino' AND t.estado = 'activo')";
        break;
    case 'residente':
        $where_conditions[] = "EXISTS (SELECT 1 FROM tenencia t WHERE t.persona_cedula = p.cedula AND t.rol = 'residente' AND t.estado = 'activo')";
        break;
    case 'junta':
        $where_conditions[] = "EXISTS (SELECT 1 FROM junta_condominio j WHERE j.persona_cedula = p.cedula AND j.estado = 'activo')";
        break;
    case 'sin_unidad':
        $where_conditions[] = "NOT EXISTS (SELECT 1 FROM tenencia t WHERE t.persona_cedula = p.cedula AND t.estado = 'activo')";
        break;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = " WHERE " . implode(' AND ', $where_conditions);
}

$count_sql = "SELECT COUNT(*) as total FROM personas p$where_sql";
$stmt_count = $connect->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_personas = $result_count->fetch_assoc()['total'];
$stmt_count->close();

$total_paginas = ceil($total_personas / $por_pagina);

$sql = "SELECT p.cedula, p.nombre, p.correo, p.telefono, p.direccion, p.fecha_creacion,
        (SELECT GROUP_CONCAT(u.numero ORDER BY u.id SEPARATOR ', ')
           FROM tenencia t JOIN unidades u ON t.unidad_id = u.id
           WHERE t.persona_cedula = p.cedula AND t.estado = 'activo') AS unidades,
        (SELECT GROUP_CONCAT(DISTINCT t.rol ORDER BY t.rol SEPARATOR ',')
           FROM tenencia t WHERE t.persona_cedula = p.cedula AND t.estado = 'activo') AS roles_tenencia,
        (SELECT j.cargo FROM junta_condominio j WHERE j.persona_cedula = p.cedula AND j.estado = 'activo' LIMIT 1) AS cargo_junta
        FROM personas p$where_sql
        ORDER BY p.fecha_creacion DESC
        LIMIT ?, ?";

$params_paginacion = $params;
$params_paginacion[] = $inicio;
$params_paginacion[] = $por_pagina;
$types_paginacion = $types . 'ii';

$stmt = $connect->prepare($sql);
if (!empty($params_paginacion)) {
    $stmt->bind_param($types_paginacion, ...$params_paginacion);
}
$stmt->execute();
$result = $stmt->get_result();
$personas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connect->close();