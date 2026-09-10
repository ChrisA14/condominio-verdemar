<?php
include("../Modelo/conexiondb.php");
include("../Modelo/config.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ingresar"])) {
    
    $usuario = trim($_POST["usuario"] ?? '');
    $pass = $_POST["password"] ?? '';

    if (empty($usuario) || empty($pass)) {
        header("Location: ../Vista/login.php?error=" . urlencode("❌ Por favor, complete todos los campos"));
        exit();
    }

    $stmt = null;
    
    try {
        $consulta = "SELECT u.pass, u.rol, u.persona_cedula, p.nombre 
                     FROM usuarios u 
                     LEFT JOIN personas p ON u.persona_cedula = p.cedula 
                     WHERE u.usuario = ?";
        $stmt = $connect->prepare($consulta);
        
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta.");
        }
        
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($hash, $rol, $persona_cedula, $nombre);
            $stmt->fetch();

            if (password_verify($pass, $hash)) {
                session_start();
                session_regenerate_id(true); // Previene la fijación de sesión
                
                $_SESSION["usuario"] = $usuario;
                $_SESSION["rol"] = $rol;
                $_SESSION["persona_cedula"] = $persona_cedula;
                $_SESSION["nombre"] = $nombre ?: $usuario;
                $_SESSION["login_time"] = time();

                $stmt->close();
                $connect->close();

                header("Location: ../Vista/index.php");
                exit();
            } else {
                header("Location: ../Vista/login.php?error=" . urlencode("❌ Usuario o contraseña incorrectos"));
                exit();
            }
        } else {
            header("Location: ../Vista/login.php?error=" . urlencode("❌ Usuario o contraseña incorrectos"));
            exit();
        }
        
    } catch (Exception $e) {
        header("Location: ../Vista/login.php?error=" . urlencode("❌ Ocurrió un error en el sistema"));
        exit();
    } finally {
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
        if (isset($connect) && $connect) {
            $connect->close();
        }
    }
}
?>