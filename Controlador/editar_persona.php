<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

$cedula = trim($_GET['cedula'] ?? '');
$persona = null;
$tenencias = [];
$junta = null;

if (isset($_POST['actualizar-btn'])) {
    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    $unidad_ids = $_POST['unidad_id'] ?? [];
    $roles = $_POST['rol_unidad'] ?? [];
    $tenencia_ids = $_POST['tenencia_id'] ?? [];

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
        header("Location: ../Vista/editar_persona.php?cedula=" . urlencode($cedula));
        exit();
    }

    try {
        $connect->begin_transaction();

        $upd = $connect->prepare(
            "UPDATE personas SET nombre = ?, correo = ?, telefono = ?, direccion = ?, fecha_nacimiento = ? WHERE cedula = ?"
        );
        $upd->bind_param("ssssss", $nombre, $correo, $telefono, $direccion, $fecha_nacimiento, $cedula);
        if (!$upd->execute()) throw new Exception("Error al actualizar la persona: " . $upd->error);
        $upd->close();

        $del = $connect->prepare("DELETE FROM tenencia WHERE persona_cedula = ?");
        $del->bind_param("s", $cedula);
        $del->execute();
        $del->close();

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

        $desactivar = $connect->prepare("UPDATE junta_condominio SET estado = 'inactivo' WHERE persona_cedula = ?");
        $desactivar->bind_param("s", $cedula);
        $desactivar->execute();
        $desactivar->close();

        if ($es_junta && $cargo) {
            $insert_junta = $connect->prepare(
                "INSERT INTO junta_condominio (persona_cedula, cargo, fecha_designacion, periodo_inicio, periodo_fin, estado) VALUES (?, ?, ?, ?, ?, 'activo')"
            );
            $hoy = date('Y-m-d');
            $insert_junta->bind_param("sssss", $cedula, $cargo, $hoy, $periodo_inicio, $periodo_fin);
            if (!$insert_junta->execute()) throw new Exception("Error al actualizar la junta: " . $insert_junta->error);
            $insert_junta->close();
        }

        $connect->commit();

        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = "✅ Persona '$nombre' actualizada exitosamente";
        header("Location: ../Vista/ver_persona.php?cedula=" . urlencode($cedula));
        exit();

    } catch (Exception $e) {
        $connect->rollback();
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ Error al actualizar: " . $e->getMessage();
        header("Location: ../Vista/editar_persona.php?cedula=" . urlencode($cedula));
        exit();
    }
}

if (!empty($cedula)) {
    $stmt = $connect->prepare("SELECT * FROM personas WHERE cedula = ?");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $persona = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($persona) {
        $stmt = $connect->prepare(
            "SELECT t.id, t.rol, t.fecha_inicio, u.id AS unidad_id, u.torre, u.numero
             FROM tenencia t JOIN unidades u ON t.unidad_id = u.id
             WHERE t.persona_cedula = ? ORDER BY u.torre, u.numero"
        );
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $tenencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $connect->prepare(
            "SELECT * FROM junta_condominio WHERE persona_cedula = ? AND estado = 'activo' ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $junta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$unidades = [];
$stmt = $connect->prepare("SELECT u.id, u.torre, u.numero, u.tipo, u.numero AS codigo
                            FROM unidades u WHERE u.estado = 'activa' ORDER BY u.numero");
$stmt->execute();
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connect->close();