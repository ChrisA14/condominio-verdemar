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

$unidades = [];
$stmt = $connect->prepare("SELECT u.id, u.torre, u.numero, u.tipo, u.numero AS codigo
                            FROM unidades u WHERE u.estado = 'activa' ORDER BY u.numero");
$stmt->execute();
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_POST["guardar-btn"])) {
    
    $usuario = trim($_POST["usuario"]);
    $passw = $_POST["passw"];
    $email = trim($_POST["email"]);
    $cargo = trim($_POST["cargo"]);
    $rol = trim($_POST["rol"] ?? 'propietario');
    $persona_cedula = $_POST["persona_cedula"] ?? null;
    $unidad_ids = [];
    foreach (($_POST['unidad_id'] ?? []) as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) $unidad_ids[] = $uid;
    }
    $roles_unidad = $_POST['rol_unidad'] ?? [];

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

    $rol_vivienda = in_array($rol, ['propietario', 'inquilino']);

    if (!empty($persona_cedula)) {
        $check = $connect->prepare("SELECT cedula FROM personas WHERE cedula = ?");
        $check->bind_param("s", $persona_cedula);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            $errores[] = "La persona vinculada no existe";
        }
        $check->close();
    }

    if (!empty($unidad_ids)) {
        $ph = implode(',', array_fill(0, count($unidad_ids), '?'));
        $check = $connect->prepare("SELECT COUNT(*) AS total FROM unidades WHERE id IN ($ph) AND estado = 'activa'");
        $check->bind_param(str_repeat('i', count($unidad_ids)), ...$unidad_ids);
        $check->execute();
        $fila = $check->get_result()->fetch_assoc();
        if ((int)$fila['total'] !== count($unidad_ids)) {
            $errores[] = "Una de las unidades seleccionadas no existe o no está activa";
        }
        $check->close();
    }

    if ($rol_vivienda) {
        if (empty($persona_cedula)) {
            $errores[] = "Para crear un usuario propietario/inquilino debe vincular una persona";
        }
        if (empty($unidad_ids)) {
            $errores[] = "Debe asignar al menos una unidad al usuario propietario/inquilino";
        }
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

        $connect->begin_transaction();

        $insertar = "INSERT INTO usuarios (usuario, pass, email, cargo, rol, persona_cedula) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connect->prepare($insertar);

        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $connect->error);
        }

        $stmt->bind_param("ssssss", $usuario, $pass_encriptada, $email, $cargo, $rol, $persona_cedula);

        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        $stmt->close();

        if (!empty($unidad_ids)) {
            $insert_tenencia = $connect->prepare(
                "INSERT INTO tenencia (persona_cedula, unidad_id, rol, fecha_inicio, estado) VALUES (?, ?, ?, ?, 'activo')"
            );
            if (!$insert_tenencia) {
                throw new Exception("Error al preparar la asignación de unidades: " . $connect->error);
            }
            $hoy = date('Y-m-d');
            foreach ($unidad_ids as $i => $uid) {
                $rol_unit = $roles_unidad[$i] ?? 'propietario';
                if (!isset(ROLES_TENENCIA[$rol_unit])) $rol_unit = 'propietario';
                $insert_tenencia->bind_param("siss", $persona_cedula, $uid, $rol_unit, $hoy);
                if (!$insert_tenencia->execute()) {
                    throw new Exception("Error al asignar la unidad: " . $insert_tenencia->error);
                }
            }
            $insert_tenencia->close();
        }

        $connect->commit();

        $msg = "✅ Usuario '$usuario' registrado exitosamente (rol: " . ROLES[$rol] . ")"
             . ($rol_vivienda ? ' con ' . count($unidad_ids) . ' unidad(es) asignada(s)' : '');
        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = $msg;

    } catch (Exception $e) {
        $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "Error al guardar usuario: " . $e->getMessage();
    }

    header("Location: ../Vista/usuario.php");
    exit();
}

$connect->close();