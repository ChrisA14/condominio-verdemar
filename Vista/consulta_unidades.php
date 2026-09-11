<?php
include("../Controlador/consulta_unidades.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consulta de Unidades | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-buildings-fill"></i> Consulta de Unidades</h2>
      <div class="header-actions">
        <a href="registro_unidad.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Registrar Unidad</a>
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <div class="form-container form-container--wide">
      <form action="consulta_unidades.php" method="get" class="search-form">
        <input type="text" name="buscar" placeholder="Buscar por torre o número..."
               value="<?php echo htmlspecialchars(isset($_GET['buscar']) ? $_GET['buscar'] : ''); ?>">
        <select name="estado" style="width:auto;min-width:150px;">
          <option value="">Todos</option>
          <option value="activa" <?php echo ($filtro_estado == 'activa') ? 'selected' : ''; ?>>Activas</option>
          <option value="inactiva" <?php echo ($filtro_estado == 'inactiva') ? 'selected' : ''; ?>>Inactivas</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="consulta_unidades.php" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</a>
      </form>

      <div class="results-info"><?php echo $total_unidades; ?> unidad(es) en el catálogo</div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Unidad</th><th>Tipo</th><th>Ocupantes</th><th>Deuda</th><th>Estado</th></tr>
          </thead>
          <tbody>
            <?php if (empty($unidades)): ?>
              <tr><td colspan="5" style="text-align:center;padding:30px;">No hay unidades registradas</td></tr>
            <?php else: foreach ($unidades as $un): ?>
              <tr>
                <td data-label="Unidad"><strong><?php echo htmlspecialchars($un['numero']); ?></strong></td>
                <td data-label="Tipo"><?php echo TIPOS_UNIDAD[$un['tipo']] ?? $un['tipo']; ?></td>
                <td data-label="Ocupantes">
                  <?php
                  $ocupantes = $un['ocupantes'] ? explode(';', $un['ocupantes']) : [];
                  if (empty($ocupantes)): ?>
                    <span class="badge badge-neutral">Desocupada</span>
                  <?php else: foreach ($ocupantes as $oc):
                    [$nombre, $rol] = array_pad(explode('|', $oc), 2, '');
                    $clase = ($rol == 'propietario') ? 'badge-success' : (($rol == 'inquilino') ? 'badge-warning' : 'badge-neutral');
                  ?>
                    <span class="badge <?php echo $clase; ?>"><?php echo htmlspecialchars($nombre) . ' · ' . ucfirst($rol); ?></span>
                  <?php endforeach; endif; ?>
                </td>
                <td data-label="Deuda" style="color:<?php echo $un['saldo'] > 0 ? 'var(--rojo)' : 'var(--verde)'; ?>;font-weight:600;">
                  <?php echo number_format((float)$un['saldo'], 2); ?> $
                </td>
                <td data-label="Estado"><span class="badge <?php echo $un['estado'] == 'activa' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($un['estado']); ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_paginas > 1): ?>
      <nav class="pagination">
        <?php
        $q = http_build_query(array_filter(['buscar' => $busqueda, 'estado' => $filtro_estado]));
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