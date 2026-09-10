<?php
include("../Controlador/gestion_cobros.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Cobros | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-cash-coin"></i> Gestión de Cobros</h2>
      <div class="header-actions">
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <!-- Información -->
    <div class="form-container" style="margin-bottom:18px;">
      <div class="results-info">
        Saldo a favor total comunidad:
        <strong style="color:var(--verde);">$<?php echo number_format($saldo_total_comunidad, 2); ?></strong>
      </div>
      <p class="field-note">
        Emita cargos generales (gastos especiales, ajustes, multas, etc.) aplicados a todas las unidades activas.
        Si marca <i class="bi bi-star-fill"></i> <strong>Usar saldo a favor</strong>, el sistema aplicará automáticamente
        el saldo acumulado de cada unidad antes de generar la deuda.
      </p>
    </div>

    <!-- Formulario de emisión -->
    <div class="form-container" style="margin-bottom:18px;">
      <h3 style="margin-bottom:12px;color:var(--navy-700);"><i class="bi bi-plus-circle"></i> Emitir nuevo cargo</h3>
      <form action="gestion_cobros.php" method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">

        <div style="flex:1 1 200px;">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Concepto existente</label>
          <select name="concepto_id" id="concepto-select" onchange="toggleConceptoNuevo()" style="width:100%;">
            <option value="0">— Crear nuevo concepto —</option>
            <?php foreach ($conceptos as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php if ($c['activo']): ?><?php endif; ?>
                data-monto="<?php echo $c['monto']; ?>">
                <?php echo htmlspecialchars($c['nombre']); ?> (<?php echo $c['activo'] ? 'Act.' : 'Inact.'; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1 1 200px;" id="nuevo-concepto-wrap">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Nombre del nuevo concepto</label>
          <input type="text" name="concepto_nuevo" placeholder="Ej. Multa ruidos, Bono Navideño..."
                 maxlength="100" style="width:100%;">
        </div>

        <div style="flex:0 1 120px;">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Monto ($)</label>
          <input type="number" name="monto" id="monto-input" min="0.01" step="0.01" required
                 placeholder="0.00" style="width:100%;">
        </div>

        <div style="flex:0 1 130px;">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Mes</label>
          <select name="mes_generar" style="width:100%;" required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>><?php echo getNombreMes($m); ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div style="flex:0 1 90px;">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Año</label>
          <input type="number" name="anio_generar" min="2020" max="2200"
                 value="<?php echo date('Y'); ?>" required style="width:100%;">
        </div>

        <div style="flex:1 1 220px;">
          <label style="display:block;font-weight:600;margin-bottom:3px;">Descripción (opcional)</label>
          <input type="text" name="descripcion" placeholder="Detalle del cargo..."
                 maxlength="255" style="width:100%;">
        </div>

        <div style="padding-bottom:4px;">
          <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap;">
            <input type="checkbox" name="usar_saldo" checked style="width:16px;height:16px;">
            <i class="bi bi-star-fill" style="color:var(--verde);"></i>
            Usar saldo a favor
          </label>
        </div>

        <button type="submit" name="crear-cargo" class="btn btn-success"
                style="white-space:nowrap;">
          <i class="bi bi-lightning"></i> Emitir Cargo
        </button>
      </form>
    </div>

    <!-- Últimas emisiones -->
    <div class="form-container form-container--wide">
      <h3 style="margin-bottom:12px;color:var(--navy-700);">
        <i class="bi bi-clock-history"></i> Últimos 25 cargos emitidos
      </h3>

      <?php if (empty($ultimas_emisiones)): ?>
        <p class="results-info">No hay emisiones registradas aún.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Unidad</th><th>Concepto</th><th>Periodo</th>
              <th>Monto ($)</th><th>Pagado</th><th>Estado</th><th>Fecha emisión</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimas_emisiones as $eu): ?>
            <tr>
              <td data-label="#"><strong>#<?php echo $eu['id']; ?></strong></td>
              <td data-label="Unidad"><?php echo htmlspecialchars($eu['numero']); ?></td>
              <td data-label="Concepto"><?php echo htmlspecialchars($eu['concepto']); ?></td>
              <td data-label="Periodo"><?php echo getNombreMes($eu['periodo_mes']) . ' ' . $eu['periodo_anio']; ?></td>
              <td data-label="Monto ($)" style="font-weight:700;">$<?php echo number_format((float)$eu['monto'], 2); ?></td>
              <td data-label="Pagado" style="color:var(--verde);">$<?php echo number_format((float)$eu['monto_pagado'], 2); ?></td>
              <td data-label="Estado">
                <?php
                  $clase = match($eu['estado'] ?? '') {
                    'vencida' => 'badge-danger',
                    'parcial' => 'badge-info',
                    'pagada'  => 'badge-success',
                    'condonada' => 'badge-secondary',
                    default   => 'badge-warning',
                  };
                ?>
                <span class="badge <?php echo $clase; ?>"><?php echo ESTADOS_CUOTA[$eu['estado']] ?? $eu['estado']; ?></span>
              </td>
              <td data-label="Emisión"><?php echo date('d/m/Y', strtotime($eu['fecha_emision'])); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  function toggleConceptoNuevo() {
    var sel = document.getElementById('concepto-select');
    var wrap = document.getElementById('nuevo-concepto-wrap');
    var montoInput = document.getElementById('monto-input');
    var isNew = sel.value === '0';
    wrap.style.display = isNew ? '' : 'none';
    if (!isNew) {
      var opt = sel.options[sel.selectedIndex];
      var monto = opt.getAttribute('data-monto');
      if (monto && parseFloat(monto) > 0) montoInput.value = monto;
    }
  }
  document.addEventListener('DOMContentLoaded', toggleConceptoNuevo);
  </script>
</body>
</html>
