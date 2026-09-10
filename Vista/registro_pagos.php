<?php
include("../Controlador/registro_pagos.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $es_gestor ? 'Registro de Pagos' : 'Declarar Pago'; ?> | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-cash-coin"></i> <?php echo $es_gestor ? 'Registro de Pagos' : 'Declarar Pago'; ?></h2>
      <div class="header-actions">
        <a href="consulta_pagos.php" class="btn btn-light-header"><i class="bi bi-clock-history"></i> <?php echo $es_gestor ? 'Historial' : 'Mis Pagos'; ?></a>
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo $_SESSION['tipo_mensaje'] ?? ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <?php if (empty($cuotas)): ?>
      <div class="form-container">
        <p class="results-info"><?php echo $es_gestor
            ? 'No hay avisos de cobro pendientes de pago.'
            : 'No tiene avisos de cobro pendientes en sus unidades.'; ?></p>
      </div>
    <?php else: ?>
    <div class="form-container">
      <?php if (!$es_gestor): ?>
        <div class="nota-declarar">
          <i class="bi bi-info-circle"></i>
          Seleccione el aviso a pagar, indique el monto y adjunte su comprobante (foto desde la cámara del celular o archivo PDF/JPG/PNG, máx 5 MB).
          La declaración quedará pendiente de verificación por la administración.
        </div>
      <?php endif; ?>

      <form action="registro_pagos.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <fieldset class="form-section">
          <legend><i class="bi bi-receipt"></i> Aviso a pagar</legend>
          <div class="form-group">
            <label for="cuota_id"><?php echo $es_gestor ? 'Aviso de cobro (con saldo pendiente)' : 'Aviso de cobro de su unidad (con saldo pendiente)'; ?>: <span class="requerido">*</span></label>
            <select id="cuota_id" name="cuota_id" required onchange="actualizarMonto()">
              <option value="">Seleccione el aviso</option>
              <?php foreach ($cuotas as $c): ?>
                <option value="<?php echo $c['id']; ?>"
                  <?php echo ($cuota_preseleccionada == $c['id']) ? 'selected' : ''; ?>
                  data-saldo="<?php echo (float)$c['saldo']; ?>">
                  <?php echo htmlspecialchars($c['numero']) . ' · ' . htmlspecialchars($c['concepto'])
                    . ' · ' . getNombreMes($c['periodo_mes']) . ' ' . $c['periodo_anio']
                    . ' (saldo $' . number_format((float)$c['saldo'], 2) . ')'; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </fieldset>

        <fieldset class="form-section">
          <legend><i class="bi bi-payment"></i> Detalle del pago</legend>
          <?php if ($tasa_bs !== null): ?>
            <div class="tasa-bcv-info">
              <i class="bi bi-bank"></i> Tasa BCV del día: <strong>1 USD = <?php echo number_format($tasa_bs, 2, ',', '.'); ?> Bs</strong>
              <input type="hidden" id="tasa_bcv" value="<?php echo $tasa_bs; ?>">
            </div>
          <?php else: ?>
            <div class="tasa-bcv-info tasa-bcv-aviso">
              <i class="bi bi-exclamation-triangle"></i> No se pudo obtener la tasa BCV. El monto en Bs se calculará cuando la tasa esté disponible.
              <input type="hidden" id="tasa_bcv" value="">
            </div>
          <?php endif; ?>
          <div class="form-row">
            <div class="form-group">
              <label for="monto">Monto ($): <span class="requerido">*</span></label>
              <input type="number" id="monto" name="monto" step="0.01" min="0.01" required placeholder="0.00"
                     oninput="calcularBs()">
            </div>
            <div class="form-group">
              <label for="monto_bs_read">Equivalente (Bs):</label>
              <input type="text" id="monto_bs_read" readonly placeholder="—" class="input-readonly-bs">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="fecha_pago">Fecha de pago:</label>
              <input type="date" id="fecha_pago" name="fecha_pago" max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label for="metodo_pago">Método de pago:</label>
              <select id="metodo_pago" name="metodo_pago">
                <?php foreach (METODOS_PAGO as $k => $v): ?>
                  <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="referencia">Referencia / N°:</label>
              <input type="text" id="referencia" name="referencia" maxlength="100"
                     placeholder="N° de transferencia, recibo, etc.">
            </div>
            <div class="form-group">
              <label for="nota">Nota (opcional):</label>
              <textarea id="nota" name="nota" rows="2" maxlength="255" placeholder="Observaciones del pago"></textarea>
            </div>
          </div>
        </fieldset>

        <?php if (!$es_gestor): ?>
        <fieldset class="form-section">
          <legend><i class="bi bi-upload"></i> Comprobante de pago (obligatorio)</legend>
          <p class="field-note">Adjunte una foto del comprobante o capture desde la cámara. También puede subir un archivo PDF.</p>
          <div class="form-row">
            <div class="form-group">
              <label for="comprobante_foto"><i class="bi bi-camera"></i> Tomar foto (cámara):</label>
              <input type="file" id="comprobante_foto" name="comprobante_foto" accept="image/*" capture="environment"
                     class="file-input">
            </div>
            <div class="form-group">
              <label for="comprobante_archivo"><i class="bi bi-file-earmark-arrow-up"></i> Subir archivo (PDF, JPG, PNG):</label>
              <input type="file" id="comprobante_archivo" name="comprobante_archivo" accept="image/*,.pdf"
                     class="file-input">
            </div>
          </div>
          <p class="field-note">Formatos permitidos: JPG, JPEG, PNG, PDF. Tamaño máximo: 5 MB. Use uno de los dos campos.</p>
        </fieldset>
        <?php endif; ?>

        <div class="form-actions">
          <button type="submit" name="pagar-btn" class="btn btn-success">
            <i class="bi bi-check2-circle"></i>
            <?php echo $es_gestor ? 'Registrar Pago' : 'Enviar Declaración'; ?>
          </button>
          <button type="reset" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</button>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <script>
  function calcularBs() {
      const montoInput = document.getElementById('monto');
      const tasaInput = document.getElementById('tasa_bcv');
      const bsField = document.getElementById('monto_bs_read');
      if (!montoInput || !tasaInput || !bsField) return;
      const monto = parseFloat(montoInput.value) || 0;
      const tasa = parseFloat(tasaInput.value) || 0;
      if (monto > 0 && tasa > 0) {
          const bs = (monto * tasa).toFixed(2);
          bsField.value = 'Bs ' + Number(bs).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      } else {
          bsField.value = '';
      }
  }
  function actualizarMonto() {
      const sel = document.getElementById('cuota_id');
      if (!sel) return;
      document.getElementById('monto').readOnly = sel.value !== '';
      const opt = sel.selectedOptions[0];
      if (opt && opt.dataset.saldo) {
          document.getElementById('monto').value = opt.dataset.saldo;
      }
      calcularBs();
  }
  document.addEventListener('DOMContentLoaded', () => { actualizarMonto(); });
  </script>
</body>
</html>