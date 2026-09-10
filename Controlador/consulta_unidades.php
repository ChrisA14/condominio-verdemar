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
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($busqueda)) {
    $where_conditions[] = "(u.torre LIKE ? OR u.numero LIKE ? OR u.piso LIKE ?)";
    $params = array_fill(0, 3, "%$busqueda%");
    $types = 'sss';
}

if ($filtro_estado === 'activa' || $filtro_estado === 'inactiva') {
    $where_conditions[] = "u.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = " WHERE " . implode(' AND ', $where_conditions);
}

$count_sql = "SELECT COUNT(*) as total FROM unidades u$where_sql";
$stmt_count = $connect->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_unidades = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();
$total_paginas = ceil($total_unidades / $por_pagina);

$sql = "SELECT u.id, u.torre, u.numero, u.piso, u.tipo, u.estado,
        (SELECT GROUP_CONCAT(CONCAT(p.nombre, '|', t.rol) SEPARATOR ';')
           FROM tenencia t JOIN personas p ON p.cedula = t.persona_cedula
           WHERE t.unidad_id = u.id AND t.estado = 'activo') AS ocupantes,
        (SELECT COALESCE(SUM(cu.monto - cu.monto_pagado), 0) FROM cuotas_emitidas cu
           WHERE cu.unidad_id = u.id AND cu.estado IN ('pendiente','vencida','parcial')) AS saldo
        FROM unidades u$where_sql
        ORDER BY u.torre, CAST(u.numero AS UNSIGNED)
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
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connect->close();