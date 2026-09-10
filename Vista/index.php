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

    <!-- Acordeón - ADMIN / JUNTA -->
    <div class="accordion" id="dashboard-accordion">

      <!-- EDIFICIOS -->
      <div class="accordion-item open">
        <button class="accordion-trigger" onclick="toggleAccordion(this)">
          <div class="module-icon ic-2"><i class="bi bi-buildings-fill"></i></div>
          <div class="trigger-body">
            <h3>Edificios</h3>
            <p>Personas, unidades y usuarios del condominio.</p>
          </div>
          <i class="bi bi-chevron-down accordion-chevron"></i>
        </button>
        <div class="accordion-panel">
          <div class="accordion-panel-inner">
            <div class="accordion-links">
              <a href="consulta_personas.php" class="accordion-link">
                <span class="link-icon link-ic-blue"><i class="bi bi-people-fill"></i></span>
                <span class="link-text"><strong>Personas</strong><span>Registrar, buscar y administrar propietarios, inquilinos y junta.</span></span>
              </a>
              <a href="consulta_unidades.php" class="accordion-link">
                <span class="link-icon link-ic-green"><i class="bi bi-buildings"></i></span>
                <span class="link-text"><strong>Unidades</strong><span>Catálogo de apartamentos, estacionamientos y locales.</span></span>
              </a>
              <?php if ($esAdmin): ?>
              <a href="usuario.php" class="accordion-link">
                <span class="link-icon link-ic-orange"><i class="bi bi-shield-lock-fill"></i></span>
                <span class="link-text"><strong>Usuarios</strong><span>Crear cuentas y asignar roles de acceso al sistema.</span></span>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- FINANZAS -->
      <div class="accordion-item">
        <button class="accordion-trigger" onclick="toggleAccordion(this)">
          <div class="module-icon ic-4"><i class="bi bi-receipt-cutoff"></i></div>
          <div class="trigger-body">
            <h3>Finanzas</h3>
            <p>Cobros, pagos, verificación y reportes del condominio.</p>
          </div>
          <i class="bi bi-chevron-down accordion-chevron"></i>
        </button>
        <div class="accordion-panel">
          <div class="accordion-panel-inner">
            <div class="accordion-links">
              <a href="avisos_cobro.php" class="accordion-link">
                <span class="link-icon link-ic-gold"><i class="bi bi-receipt"></i></span>
                <span class="link-text"><strong>Avisos de Cobro</strong><span>Generar cuotas mensuales y controlar vencimientos.</span></span>
              </a>
              <a href="gestion_cobros.php" class="accordion-link">
                <span class="link-icon link-ic-orange"><i class="bi bi-cash-coin"></i></span>
                <span class="link-text"><strong>Cobros Especiales</strong><span>Multas, bonos, ajustes y gastos extraordinarios.</span></span>
              </a>
              <a href="registro_pagos.php" class="accordion-link">
                <span class="link-icon link-ic-blue"><i class="bi bi-cash-stack"></i></span>
                <span class="link-text"><strong>Registro de Pagos</strong><span>Aplicar pagos directos a cuotas por unidad.</span></span>
              </a>
              <a href="verificar_pagos.php" class="accordion-link">
                <span class="link-icon link-ic-green"><i class="bi bi-clipboard-check"></i></span>
                <span class="link-text"><strong>Verificación de Pagos</strong><span>Aprobar o rechazar comprobantes de propietarios.</span></span>
              </a>
              <a href="consulta_pagos.php" class="accordion-link">
                <span class="link-icon link-ic-navy"><i class="bi bi-clock-history"></i></span>
                <span class="link-text"><strong>Historial de Pagos</strong><span>Buscar y consultar pagos registrados.</span></span>
              </a>
              <a href="reporte_deudores.php" class="accordion-link">
                <span class="link-icon link-ic-red"><i class="bi bi-exclamation-triangle"></i></span>
                <span class="link-text"><strong>Reporte de Deudores</strong><span>Unidades con deuda y estado de la comunidad.</span></span>
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>

    <?php else: ?>

    <!-- PROPIETARIO / INQUILINO -->
    <div class="modules-grid" style="margin-bottom:16px;">
      <a href="registro_pagos.php" class="module-card card-pagos">
        <div class="module-icon ic-3"><i class="bi bi-upload"></i></div>
        <div class="module-body">
          <h3>Declarar Pago</h3>
          <p>Registre su pago y adjunte comprobante desde su celular o computadora.</p>
        </div>
        <span class="module-arrow"><i class="bi bi-arrow-right-circle"></i></span>
      </a>
    </div>

    <div class="accordion">
      <div class="accordion-item">
        <button class="accordion-trigger" onclick="toggleAccordion(this)">
          <div class="module-icon ic-4"><i class="bi bi-wallet2"></i></div>
          <div class="trigger-body">
            <h3>Mis Finanzas</h3>
            <p>Avisos de cobro, estado de cuenta e historial de pagos.</p>
          </div>
          <i class="bi bi-chevron-down accordion-chevron"></i>
        </button>
        <div class="accordion-panel">
          <div class="accordion-panel-inner">
            <div class="accordion-links">
              <a href="avisos_cobro.php" class="accordion-link">
                <span class="link-icon link-ic-gold"><i class="bi bi-receipt"></i></span>
                <span class="link-text"><strong>Mis Avisos de Cobro</strong><span>Cuotas adeudadas y fechas de vencimiento.</span></span>
              </a>
              <a href="reporte_deudores.php" class="accordion-link">
                <span class="link-icon link-ic-red"><i class="bi bi-wallet2"></i></span>
                <span class="link-text"><strong>Mi Estado de Cuenta</strong><span>Saldo pendiente, vencido y resumen de deuda.</span></span>
              </a>
              <a href="consulta_pagos.php" class="accordion-link">
                <span class="link-icon link-ic-blue"><i class="bi bi-cash-stack"></i></span>
                <span class="link-text"><strong>Mis Pagos</strong><span>Historial de pagos aplicados a sus cuotas.</span></span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>

  <script>
  function toggleAccordion(btn) {
    var item = btn.closest('.accordion-item');
    var wasOpen = item.classList.contains('open');
    var accordion = item.closest('.accordion');
    accordion.querySelectorAll('.accordion-item').forEach(function(el) {
      el.classList.remove('open');
    });
    if (!wasOpen) item.classList.add('open');
  }
  </script>
</body>
</html>