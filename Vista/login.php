<?php

session_start();
include("../Controlador/login.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Sistema de Gestión de Condominio</title>
    <link rel="stylesheet" href="../Estilo/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../Estilo/login.css?v=<?php echo time(); ?>">
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-brand">
            <div class="brand-logo"><i class="bi bi-buildings-fill"></i></div>
            <div class="brand-name">Residencial <strong>Verdemar</strong></div>
            <div class="brand-tagline">Sistema de Gestión de Condominio</div>
        </div>

        <div class="login-card">
            <div class="login-header">
                <div class="icon"><i class="bi bi-shield-lock-fill"></i></div>
                <h2>Bienvenido de nuevo</h2>
                <p>Ingrese sus credenciales para acceder al panel</p>
            </div>

            <?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
                <div class="error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
                <div class="error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <form action="../Controlador/login.php" method="post" class="login-form">
                <div class="field">
                    <label for="usuario">Usuario</label>
                    <div class="input-group">
                        <i class="bi bi-person"></i>
                        <input type="text" id="usuario" name="usuario" required
                               placeholder="Ingrese su usuario"
                               autocomplete="username">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="input-group">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="password" name="password" required
                               placeholder="Ingrese su contraseña"
                               autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" id="ingresar" name="ingresar" class="btn btn-primary login-btn">
                    <i class="bi bi-box-arrow-in-right"></i> Acceder al Sistema
                </button>
            </form>

            <div class="login-footer">
                <i class="bi bi-shield-check"></i> Entorno seguro y protegido
            </div>
        </div>
    </div>
</body>
</html>
