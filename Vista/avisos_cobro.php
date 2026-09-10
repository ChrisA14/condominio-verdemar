<?php
include("../Controlador/avisos_cobro.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Avisos de Cobro | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-receipt"></i> Avisos de Cobro</h2>
      <div class="header-actions">
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <?php if ($es_gestor): ?>
    <div class="form-container" style="margin-bottom:18px;">
      <form action="avisos_cobro.php" method="post" class="search-form">
        <label style="margin:0;font-weight:700;white-space:nowrap;">Generación masiva mensual:</label>
        <select name="mes_generar" style="width:auto;" required>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>><?php echo getNombreMes($m); ?></option>
          <?php endfor; ?>
        </select>
        <input type="number" name="anio_generar" min="2020" max="2200" value="<?php echo date('Y'); ?>" class="input-sm" required>
        <button type="submit" name="generar-btn" class="btn btn-success"><i class="bi bi-magic"></i> Generar Avisos</button>
      </form>
      <p class="field-note">Genera la cuota de todos los conceptos activos para todas las unidades activas del mes indicado (no duplica las ya emitidas).</p>
    </div>
    <?php endif; ?>

    <div class="form-container form-container--wide">
      <form action="avisos_cobro.php" method="get" class="search-form">
        <input type="text" name="buscar" placeholder="Buscar por torre o número de unidad..."
               value="<?php echo htmlspecialchars($busqueda); ?>">
        <select name="mes" style="width:auto;min-width:130px;">
          <option value="">Todos los meses</option>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo ($mes_f == $m) ? 'selected' : ''; ?>><?php echo getNombreMes($m); ?></option>
          <?php endfor; ?>
        </select>
        <input type="number" name="anio" min="2020" max="2200" value="<?php echo $anio_f; ?>" class="input-sm">
        <select name="estado" style="width:auto;min-width:140px;">
          <option value="">Todos los estados</option>
          <?php foreach (ESTADOS_CUOTA as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo ($estado_f == $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>
        <select name="concepto" style="width:auto;min-width:150px;">
          <option value="0">Todos los conceptos</option>
          <?php foreach ($conceptos as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo ($concepto_f == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="avisos_cobro.php" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</a>
      </form>

      <div class="results-info"><?php echo $total_unidades; ?> unidad(es) con deuda pendiente</div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Unidad</th><th>Responsable</th><th>Período</th>
              <th>Deuda Total</th><th>Pagado</th><th>Saldo ($)</th><th>Saldo (Bs)</th>
              <th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($unidades_deuda)): ?>
              <tr><td colspan="9" style="text-align:center;padding:30px;">No hay unidades con deuda pendiente</td></tr>
            <?php else: foreach ($unidades_deuda as $ud): ?>
              <?php
                $estado_resumen = 'pendiente';
                if ($ud['hay_vencida']) $estado_resumen = 'vencida';
                elseif ($ud['hay_parcial']) $estado_resumen = 'parcial';
                $claseEstado = ($estado_resumen == 'vencida') ? 'badge-danger'
                              : (($estado_resumen == 'parcial') ? 'badge-info' : 'badge-warning');
              ?>
              <tr>
                <td data-label="Unidad"><strong><?php echo htmlspecialchars($ud['numero']); ?></strong></td>
                <td data-label="Responsable"><?php echo htmlspecialchars($ud['responsable'] ?? '—'); ?></td>
                <td data-label="Período"><?php echo getNombreMes($ud['desde_mes']) . ' ' . $ud['desde_anio']
                  . ' → ' . getNombreMes($ud['hasta_mes']) . ' ' . $ud['hasta_anio']
                  . ' <small>(<strong>' . $ud['meses'] . ' meses</strong>)</small>'; ?></td>
                <td data-label="Deuda Total">$<?php echo number_format((float)$ud['monto'], 2); ?></td>
                <td data-label="Pagado" style="color:var(--verde);">$<?php echo number_format((float)$ud['pagado'], 2); ?></td>
                <td data-label="Saldo ($)" style="color:var(--rojo);font-weight:700;">
                  $<?php echo number_format((float)$ud['saldo'], 2); ?>
                </td>
                <td data-label="Saldo (Bs)" style="color:#1a7a42;font-weight:700;">
                  <?php
                  $saldo_bs = convertirABolivares((float)$ud['saldo'], $tasa_bs);
                  echo $saldo_bs !== null ? 'Bs ' . number_format($saldo_bs, 2, ',', '.') : '—';
                  ?>
                </td>
                <td data-label="Estado">
                  <span class="badge <?php echo $claseEstado; ?>"><?php echo ESTADOS_CUOTA[$estado_resumen]; ?></span>
                </td>
                <td data-label="Acciones">
                  <div class="action-buttons">
                    <a href="vista_aviso.php?unidad_id=<?php echo $ud['id']; ?>" class="btn-view" target="_blank"><i class="bi bi-printer"></i> Estado de Cuenta</a>
                    <?php if (!$es_gestor && $ud['saldo'] > 0): ?>
                      <a href="registro_pagos.php?aviso_unidad_id=<?php echo $ud['id']; ?>" class="btn-edit"><i class="bi bi-upload"></i> Declarar Pago</a>
                    <?php endif; ?>
                    <?php if ($es_gestor && $ud['saldo'] > 0): ?>
                      <a href="registro_pagos.php?aviso_unidad_id=<?php echo $ud['id']; ?>" class="btn-edit"><i class="bi bi-cash-coin"></i> Registrar Pago</a>
                      <form method="post" style="display:inline;" onsubmit="return confirm('¿Condonar TODA la deuda de esta unidad?');">
                        <input type="hidden" name="unidad_id" value="<?php echo $ud['id']; ?>">
                        <button type="submit" name="condonar-unidad" class="btn-edit btn-danger-soft"><i class="bi bi-x-circle"></i> Condonar</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_paginas > 1): ?>
      <nav class="pagination">
        <?php
        $q = http_build_query(array_filter(['buscar' => $busqueda, 'mes' => $mes_f ?: null, 'anio' => $anio_f ?: null, 'estado' => $estado_f, 'concepto' => $concepto_f ?: null]));
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
  </div>
</body>
</html>