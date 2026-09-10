<?php
include("../Modelo/auth.php");
requireLogin();
$rol = getRolSession();
$nombre = getNombreSession();
$esAdmin = ($rol === 'admin');
$accesoTotal = tieneAccesoTotal();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Principal | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../Estilo/dashboard.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="dashboard-container">

    <?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
      <div class="mensaje error" style="margin-bottom:18px;">
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <!-- Hero / encabezado -->
    <div class="dashboard-header">
      <div class="header-glow"></div>
      <div class="hero-top">
        <div class="hero-brand">
          <div class="hero-logo"><i class="bi bi-buildings-fill"></i></div>
          <div class="hero-text">
            <h1>Sistema de Gestión de Condominio</h1>
            <p>Bienvenido/a <strong><?php echo htmlspecialchars(strtoupper($nombre)); ?></strong>. Residencial Verdemar.</p>
          </div>
        </div>
        <a href="logout.php" class="btn btn-outline btn-light logout-btn">
          <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
        </a>
      </div>
    </div>

    <!-- Barra de usuario -->
    <div class="user-bar">
      <span class="user-chip"><i class="bi bi-person-circle"></i> Conectado como <strong><?php echo htmlspecialchars(strtoupper($_SESSION['usuario'])); ?></strong></span>
      <span class="session-badge"><i class="bi bi-shield-check"></i> Rol: <?php echo ROLES[$rol] ?? $rol; ?></span>
    </div>

    <?php if ($accesoTotal): ?>

    <!-- Grid de módulos - ADMIN / JUNTA -->
    <div class="modules-grid">
      <a href="consulta_personas.php" class="module-card card-consulta">
        <div class="module-icon ic-1"><i class="bi bi-people-fill"></i></div>
        <div class="module-body">
          <h3>Personas</h3>
          <p>Registrar, buscar y administrar propietarios, inquilinos y miembros de la junta.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="consulta_unidades.php" class="module-card card-unidades">
        <div class="module-icon ic-2"><i class="bi bi-buildings-fill"></i></div>
        <div class="module-body">
          <h3>Unidades</h3>
          <p>Catálogo de apartamentos, estacionamientos y locales del condominio.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="avisos_cobro.php" class="module-card card-avsicos">
        <div class="module-icon ic-4"><i class="bi bi-receipt"></i></div>
        <div class="module-body">
          <h3>Avisos de Cobro</h3>
          <p>Generar cuotas mensuales, emitir avisos por unidad y controlar vencimientos.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="registro_pagos.php" class="module-card card-pagos">
        <div class="module-icon ic-3"><i class="bi bi-cash-coin"></i></div>
        <div class="module-body">
          <h3>Registro de Pagos</h3>
          <p>Aplicar pagos directos a las cuotas emitidas por cada unidad del condominio.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="verificar_pagos.php" class="module-card card-pagos">
        <div class="module-icon ic-4"><i class="bi bi-clipboard-check"></i></div>
        <div class="module-body">
          <h3>Verificación de Pagos</h3>
          <p>Aprobar o rechazar comprobantes declarados por propietarios e inquilinos.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="consulta_pagos.php" class="module-card card-consulta">
        <div class="module-icon ic-1"><i class="bi bi-cash-stack"></i></div>
        <div class="module-body">
          <h3>Historial de Pagos</h3>
          <p>Buscar y consultar el historial completo de pagos registrados.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="reporte_deudores.php" class="module-card card-deudores">
        <div class="module-icon ic-2"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="module-body">
          <h3>Reporte de Deudores</h3>
          <p>Unidades con deuda pendiente, saldos y estado general de la comunidad.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <?php if ($esAdmin): ?>
      <a href="usuario.php" class="module-card card-usuarios">
        <div class="module-icon ic-3"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="module-body">
          <h3>Gestión de Usuarios</h3>
          <p>Crear cuentas y asignar roles de acceso al sistema.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>
      <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- Grid de módulos - PROPIETARIO / INQUILINO -->
    <div class="modules-grid">
      <a href="avisos_cobro.php" class="module-card card-avsicos">
        <div class="module-icon ic-4"><i class="bi bi-receipt"></i></div>
        <div class="module-body">
          <h3>Mis Avisos de Cobro</h3>
          <p>Consulte su estado de cuenta: cuotas adeudadas y fechas de vencimiento.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="registro_pagos.php" class="module-card card-pagos">
        <div class="module-icon ic-3"><i class="bi bi-upload"></i></div>
        <div class="module-body">
          <h3>Declarar Pago</h3>
          <p>Registre su pago y adjunte comprobante desde su celular o computadora.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="reporte_deudores.php" class="module-card card-deudores">
        <div class="module-icon ic-2"><i class="bi bi-wallet2"></i></div>
        <div class="module-body">
          <h3>Mi Estado de Cuenta</h3>
          <p>Saldo pendiente y resumen de deuda de sus unidades.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>

      <a href="consulta_pagos.php" class="module-card card-pagos">
        <div class="module-icon ic-1"><i class="bi bi-cash-stack"></i></div>
        <div class="module-body">
          <h3>Mis Pagos</h3>
          <p>Historial de pagos aplicados a sus cuotas.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>
    </div>

    <?php endif; ?>
  </div>
</body>
</html>