<?php
include("../Controlador/vista_aviso.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Estado de Cuenta <?php echo $aviso ? ('— ' . htmlspecialchars($aviso['numero'])) : ''; ?> | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../Estilo/vista_aviso.css">
</head>
<body>
  <div class="toolbar no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <a href="<?php echo tieneAccesoTotal() ? 'avisos_cobro.php' : 'index.php'; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <?php if (empty($aviso) || empty($aviso['cuotas_abiertas'])): ?>
    <div class="aviso-doc">
      <p style="text-align:center;">
        <?php echo $aviso ? 'Este apartamento no tiene deuda pendiente.' : 'Aviso no encontrado o sin permisos para verlo.'; ?>
      </p>
    </div>
  <?php else: ?>
  <div class="aviso-doc">
    <header class="doc-header">
      <div class="brand">
        <i class="bi bi-buildings-fill"></i>
        <div>
          <h1>Residencial Verdemar</h1>
          <p>Aviso de Cobro · Sistema de Gestión de Condominio</p>
        </div>
      </div>
      <div class="doc-num">
        <strong>ESTADO DE CUENTA</strong>
        <span>Unidad N° <?php echo htmlspecialchars($aviso['numero']); ?></span>
      </div>
    </header>

    <div class="meta-grid">
      <div><label>Unidad:</label>
        <span><?php echo htmlspecialchars($aviso['numero']); ?>
              (<?php echo TIPOS_UNIDAD[$aviso['tipo']] ?? $aviso['tipo']; ?>)</span></div>
      <div><label>Responsable:</label>
        <span><?php echo htmlspecialchars($aviso['responsable']['nombre'] ?? '—'); ?></span></div>
      <div><label>Contacto:</label>
        <span><?php echo htmlspecialchars($aviso['responsable']['telefono'] ?? '—'); ?> · <?php echo htmlspecialchars($aviso['responsable']['correo'] ?? '—'); ?></span></div>
      <div><label>Período adeudado:</label>
        <span><?php echo $aviso['desde_periodo'] . ' → ' . $aviso['hasta_periodo']
                     . ' (<strong>' . $aviso['meses'] . ' mes' . ($aviso['meses'] != 1 ? 'es' : '') . '</strong>)'; ?></span></div>
      <div><label>Fecha de emisión:</label>
        <span><?php echo date('d/m/Y', strtotime('now')); ?></span></div>
    </div>

    <h3 class="doc-section">Detalle de Cuotas Pendientes</h3>
    <table class="doc-table doc-table--detail">
      <thead>
        <tr><th>Periodo</th><th>Concepto</th><th>Vence</th><th>Monto ($)</th><th>Monto (Bs)</th><th>Pagado</th><th>Saldo ($)</th><th>Saldo (Bs)</th></tr>
      </thead>
      <tbody>
        <?php foreach ($aviso['cuotas_abiertas'] as $cu): ?>
        <tr>
          <td><?php echo getNombreMes($cu['periodo_mes']) . ' ' . $cu['periodo_anio']; ?></td>
          <td>
            <?php echo htmlspecialchars($cu['concepto']); ?>
            <?php if (!empty($cu['concepto_desc'])): ?>
              <small class="concepto-desc"><?php echo htmlspecialchars($cu['concepto_desc']); ?></small>
            <?php endif; ?>
          </td>
          <td><?php echo date('d/m/Y', strtotime($cu['fecha_vencimiento'])); ?></td>
          <td>$<?php echo number_format((float)$cu['monto'], 2); ?></td>
          <td style="color:#1a7a42;"><?php
            $monto_cu_bs = convertirABolivares((float)$cu['monto'], $tasa_bs);
            echo $monto_cu_bs !== null ? 'Bs ' . number_format($monto_cu_bs, 2, ',', '.') : '—';
          ?></td>
          <td style="color:var(--verde);">-$<?php echo number_format((float)$cu['monto_pagado'], 2); ?></td>
          <td style="color:var(--rojo);font-weight:700;">$<?php echo number_format((float)$cu['saldo'], 2); ?></td>
          <td style="color:#1a7a42;font-weight:700;"><?php
            $saldo_cu_bs = convertirABolivares((float)$cu['saldo'], $tasa_bs);
            echo $saldo_cu_bs !== null ? 'Bs ' . number_format($saldo_cu_bs, 2, ',', '.') : '—';
          ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="pagado">
          <td colspan="5">Total pagado hasta ahora</td>
          <td>-$<?php echo number_format((float)$aviso['total_pagado'], 2); ?></td>
          <td colspan="2"></td>
        </tr>
        <tr class="total">
          <td colspan="5">Saldo pendiente total</td>
          <td colspan="2">$<?php echo number_format((float)$aviso['total_saldo'], 2); ?></td>
          <td style="color:#1a7a42;font-weight:700;"><?php
            $total_saldo_bs = convertirABolivares((float)$aviso['total_saldo'], $tasa_bs);
            echo $total_saldo_bs !== null ? 'Bs ' . number_format($total_saldo_bs, 2, ',', '.') : '—';
          ?></td>
        </tr>
      </tbody>
    </table>

    <?php if (!empty($aviso['pagos'])): ?>
    <h3 class="doc-section">Pagos Registrados</h3>
    <table class="doc-table">
      <thead>
        <tr><th>Fecha</th><th>Método</th><th>Referencia</th><th>Monto ($)</th><th>Monto (Bs)</th><th>Registrado por</th></tr>
      </thead>
      <tbody>
        <?php foreach ($aviso['pagos'] as $pg): ?>
        <tr>
          <td><?php echo date('d/m/Y', strtotime($pg['fecha_pago'])); ?></td>
          <td><?php echo METODOS_PAGO[$pg['metodo_pago']] ?? $pg['metodo_pago']; ?></td>
          <td><?php echo htmlspecialchars($pg['referencia'] ?? '—'); ?></td>
          <td style="color:var(--verde);">$<?php echo number_format((float)$pg['monto'], 2); ?></td>
          <td style="color:#1a7a42;"><?php echo !empty($pg['monto_bs']) ? 'Bs ' . number_format((float)$pg['monto_bs'], 2, ',', '.') : '—'; ?></td>
          <td><?php echo htmlspecialchars($pg['registrado_por'] ?? '—'); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="pagado">
          <td colspan="3">Total registrado</td>
          <td>$<?php echo number_format((float)$aviso['total_pagos_registrados'], 2); ?></td>
          <td></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    <?php endif; ?>

    <p class="nota">
      <i class="bi bi-info-circle"></i>
      Estado: <strong><?php
        $primer = $aviso['cuotas_abiertas'][0];
        $estado_res = 'pendiente';
        foreach ($aviso['cuotas_abiertas'] as $cu) {
            if ($cu['estado'] == 'vencida') { $estado_res = 'vencida'; break; }
            if ($cu['estado'] == 'parcial') { $estado_res = 'parcial'; }
        }
        echo ESTADOS_CUOTA[$estado_res];
      ?></strong>
      · Realice el pago antes de las fechas de vencimiento para evitar recargos.
      <?php if (!tieneAccesoTotal()): ?>
        Puede declarar su pago adjuntando comprobante desde <strong>Registro de Pagos</strong>.
      <?php endif; ?>
      Cualquier duda diríjase a la administración del condominio.
    </p>

    <footer class="doc-footer">
      <div>________________________<br><small>Administración</small></div>
      <div>________________________<br><small>Responsable</small></div>
    </footer>
  </div>
  <?php endif; ?>
</body>
</html>