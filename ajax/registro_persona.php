<?php
// AJAX - Endpoint del módulo de registro de persona (Vista/registro_persona.php)
// Recibe: accion + parametros. Devuelve JSON.
// Importante: este endpoint valida y sanitiza server-side para evitar
// que se modifique la vista y se salten las validaciones.

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../Modelo/conexiondb.php');
require_once(__DIR__ . '/../Modelo/config.php');
require_once(__DIR__ . '/../Modelo/auth.php');

requireRol(['admin', 'junta']);

// --- CSRF (mismo mecanismo que los formularios) ---
$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Token de seguridad invalido.']);
    exit();
}

$accion = $_POST['accion'] ?? 'verificar_cedula';

switch ($accion) {

    // Verifica en tiempo real si una cédula ya está registrada.
    // Recibe: accion + tipo_ciudadano (V|E) + numero_cedula
    case 'verificar_cedula':
        $tipo = strtoupper(trim($_POST['tipo_ciudadano'] ?? ''));
        $numero = preg_replace('/\s+/', '', $_POST['numero_cedula'] ?? '');
        $numero = preg_replace('/[^0-9]/', '', $numero);
        $numero = mb_substr($numero, 0, 18);

        if (!array_key_exists($tipo, TIPOS_CEDULA)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Tipo de cédula invalido.']);
            exit();
        }
        if ($numero === '' || !preg_match('/^[0-9]{5,18}$/', $numero)) {
            echo json_encode(['ok' => true, 'existe' => false, 'completo' => $tipo . '-' . $numero]);
            exit();
        }

        $cedula_completa = $tipo . '-' . $numero;

        $stmt = $connect->prepare("SELECT cedula FROM personas WHERE cedula = ?");
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error interno.']);
            exit();
        }
        $stmt->bind_param("s", $cedula_completa);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        $connect->close();

        echo json_encode(['ok' => true, 'existe' => $existe, 'completo' => $cedula_completa]);
        exit();

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Acción no válida.']);
        exit();
}

$connect->close();