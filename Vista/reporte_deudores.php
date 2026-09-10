<?php
include("../Controlador/reporte_deudores.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte de Deudores | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../Estilo/reporte_deudores.css">
</head>
<body>
  <div class="toolbar no-print">
    <button class="btn btn-primary" onclick="abrirModal()"><i class="bi bi-printer"></i> Imprimir</button>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
  </div>

  <?php if ($es_gestor): ?>

  <div id="modal-imprimir" class="modal-overlay" style="display:none;">
    <div class="modal-box">
      <h3><i class="bi bi-printer"></i> Opciones de impresión</h3>
      <label class="modal-check">
        <input type="checkbox" id="chk-mostrar-total" checked>
        <span>Mostrar totales (fila de resumen con monto total)</span>
      </label>
      <div class="modal-actions">
        <button class="btn btn-success" onclick="imprimir(document.getElementById('chk-mostrar-total').checked)"><i class="bi bi-check2-circle"></i> Imprimir</button>
        <button class="btn btn-secondary" onclick="cerrarModal()"><i class="bi bi-x-lg"></i> Cancelar</button>
      </div>
    </div>
  </div>

  <div class="reporte-doc" id="reporte-doc">
    <header class="doc-header">
      <div class="brand">
        <i class="bi bi-buildings-fill"></i>
        <div>
          <h1>Residencial Verdemar</h1>
          <p>Reporte de Deudores</p>
        </div>
      </div>
      <div class="doc-num">
        <strong>REPORTE DE DEUDORES</strong>
        <span><?php echo date('d/m/Y'); ?></span>
      </div>
    </header>

    <div class="resumen-row" id="resumen-row">
      <div class="resumen-item">
        <span class="resumen-label">Unidades con deuda</span>
        <span class="resumen-value"><?php echo count($deudores); ?></span>
      </div>
      <?php if ($resumen): ?>
      <div class="resumen-item">
        <span class="resumen-label">Pendiente de cobro</span>
        <span class="resumen-value resumen-value--danger">$<?php echo number_format($resumen['pendiente'], 2); ?></span>
      </div>
      <div class="resumen-item">
        <span class="resumen-label">Saldo a favor (comunidad)</span>
        <span class="resumen-value resumen-value--ok">$<?php echo number_format($resumen['saldo_favor'], 2); ?></span>
      </div>
      <?php endif; ?>
      <div class="resumen-item">
        <span class="resumen-label">Deuda total</span>
        <span class="resumen-value resumen-value--danger">$<?php echo number_format($total_deuda, 2); ?></span>
      </div>
    </div>

    <?php if (!empty($deudores)): ?>
    <table class="doc-table doc-table--deudores" id="tabla-deudores">
      <thead>
        <tr>
          <th class="col-num">#</th>
          <th class="col-unidad">Unidad</th>
          <th class="col-unidad">Saldo a Favor</th>
          <th class="col-deuda">Deuda Total</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; foreach ($deudores as $d): ?>
        <tr>
          <td class="col-num"><?php echo $i++; ?></td>
          <td class="col-unidad"><strong><?php echo htmlspecialchars($d['numero']); ?></strong></td>
          <td class="col-unidad"><?php echo (float)$d['saldo_favor'] > 0 ? '$' . number_format((float)$d['saldo_favor'], 2) : '—'; ?></td>
          <td class="col-deuda">$<?php echo number_format((float)$d['deuda'] - (float)$d['saldo_favor'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot id="total-footer">
        <tr class="total-row">
          <td colspan="3"><strong>TOTAL DEUDORES: <?php echo count($deudores); ?> UNIDADES</strong></td>
          <td class="col-deuda col-monto"><strong>$<?php echo number_format($total_deuda, 2); ?></strong></td>
        </tr>
      </tfoot>
    </table>
    <?php else: ?>
    <div class="sin-deudores">
      <i class="bi bi-check-circle"></i>
      <p>No existen unidades con deuda pendiente.</p>
    </div>
    <?php endif; ?>

    <p class="nota">
      <i class="bi bi-info-circle"></i>
      Se refleja la situación de deuda al <?php echo date('d/m/Y'); ?>.
      Los montos corresponden a cuotas emitidas con saldo pendiente.
      Para mayor información contactar a la administración.
    </p>

    <footer class="doc-footer">
      <div>________________________<br><small>Administración</small></div>
      <div>________________________<br><small>Presidente Comité</small></div>
    </footer>
  </div>

  <?php else: ?>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-exclamation-triangle"></i> Mi Estado de Cuenta</h2>
    </div>

    <div class="form-container form-container--wide">
      <div class="results-info">
        Deuda total: <strong style="color:var(--rojo);">$<?php echo number_format($total_deuda, 2); ?></strong>
        <?php if ($total_vencido > 0): ?>
          · Vencido: <strong style="color:var(--rojo);">$<?php echo number_format($total_vencido, 2); ?></strong>
        <?php endif; ?>
        <?php if ($total_saldo_favor > 0): ?>
          · Saldo a favor: <strong style="color:var(--verde);">-$<?php echo number_format($total_saldo_favor, 2); ?></strong>
        <?php endif; ?>
      </div>

      <?php if (!empty($deudores)): foreach ($deudores as $d): ?>
      <div style="border:1px solid rgba(22,50,77,0.12);border-radius:10px;padding:14px 18px;margin-bottom:12px;">
        <div style="font-weight:700;color:var(--navy-800);margin-bottom:6px;">
          <?php echo htmlspecialchars($d['numero']); ?>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:0.88rem;">
          <?php if ($d['avisos_pendientes'] > 0): ?>
            <span>Avisos pendientes: <strong><?php echo $d['avisos_pendientes']; ?></strong></span>
          <?php endif; ?>
          <?php if ((float)$d['deuda_vencida'] > 0): ?>
            <span style="color:var(--rojo);">Vencido: <strong>$<?php echo number_format((float)$d['deuda_vencida'], 2); ?></strong></span>
          <?php endif; ?>
          <?php if ((float)$d['saldo_favor'] > 0): ?>
            <span style="color:var(--verde);">Saldo a favor: <strong>-$<?php echo number_format((float)$d['saldo_favor'], 2); ?></strong></span>
          <?php endif; ?>
          <span style="color:var(--rojo);font-weight:700;">Total: $<?php echo number_format((float)$d['deuda'] - (float)$d['saldo_favor'], 2); ?></span>
        </div>
      </div>
      <?php endforeach; else: ?>
      <p class="results-info">No tiene deuda pendiente.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <script>
  function abrirModal() {
    document.getElementById('modal-imprimir').style.display = 'flex';
  }
  function cerrarModal() {
    document.getElementById('modal-imprimir').style.display = 'none';
  }
  function imprimir(mostrarTotales) {
    cerrarModal();
    var footer = document.getElementById('total-footer');
    var resumen = document.getElementById('resumen-row');
    if (footer) footer.style.display = mostrarTotales ? '' : 'none';
    if (resumen) resumen.style.display = mostrarTotales ? '' : 'none';
    setTimeout(function() { window.print(); }, 100);
  }
  </script>
</body>
</html>
