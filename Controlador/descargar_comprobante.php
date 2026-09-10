<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireLogin();

$es_gestor = tieneAccesoTotal();
$comp_id = (int)($_GET['id'] ?? 0);

if ($comp_id <= 0) {
    http_response_code(400);
    exit("ID de comprobante inválido");
}

// Obtener comprobante y unidad
$stmt = $connect->prepare(
    "SELECT c.archivo, c.archivo_original, c.mime, c.cuota_id, cu.unidad_id
     FROM comprobantes_pago c
     JOIN cuotas_emitidas cu ON cu.id = c.cuota_id
     WHERE c.id = ?"
);
$stmt->bind_param("i", $comp_id);
$stmt->execute();
$comp = $stmt->get_result()->fetch_assoc();
$stmt->close();
$connect->close();

if (!$comp) {
    http_response_code(404);
    exit("Comprobante no encontrado");
}

// Verificar permisos
if (!$es_gestor) {
    // Necesitamos la cedula de la sesión para verificar unidad
    $cedula = $_SESSION['persona_cedula'] ?? null;
    if (!$cedula) {
        http_response_code(403);
        exit("Acceso denegado");
    }

    // Verificar que la unidad le pertenece
    $connect = new mysqli('localhost', 'root', '', 'Proyecto');
    $stmt = $connect->prepare(
        "SELECT 1 FROM tenencia
         WHERE persona_cedula = ? AND unidad_id = ? AND estado = 'activo'
         LIMIT 1"
    );
    $stmt->bind_param("si", $cedula, $comp['unidad_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $connect->close();

    if ($result->num_rows === 0) {
        http_response_code(403);
        exit("No tiene permisos para ver este comprobante");
    }
}

$upload_dir = __DIR__ . '/../uploads/comprobantes/';
$ruta = $upload_dir . $comp['archivo'];

if (!file_exists($ruta) || !is_file($ruta)) {
    http_response_code(404);
    exit("Archivo no encontrado en el servidor");
}

// Prevenir traversal
$path_real = realpath($ruta);
if ($path_real === false || strpos($path_real, realpath($upload_dir)) !== 0) {
    http_response_code(403);
    exit("Ruta de archivo no válida");
}

// Servir el archivo
$nombre = $comp['archivo_original'] ?: $comp['archivo'];
$mime = $comp['mime'] ?: mime_content_type($ruta);

header("Content-Type: " . $mime);
header("Content-Disposition: inline; filename=\"" . $nombre . "\"");
header("Content-Length: " . filesize($ruta));
header("Cache-Control: no-cache");

readfile($ruta);