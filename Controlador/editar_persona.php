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
    $tipo_ciudadano = substr($cedula, 0, 1);
    $es_jg = in_array($tipo_ciudadano, TIPOS_NOMBRE_CON_NUMEROS);

    $nombre_raw = trim($_POST['nombre'] ?? '');
    if ($es_jg) {
        $nombre_patron = '/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\',\.]/u';
        $etiqueta_nombre = 'razón social';
    } else {
        $nombre_patron = '/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s\',\.]/u';
        $etiqueta_nombre = 'nombre';
    }
    $nombre = mb_substr(preg_replace($nombre_patron, '', $nombre_raw), 0, 100);
    $correo = trim($_POST['correo'] ?? '');
    $telefono_solo_numeros = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
    $telefono = mb_substr($telefono_solo_numeros, 0, 15);
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    if ($es_jg) {
        $fecha_nacimiento = null;
        $rep_legal_nombre_raw = trim($_POST['rep_legal_nombre'] ?? '');
        $rep_legal_nombre_solo = preg_replace('/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s\'.]/u', '', $rep_legal_nombre_raw);
        $rep_legal_nombre = mb_substr($rep_legal_nombre_solo, 0, 100);
        $rep_legal_tipo = strtoupper(trim($_POST['rep_legal_tipo_cedula'] ?? ''));
        $rep_legal_numero = preg_replace('/\s+/', '', $_POST['rep_legal_numero_cedula'] ?? '');
        $rep_legal_numero = preg_replace('/[^0-9]/', '', $rep_legal_numero);
        $rep_legal_numero = mb_substr($rep_legal_numero, 0, 18);
        $rep_legal_cedula = ($rep_legal_tipo && $rep_legal_numero !== '') ? $rep_legal_tipo . '-' . $rep_legal_numero : null;
        $rep_legal_correo = trim($_POST['rep_legal_correo'] ?? '');
        $rep_legal_telefono_solo = preg_replace('/\D/', '', $_POST['rep_legal_telefono'] ?? '');
        $rep_legal_telefono = mb_substr($rep_legal_telefono_solo, 0, 15);
        $rep_legal_fecha_nac = $_POST['rep_legal_fecha_nac'] ?? null;
    } else {
        $rep_legal_nombre = null;
        $rep_legal_cedula = null;
        $rep_legal_correo = null;
        $rep_legal_telefono = null;
        $rep_legal_fecha_nac = null;
    }

    $unidad_ids = $_POST['unidad_id'] ?? [];
    $roles = $_POST['rol_unidad'] ?? [];
    $tenencia_ids = $_POST['tenencia_id'] ?? [];

    $es_junta = isset($_POST['es_junta']) ? 1 : 0;
    $cargo = trim($_POST['cargo_junta'] ?? '');
    $periodo_inicio = $_POST['periodo_inicio'] ?? null;
    $periodo_fin = $_POST['periodo_fin'] ?? null;

    $errores = [];
    if (empty($cedula)) $errores[] = "La cédula es obligatoria";
    if (empty($nombre)) $errores[] = "El $etiqueta_nombre es obligatorio";
    if ($nombre_raw !== $nombre) $errores[] = "El $etiqueta_nombre solo puede contener " . ($es_jg ? "letras, numeros y espacios" : "letras y espacios");
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo no es válido";
    if (!empty($telefono) && !preg_match('/^[0-9]{7,15}$/', $telefono)) $errores[] = "El teléfono debe contener solo 7 a 15 digitos";
    if ($es_junta && empty($cargo)) $errores[] = "Debe seleccionar el cargo de la junta";
    if ($periodo_inicio && $periodo_fin && $periodo_fin < $periodo_inicio) $errores[] = "El fin del periodo no puede ser anterior al inicio";

    if (!empty($fecha_nacimiento)) {
        $fn = explode('-', $fecha_nacimiento);
        if (count($fn) !== 3 || !checkdate((int)$fn[1], (int)$fn[2], (int)$fn[0])) {
            $errores[] = "La fecha de nacimiento no es válida";
        } elseif ($fecha_nacimiento > date('Y-m-d')) {
            $errores[] = "La fecha de nacimiento no puede ser en el futuro";
        }
    }

    if ($es_jg) {
        if ($rep_legal_nombre_raw !== $rep_legal_nombre) $errores[] = "El nombre del representante legal solo puede contener letras y espacios";
        if (!empty($rep_legal_numero) && !array_key_exists($rep_legal_tipo, TIPOS_CEDULA_REP_LEGAL)) $errores[] = "Debe seleccionar el tipo de cédula del representante legal";
        if (!empty($rep_legal_numero) && !preg_match('/^[0-9]{5,18}$/', $rep_legal_numero)) $errores[] = "El numero de cédula del representante legal debe contener solo digitos";
        if (!empty($rep_legal_correo) && !filter_var($rep_legal_correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo del representante legal no es válido";
        if (!empty($rep_legal_telefono) && !preg_match('/^[0-9]{7,15}$/', $rep_legal_telefono)) $errores[] = "El teléfono del representante legal debe contener solo 7 a 15 digitos";
        if (!empty($rep_legal_fecha_nac)) {
            $rfn = explode('-', $rep_legal_fecha_nac);
            if (count($rfn) !== 3 || !checkdate((int)$rfn[1], (int)$rfn[2], (int)$rfn[0])) {
                $errores[] = "La fecha de nacimiento del representante legal no es válida";
            } elseif ($rep_legal_fecha_nac > date('Y-m-d')) {
                $errores[] = "La fecha de nacimiento del representante legal no puede ser en el futuro";
            }
        }
    }

    if (!empty($errores)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = implode('<br>', $errores);
        header("Location: ../Vista/editar_persona.php?cedula=" . urlencode($cedula));
        exit();
    }

    try {
        $connect->begin_transaction();

        $upd = $connect->prepare(
            "UPDATE personas SET nombre = ?, correo = ?, telefono = ?, direccion = ?, fecha_nacimiento = ?, rep_legal_nombre = ?, rep_legal_cedula = ?, rep_legal_correo = ?, rep_legal_telefono = ?, rep_legal_fecha_nac = ? WHERE cedula = ?"
        );
        $upd->bind_param("sssssssssss", $nombre, $correo, $telefono, $direccion, $fecha_nacimiento, $rep_legal_nombre, $rep_legal_cedula, $rep_legal_correo, $rep_legal_telefono, $rep_legal_fecha_nac, $cedula);
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
             WHERE t.persona_cedula = ? ORDER BY CAST(SUBSTRING_INDEX(u.numero, '-', 1) AS UNSIGNED), u.numero"
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
                            FROM unidades u WHERE u.estado = 'activa'
                            ORDER BY CAST(SUBSTRING_INDEX(u.numero, '-', 1) AS UNSIGNED), u.numero");
$stmt->execute();
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connect->close();