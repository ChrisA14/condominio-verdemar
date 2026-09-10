<?php
include("../Controlador/verificar_pagos.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificación de Pagos | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-clipboard-check"></i> Verificación de Pagos</h2>
      <div class="header-actions">
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo $_SESSION['tipo_mensaje'] ?? ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <div class="form-container form-container--wide">
      <form action="verificar_pagos.php" method="get" class="search-form">
        <input type="text" name="buscar" placeholder="Buscar por torre o número..."
               value="<?php echo htmlspecialchars($busqueda); ?>">
        <select name="estado" style="width:auto;min-width:150px;">
          <option value="">Todos los estados</option>
          <option value="pendiente" <?php echo ($estado_f == 'pendiente') ? 'selected' : ''; ?>>🟡 Pendientes</option>
          <option value="aprobado" <?php echo ($estado_f == 'aprobado') ? 'selected' : ''; ?>>🟢 Aprobados</option>
          <option value="rechazado" <?php echo ($estado_f == 'rechazado') ? 'selected' : ''; ?>>🔴 Rechazados</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="verificar_pagos.php" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</a>
      </form>

      <div class="results-info"><?php echo count($comprobantes); ?> comprobante(s)
        <?php if ($pendientes_count > 0): ?>
          · <strong style="color:var(--amarillo);"><?php echo $pendientes_count; ?> pendiente(s) por revisar</strong>
        <?php endif; ?>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Fecha solicitud</th><th>Unidad</th><th>Concepto/Período</th>
              <th>Monto ($)</th><th>Monto (Bs)</th><th>Método</th><th>Ref.</th><th>Solicitado por</th>
              <th>Comprobante</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($comprobantes)): ?>
              <tr><td colspan="12" style="text-align:center;padding:30px;">No hay comprobantes para mostrar</td></tr>
            <?php else: foreach ($comprobantes as $c): ?>
              <tr class="<?php echo $c['estado'] === 'pendiente' ? 'fila-pendiente' : ''; ?>">
                <td data-label="#"><strong>#<?php echo $c['id']; ?></strong></td>
                <td data-label="Fecha solicitud"><?php echo date('d/m/Y H:i', strtotime($c['fecha_solicitud'])); ?></td>
                <td data-label="Unidad"><?php echo htmlspecialchars($c['numero']); ?></td>
                <td data-label="Concepto/Período"><?php echo htmlspecialchars($c['concepto']); ?>
                  <br><small><?php echo getNombreMes($c['periodo_mes']) . ' ' . $c['periodo_anio']; ?></small></td>
                <td data-label="Monto ($)" style="font-weight:600;">$<?php echo number_format((float)$c['monto'], 2); ?></td>
                <td data-label="Monto (Bs)" style="font-weight:600;color:#1a7a42;">
                  <?php echo !empty($c['monto_bs']) ? 'Bs ' . number_format((float)$c['monto_bs'], 2, ',', '.') : '—'; ?>
                </td>
                <td data-label="Método"><?php echo METODOS_PAGO[$c['metodo_pago']] ?? $c['metodo_pago']; ?></td>
                <td data-label="Ref."><?php echo htmlspecialchars($c['referencia'] ?? '—'); ?></td>
                <td data-label="Solicitado por"><?php echo htmlspecialchars($c['solicitado_por_nombre'] ?? $c['solicitado_por_usuario'] ?? '—'); ?></td>
                <td data-label="Comprobante">
                  <a href="../Controlador/descargar_comprobante.php?id=<?php echo $c['id']; ?>" target="_blank" class="btn btn-sm btn-outline">
                    <i class="bi bi-file-earmark"></i> Ver archivo
                  </a>
                </td>
                <td data-label="Estado">
                  <?php
                  $badge = ($c['estado'] === 'pendiente') ? 'badge-warning'
                          : (($c['estado'] === 'aprobado') ? 'badge-success' : 'badge-danger');
                  ?>
                  <span class="badge <?php echo $badge; ?>"><?php echo ESTADOS_COMPROBANTE[$c['estado']]; ?></span>
                </td>
                <td data-label="Acciones">
                  <?php if ($c['estado'] === 'pendiente'): ?>
                  <div class="action-buttons">
                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Aprobar este comprobante y registrar el pago?');">
                      <input type="hidden" name="comp_id" value="<?php echo $c['id']; ?>">
                      <button type="submit" name="aprobar-comprobante" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg"></i> Aprobar
                      </button>
                    </form>
                    <details class="rechazo-details">
                      <summary class="btn btn-sm btn-danger-soft"><i class="bi bi-x-lg"></i> Rechazar</summary>
                      <form method="post" class="rechazo-form" onsubmit="return confirm('¿Rechazar este comprobante?');">
                        <input type="hidden" name="comp_id" value="<?php echo $c['id']; ?>">
                        <textarea name="motivo_rechazo" rows="2" maxlength="255" placeholder="Motivo del rechazo (obligatorio)" required></textarea>
                        <button type="submit" name="rechazar-comprobante" class="btn btn-sm btn-danger">
                          <i class="bi bi-x-circle"></i> Confirmar Rechazo
                        </button>
                      </form>
                    </details>
                  </div>
                  <?php elseif ($c['estado'] === 'rechazado' && !empty($c['motivo_rechazo'])): ?>
                    <small class="motivo-rechazo"><strong>Motivo:</strong> <?php echo htmlspecialchars($c['motivo_rechazo']); ?></small>
                  <?php else: ?>
                    <span class="badge badge-success">Aprobado</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>