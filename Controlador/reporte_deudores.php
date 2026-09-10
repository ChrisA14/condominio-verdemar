<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireLogin();

$es_gestor = tieneAccesoTotal();
$unidades_filtro = buildUnidadFilter($connect);

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$solo_vencidos = isset($_GET['vencidos']) ? 1 : 0;

// ------------------------------------------------------------------
// RESULTADOS POR UNIDAD (deudores)
// ------------------------------------------------------------------
$where = ["1=1"];
$params = [];
$types = '';

if (!empty($busqueda)) {
    $where[] = "(u.torre LIKE ? OR u.numero LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $types .= 'ss';
}

if ($solo_vencidos) {
    $where[] = "EXISTS (SELECT 1 FROM cuotas_emitidas cv
                         WHERE cv.unidad_id = u.id AND cv.estado = 'vencida')";
}

$where_sql = " WHERE " . implode(' AND ', $where) . $unidades_filtro[0];
$params = array_merge($params, $unidades_filtro[1]);
$types .= str_repeat('i', count($unidades_filtro[1]));

$sql = "SELECT u.id, u.torre, u.numero, u.piso,
        (SELECT GROUP_CONCAT(DISTINCT CONCAT(p.nombre, '|', t.rol) SEPARATOR ';')
           FROM tenencia t JOIN personas p ON p.cedula = t.persona_cedula
           WHERE t.unidad_id = u.id AND t.estado = 'activo') AS ocupantes,
        COALESCE(SUM(CASE WHEN c.estado IN ('pendiente','vencida','parcial') THEN c.monto - c.monto_pagado ELSE 0 END), 0) AS deuda,
        COALESCE(SUM(CASE WHEN c.estado = 'vencida' THEN c.monto - c.monto_pagado ELSE 0 END), 0) AS deuda_vencida,
        COUNT(CASE WHEN c.estado IN ('pendiente','vencida','parcial') THEN 1 END) AS avisos_pendientes
        FROM unidades u
        LEFT JOIN cuotas_emitidas c ON c.unidad_id = u.id
        $where_sql
        GROUP BY u.id, u.torre, u.numero, u.piso
        HAVING deuda > 0
        ORDER BY deuda DESC";

$stmt = $connect->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$deudores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_deuda = 0.0;
$total_vencido = 0.0;
foreach ($deudores as $d) {
    $total_deuda += (float)$d['deuda'];
    $total_vencido += (float)$d['deuda_vencida'];
}

// ------------------------------------------------------------------
// RESUMEN GENERAL DE LA COMUNIDAD (solo gestores)
// ------------------------------------------------------------------
$resumen = null;
if ($es_gestor) {
    $stmt = $connect->query(
        "SELECT COALESCE(SUM(monto), 0) AS emitido,
                COALESCE(SUM(monto_pagado), 0) AS pagado
         FROM cuotas_emitidas WHERE estado != 'condonada'"
    );
    $r = $stmt->fetch_assoc();
    $resumen = [
        'emitido' => (float)$r['emitido'],
        'pagado'  => (float)$r['pagado'],
        'pendiente' => (float)$r['emitido'] - (float)$r['pagado']
    ];
    $stmt->close();
}

$connect->close();