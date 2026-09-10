<?php
// AUTH.PHP - Control de acceso y permisos por rol

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Construye una ruta absoluta válida dentro de la carpeta Vista
function vista_url($archivo) {
    $dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return ($dir === 'Vista') ? $archivo : '../Vista/' . $archivo;
}

function requireLogin() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . vista_url("login.php"));
        exit();
    }
}

function requireRol($rolesPermitidos) {
    requireLogin();
    $rol = $_SESSION['rol'] ?? '';
    if (!in_array($rol, (array)$rolesPermitidos)) {
        header("Location: " . vista_url("index.php?error=" . urlencode("No tiene permisos para acceder a este módulo")));
        exit();
    }
}

function tieneAccesoTotal() {
    $rol = $_SESSION['rol'] ?? '';
    return in_array($rol, ['admin', 'junta']);
}

/*
 * Devuelve el array de IDs de unidades que puede consultar el usuario actual.
 * - admin / junta: null (todas las unidades)
 * - propietario / inquilino: solo sus unidades vinculadas por tenencia
 */
function getUnidadesPermitidas($connect) {
    if (tieneAccesoTotal()) {
        return null;
    }
    $cedula = $_SESSION['persona_cedula'] ?? null;
    if (!$cedula) {
        return [];
    }
    $stmt = $connect->prepare(
        "SELECT unidad_id FROM tenencia WHERE persona_cedula = ? AND estado = 'activo'"
    );
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['unidad_id'];
    }
    $stmt->close();
    return $ids;
}

// Construye (y devuelve) la cláusula WHERE + params para filtrar por unidades permitidas
function buildUnidadFilter($connect, $alias = 'u.id') {
    $ids = getUnidadesPermitidas($connect);
    if ($ids === null) {
        return ['', []];
    }
    if (empty($ids)) {
        return [' AND 0 ', []]; // sin unidades asignadas: no ve nada
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return [" AND $alias IN ($placeholders) ", $ids];
}

// Devuelve la lista de roles visibles en el menú/dashboard
function getRolSession() {
    return $_SESSION['rol'] ?? '';
}

function getNombreSession() {
    return $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? '';
}
?>