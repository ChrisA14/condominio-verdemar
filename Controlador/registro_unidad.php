<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");
include("../Modelo/auth.php");
requireRol(['admin', 'junta']);

if (isset($_POST['guardar-btn'])) {

    $torre = trim($_POST['torre'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'apartamento');
    $estado = trim($_POST['estado'] ?? 'activa');

    $errores = [];
    if (empty($torre)) $errores[] = "La torre es obligatoria";
    if (empty($numero)) $errores[] = "El número de unidad es obligatorio";
    if (!isset(TIPOS_UNIDAD[$tipo])) $tipo = 'apartamento';

    if (!empty($errores)) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = implode('<br>', $errores);
        header("Location: ../Vista/registro_unidad.php");
        exit();
    }

    try {
        $stmt_verificar = $connect->prepare("SELECT id FROM unidades WHERE torre = ? AND numero = ?");
        $stmt_verificar->bind_param("ss", $torre, $numero);
        $stmt_verificar->execute();
        $stmt_verificar->store_result();
        if ($stmt_verificar->num_rows > 0) {
            $_SESSION['tipo_mensaje'] = 'error';
            $_SESSION['mensaje'] = "❌ La unidad '$numero' ya existe";
            $stmt_verificar->close();
            header("Location: ../Vista/registro_unidad.php");
            exit();
        }
        $stmt_verificar->close();

        $stmt = $connect->prepare(
            "INSERT INTO unidades (torre, numero, tipo, estado) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $torre, $numero, $tipo, $estado);
        if ($stmt->execute()) {
            $_SESSION['tipo_mensaje'] = 'success';
            $_SESSION['mensaje'] = "✅ Unidad '$numero' registrada exitosamente";
        } else {
            throw new Exception("Error al registrar la unidad: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['tipo_mensaje'] = 'error';
        $_SESSION['mensaje'] = "❌ " . $e->getMessage();
    }

    header("Location: ../Vista/consulta_unidades.php");
    exit();
}

$connect->close();