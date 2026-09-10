<?php
include("../Controlador/consulta_pagos.php");
$es_gestor = tieneAccesoTotal();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historial de Pagos | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-cash-stack"></i> <?php echo $es_gestor ? 'Historial de Pagos' : 'Mis Pagos'; ?></h2>
      <div class="header-actions">
        <?php if ($es_gestor): ?>
          <a href="registro_pagos.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Registrar Pago</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <div class="form-container form-container--wide">
      <form action="consulta_pagos.php" method="get" class="search-form">
        <input type="text" name="buscar" placeholder="<?php echo $es_gestor ? 'Buscar por torre o número...' : 'Filtrar...'; ?>"
               value="<?php echo htmlspecialchars(isset($_GET['buscar']) ? $_GET['buscar'] : ''); ?>">
        <input type="date" name="desde" value="<?php echo htmlspecialchars($fecha_desde); ?>" style="width:auto;" title="Desde">
        <input type="date" name="hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>" style="width:auto;" title="Hasta">
        <select name="metodo" style="width:auto;min-width:140px;">
          <option value="">Todos los métodos</option>
          <?php foreach (METODOS_PAGO as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo ($metodo_f == $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="consulta_pagos.php" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</a>
      </form>

      <div class="results-info">
        <?php echo $total_pagos; ?> pago(s) encontrado(s)
        <?php if ($total_suma > 0): ?> · Subtotal: <strong>$<?php echo number_format($total_suma, 2); ?></strong>
          <?php if ($total_suma_bs > 0): ?> / <strong style="color:#1a7a42;">Bs <?php echo number_format($total_suma_bs, 2, ',', '.'); ?></strong><?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Recibo</th><th>Fecha</th><th>Unidad</th><th>Concepto</th><th>Periodo</th>
              <th>Monto ($)</th><th>Monto (Bs)</th><th>Método</th><th>Referencia</th><th>Registrado por</th><th>Declarado por</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pagos)): ?>
              <tr><td colspan="11" style="text-align:center;padding:30px;">No se encontraron pagos</td></tr>
            <?php else: foreach ($pagos as $pg): ?>
              <tr>
                <td data-label="Recibo"><strong>#<?php echo $pg['id']; ?></strong></td>
                <td data-label="Fecha"><?php echo date('d/m/Y', strtotime($pg['fecha_pago'])); ?></td>
                <td data-label="Unidad"><?php echo htmlspecialchars($pg['numero']); ?></td>
                <td data-label="Concepto"><?php echo htmlspecialchars($pg['concepto']); ?>
                <?php if (($pg['tipo'] ?? '') === 'anticipo'): ?><br><small style="color:var(--navy-500);">(saldo a favor)</small><?php endif; ?></td>
                <td data-label="Periodo"><?php echo (!empty($pg['periodo_mes']) && !empty($pg['periodo_anio']))
                    ? getNombreMes($pg['periodo_mes']) . ' ' . $pg['periodo_anio'] : '—'; ?></td>
                <td data-label="Monto ($)" style="color:var(--verde);font-weight:700;">$<?php echo number_format((float)$pg['monto'], 2); ?></td>
                <td data-label="Monto (Bs)" style="color:#1a7a42;font-weight:700;">
                  <?php echo !empty($pg['monto_bs']) ? 'Bs ' . number_format((float)$pg['monto_bs'], 2, ',', '.') : '—'; ?>
                </td>
                <td data-label="Método"><?php echo METODOS_PAGO[$pg['metodo_pago']] ?? $pg['metodo_pago']; ?></td>
                <td data-label="Referencia"><?php echo htmlspecialchars($pg['referencia'] ?? '—'); ?></td>
                <td data-label="Registrado por" style="text-transform:uppercase;"><?php echo htmlspecialchars($pg['registrado_por'] ?? '—'); ?></td>
                <td data-label="Declarado por"><?php echo !empty($pg['declarado_por']) ? htmlspecialchars($pg['declarado_por']) : '—'; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_paginas > 1): ?>
      <nav class="pagination">
        <?php
        $q = http_build_query(array_filter(['buscar' => $busqueda, 'desde' => $fecha_desde, 'hasta' => $fecha_hasta, 'metodo' => $metodo_f]));
        $sep = strpos($q, '=') !== false ? '&' : '';
        if ($pagina_actual > 1) echo '<a href="?pagina=' . ($pagina_actual - 1) . "$sep$q\"><i class=\"bi bi-chevron-left\"></i> Anterior</a>";
        for ($i = 1; $i <= $total_paginas; $i++) {
            echo $i == $pagina_actual ? "<span class=\"current\">$i</span>" : "<a href=\"?pagina=$i$sep$q\">$i</a>";
        }
        if ($pagina_actual < $total_paginas) echo '<a href="?pagina=' . ($pagina_actual + 1) . "$sep$q\">Siguiente <i class=\"bi bi-chevron-right\"></i></a>";
        ?>
      </nav>
      <?php endif; ?>
    </div>

    <?php if (!$es_gestor && !empty($comprobantes_usuario)): ?>
    <div class="form-container form-container--wide" style="margin-top:18px;">
      <h3 style="margin-bottom:12px;color:var(--navy-700);"><i class="bi bi-file-earmark-check"></i> Mis Declaraciones de Pago (Comprobantes)</h3>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Fecha solicitud</th><th>Unidad</th><th>Concepto/Período</th>
              <th>Monto ($)</th><th>Monto (Bs)</th><th>Método</th><th>Estado</th><th>Archivo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($comprobantes_usuario as $c): ?>
            <tr>
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
              <td data-label="Estado">
                <?php
                $badge = ($c['estado'] === 'pendiente') ? 'badge-warning'
                        : (($c['estado'] === 'aprobado') ? 'badge-success' : 'badge-danger');
                ?>
                <span class="badge <?php echo $badge; ?>"><?php echo ESTADOS_COMPROBANTE[$c['estado']]; ?></span>
                <?php if ($c['estado'] === 'rechazado' && !empty($c['motivo_rechazo'])): ?>
                  <br><small class="motivo-rechazo"><strong>Motivo:</strong> <?php echo htmlspecialchars($c['motivo_rechazo']); ?></small>
                <?php endif; ?>
              </td>
              <td data-label="Archivo">
                <a href="../Controlador/descargar_comprobante.php?id=<?php echo $c['id']; ?>" target="_blank" class="btn btn-sm btn-outline">
                  <i class="bi bi-file-earmark"></i> Ver
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>