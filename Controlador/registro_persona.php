<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

$unidades = [];
$stmt = $connect->prepare("SELECT u.id, u.torre, u.numero, u.tipo, u.numero AS codigo
                            FROM unidades u WHERE u.estado = 'activa'
                            ORDER BY CAST(SUBSTRING_INDEX(u.numero, '-', 1) AS UNSIGNED), u.numero");
$stmt->execute();
$result = $stmt->get_result();
$unidades = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['guardar-btn'])) {

    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "Token de seguridad invalido. Recargue la pagina e intente de nuevo.";
        header("Location: ../Vista/registro_persona.php");
        exit();
    }

    $tipo_ciudadano = strtoupper(trim($_POST['tipo_ciudadano'] ?? ''));
    $numero_cedula = preg_replace('/\s+/', '', $_POST['numero_cedula'] ?? '');
    $numero_cedula = preg_replace('/[^0-9]/', '', $numero_cedula);
    $numero_cedula = mb_substr($numero_cedula, 0, 18);
    $cedula = ($tipo_ciudadano && $numero_cedula !== '') ? $tipo_ciudadano . '-' . $numero_cedula : '';

    $nombre_raw = trim($_POST['nombre'] ?? '');
    if (in_array($tipo_ciudadano, TIPOS_NOMBRE_CON_NUMEROS)) {
        $nombre_patron = '/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\',\.]/u';
        $etiqueta_nombre = 'razón social';
    } else {
        $nombre_patron = '/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s\',\.]/u';
        $etiqueta_nombre = 'nombre';
    }
    $nombre_solo_letras = preg_replace($nombre_patron, '', $nombre_raw);
    $nombre = mb_substr($nombre_solo_letras, 0, 100);
    $correo = trim($_POST['correo'] ?? '');
    $telefono_solo_numeros = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
    $telefono = mb_substr($telefono_solo_numeros, 0, 15);
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    $es_jg = in_array($tipo_ciudadano, TIPOS_NOMBRE_CON_NUMEROS);

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

    $es_junta = isset($_POST['es_junta']) ? 1 : 0;
    $cargo = trim($_POST['cargo_junta'] ?? '');
    $periodo_inicio = $_POST['periodo_inicio'] ?? null;
    $periodo_fin = $_POST['periodo_fin'] ?? null;

    $errores = [];
    if (!array_key_exists($tipo_ciudadano, TIPOS_CEDULA)) $errores[] = "Debe seleccionar el tipo de ciudadano";
    if ($numero_cedula === '') $errores[] = "La cedula es obligatoria";
    elseif (!preg_match('/^[0-9]{5,18}$/', $numero_cedula)) $errores[] = "El numero de cedula debe contener solo digitos";
    if (empty($nombre)) $errores[] = "El $etiqueta_nombre es obligatorio";
    if ($nombre_raw !== $nombre) $errores[] = "El $etiqueta_nombre solo puede contener " . (in_array($tipo_ciudadano, TIPOS_NOMBRE_CON_NUMEROS) ? "letras, numeros y espacios" : "letras y espacios");
    if (!empty($telefono) && !preg_match('/^[0-9]{7,15}$/', $telefono)) $errores[] = "El teléfono debe contener solo 7 a 15 digitos";
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo no es valido";
    if ($es_junta && empty($cargo)) $errores[] = "Debe seleccionar el cargo de la junta";
    if ($es_junta && !empty($cargo) && !array_key_exists($cargo, CARGOS_JUNTA)) $errores[] = "El cargo seleccionado no es valido";
    if ($periodo_inicio && $periodo_fin && $periodo_fin < $periodo_inicio) $errores[] = "El fin del periodo no puede ser anterior al inicio";

    if (!empty($fecha_nacimiento)) {
        $fn = explode('-', $fecha_nacimiento);
        if (count($fn) !== 3 || !checkdate((int)$fn[1], (int)$fn[2], (int)$fn[0])) {
            $errores[] = "La fecha de nacimiento no es valida";
        } elseif ($fecha_nacimiento > date('Y-m-d')) {
            $errores[] = "La fecha de nacimiento no puede ser en el futuro";
        }
    }

    if ($es_jg) {
        if ($rep_legal_nombre_raw !== $rep_legal_nombre) $errores[] = "El nombre del representante legal solo puede contener letras y espacios";
        if (!empty($rep_legal_numero) && (!array_key_exists($rep_legal_tipo, TIPOS_CEDULA_REP_LEGAL))) $errores[] = "Debe seleccionar el tipo de cedula del representante legal";
        if (!empty($rep_legal_numero) && !preg_match('/^[0-9]{5,18}$/', $rep_legal_numero)) $errores[] = "El numero de cedula del representante legal debe contener solo digitos";
        if (!empty($rep_legal_correo) && !filter_var($rep_legal_correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo del representante legal no es valido";
        if (!empty($rep_legal_telefono) && !preg_match('/^[0-9]{7,15}$/', $rep_legal_telefono)) $errores[] = "El telefono del representante legal debe contener solo 7 a 15 digitos";
        if (!empty($rep_legal_fecha_nac)) {
            $rfn = explode('-', $rep_legal_fecha_nac);
            if (count($rfn) !== 3 || !checkdate((int)$rfn[1], (int)$rfn[2], (int)$rfn[0])) {
                $errores[] = "La fecha de nacimiento del representante legal no es valida";
            } elseif ($rep_legal_fecha_nac > date('Y-m-d')) {
                $errores[] = "La fecha de nacimiento del representante legal no puede ser en el futuro";
            }
        }
    }

    if (!empty($errores)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = implode("\n", $errores);
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
            $_SESSION['mensaje'] = "La cedula ingresada ya existe en el sistema.";
            $stmt_verificar->close();
            header("Location: ../Vista/registro_persona.php");
            exit();
        }
        $stmt_verificar->close();

        $connect->begin_transaction();

        $insert_persona = $connect->prepare(
            "INSERT INTO personas (cedula, nombre, correo, telefono, direccion, fecha_nacimiento, rep_legal_nombre, rep_legal_cedula, rep_legal_correo, rep_legal_telefono, rep_legal_fecha_nac) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert_persona->bind_param("sssssssssss", $cedula, $nombre, $correo, $telefono, $direccion, $fecha_nacimiento, $rep_legal_nombre, $rep_legal_cedula, $rep_legal_correo, $rep_legal_telefono, $rep_legal_fecha_nac);
        if (!$insert_persona->execute()) throw new Exception("Error al registrar la persona: " . $insert_persona->error);
        $insert_persona->close();

        if (!empty($unidad_ids)) {
            $insert_tenencia = $connect->prepare(
                "INSERT INTO tenencia (persona_cedula, unidad_id, rol, fecha_inicio, estado) VALUES (?, ?, ?, ?, 'activo')"
            );
            $hoy = date('Y-m-d');
            foreach ($unidad_ids as $i => $uid) {
                $uid = (int)$uid;
                if ($uid <= 0) continue;
                $rol_unit = $roles[$i] ?? 'propietario';
                if (!array_key_exists($rol_unit, ROLES_TENENCIA)) $rol_unit = 'propietario';
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

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['tipo_mensaje'] = 'success';
        $_SESSION['mensaje'] = "Persona registrada exitosamente";
        header("Location: ../Vista/consulta_personas.php");
        exit();

    } catch (Exception $e) {
        $connect->rollback();
        error_log("[registro_persona] Error: " . $e->getMessage());
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "No se pudo registrar la persona. Intente nuevamente o contacte al administrador.";
        header("Location: ../Vista/registro_persona.php");
        exit();
    }
}

$connect->close();