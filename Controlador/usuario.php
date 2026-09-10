<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin']);

$personas = [];
$stmt = $connect->prepare("SELECT cedula, nombre FROM personas ORDER BY nombre");
$stmt->execute();
$personas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_POST["guardar-btn"])) {
    
    $usuario = trim($_POST["usuario"]);
    $passw = $_POST["passw"];
    $email = trim($_POST["email"]);
    $cargo = trim($_POST["cargo"]);
    $rol = trim($_POST["rol"] ?? 'propietario');
    $persona_cedula = $_POST["persona_cedula"] ?? null;

    $_SESSION['mensaje'] = '';
    $_SESSION['tipo_mensaje'] = '';

    $errores = [];

    if (empty($usuario) || strlen($usuario) < 3 || strlen($usuario) > 50) {
        $errores[] = "El usuario es obligatorio y debe tener entre 3 y 50 caracteres";
    }

    if (empty($passw) || strlen($passw) < 6) {
        $errores[] = "La contraseña es obligatoria y debe tener al menos 6 caracteres";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del correo electrónico no es válido";
    }

    if (empty($cargo) || strlen($cargo) > 50) {
        $errores[] = "El cargo es obligatorio y máximo 50 caracteres";
    }

    if (!isset(ROLES[$rol])) {
        $rol = 'propietario';
    }

    if (!empty($persona_cedula)) {
        $check = $connect->prepare("SELECT cedula FROM personas WHERE cedula = ?");
        $check->bind_param("s", $persona_cedula);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) $persona_cedula = null;
        $check->close();
    }

    if (!empty($errores)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = implode('<br>', $errores);
        header("Location: ../Vista/usuario.php");
        exit();
    }

    try {
        $stmt_verificar = $connect->prepare("SELECT usuario FROM usuarios WHERE usuario = ?");
        
        if (!$stmt_verificar) {
            throw new Exception("Error al preparar consulta de verificación: " . $connect->error);
        }

        $stmt_verificar->bind_param("s", $usuario);
        $stmt_verificar->execute();
        $stmt_verificar->store_result();

        if ($stmt_verificar->num_rows > 0) {
            $_SESSION['tipo_mensaje'] = 'error';
            $_SESSION['mensaje'] = "❌ El usuario '$usuario' ya existe en el sistema";
            $stmt_verificar->close();
            header("Location: ../Vista/usuario.php");
            exit();
        }
        
        $stmt_verificar->close();

    } catch (Exception $e) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "Error al verificar usuario: " . $e->getMessage();
        header("Location: ../Vista/usuario.php");
        exit();
    }

    try {

        $pass_encriptada = password_hash($passw, PASSWORD_DEFAULT);

        $insertar = "INSERT INTO usuarios (usuario, pass, email, cargo, rol, persona_cedula) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connect->prepare($insertar);
        
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $connect->error);
        }

        $stmt->bind_param("ssssss", $usuario, $pass_encriptada, $email, $cargo, $rol, $persona_cedula);
        
        if ($stmt->execute()) {
            $_SESSION['tipo_mensaje'] = 'success';
            $_SESSION['mensaje'] = "✅ Usuario '$usuario' registrado exitosamente (rol: " . ROLES[$rol] . ")";
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "Error al guardar usuario: " . $e->getMessage();
    }

    header("Location: ../Vista/usuario.php");
    exit();
}

$connect->close();