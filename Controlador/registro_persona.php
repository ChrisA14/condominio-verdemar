<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

$unidades = [];
$stmt = $connect->prepare("SELECT u.id, u.torre, u.numero, u.tipo, u.numero AS codigo
                            FROM unidades u WHERE u.estado = 'activa' ORDER BY u.numero");
$stmt->execute();
$result = $stmt->get_result();
$unidades = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_POST['guardar-btn'])) {

    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    $unidad_ids = $_POST['unidad_id'] ?? [];
    $roles = $_POST['rol_unidad'] ?? [];

    $es_junta = isset($_POST['es_junta']) ? 1 : 0;
    $cargo = trim($_POST['cargo_junta'] ?? '');
    $periodo_inicio = $_POST['periodo_inicio'] ?? null;
    $periodo_fin = $_POST['periodo_fin'] ?? null;

    $errores = [];
    if (empty($cedula)) $errores[] = "La cédula es obligatoria";
    if (empty($nombre)) $errores[] = "El nombre es obligatorio";
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo no es válido";
    if ($es_junta && empty($cargo)) $errores[] = "Debe seleccionar el cargo de la junta";
    if ($periodo_inicio && $periodo_fin && $periodo_fin < $periodo_inicio) $errores[] = "El fin del periodo no puede ser anterior al inicio";

    if (!empty($errores)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = implode('<br>', $errores);
        header("Location: ../Vista/registro_persona.php");
        exit();
    }

    try {
        $stmt_verificar = $connect->prepare("SELECT cedula FROM personas WHERE cedula = ?");
        $stmt_verificar->bind_param("s", $cedula);
        $stmt_verificar->execute();
        $stmt_verificar->store_result();
        if ($stmt_verificar->num_rows > 0) {
            $_SESSION['tipo_mensaje'] = 'error';
            $_SESSION['mensaje'] = "❌ La cédula '$cedula' ya existe en el sistema";
            $stmt_verificar->close();
            header("Location: ../Vista/registro_persona.php");
            exit();
        }
        $stmt_verificar->close();

        $connect->begin_transaction();

        $insert_persona = $connect->prepare(
            "INSERT INTO personas (cedula, nombre, correo, telefono, direccion, fecha_nacimiento) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert_persona->bind_param("ssssss", $cedula, $nombre, $correo, $telefono, $direccion, $fecha_nacimiento);
        if (!$insert_persona->execute()) throw new Exception("Error al registrar la persona: " . $insert_persona->error);
        $insert_persona->close();

        if (!empty($unidad_ids)) {
            $insert_tenencia = $connect->prepare(
                "INSERT INTO tenencia (persona_cedula, unidad_id, rol, fecha_inicio, estado) VALUES (?, ?, ?, ?, 'activo')"
            );
            $hoy = date('Y-m-d');
            foreach ($unidad_ids as $i => $uid) {
                if (empty($uid)) continue;
                $rol_unit = $roles[$i] ?? 'propietario';
                $insert_tenencia->bind_param("siss", $cedula, $uid, $rol_unit, $hoy);
                if (!$insert_tenencia->execute()) throw new Exception("Error al asignar la unidad: " . $insert_tenencia->error);
            }
            $insert_tenencia->close();
        }

        if ($es_junta && $cargo) {
            $insert_junta = $connect->prepare(
                "INSERT INTO junta_condominio (persona_cedula, cargo, fecha_designacion, periodo_inicio, periodo_fin, estado) VALUES (?, ?, ?, ?, ?, 'activo')"
            );
            $hoy = date('Y-m-d');
            $insert_junta->bind_param("sssss", $cedula, $cargo, $hoy, $periodo_inicio, $periodo_fin);
            if (!$insert_junta->execute()) throw new Exception("Error al registrar en la junta: " . $insert_junta->error);
            $insert_junta->close();
        }

        $connect->commit();

        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = "✅ Persona '$nombre' registrada exitosamente";
        header("Location: ../Vista/consulta_personas.php");
        exit();

    } catch (Exception $e) {
        $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Error al registrar: " . $e->getMessage();
        header("Location: ../Vista/registro_persona.php");
        exit();
    }
}

$connect->close();