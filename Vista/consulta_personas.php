<?php
include("../Controlador/consulta_personas.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consulta de Personas | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-people-fill"></i> Consulta de Personas</h2>
      <div class="header-actions">
        <a href="registro_persona.php" class="btn btn-success"><i class="bi bi-person-plus-fill"></i> Registrar Persona</a>
        <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
      </div>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <div class="form-container form-container--wide">
      <form action="consulta_personas.php" method="get" class="search-form">
        <input type="text" name="buscar" placeholder="Buscar por cédula, nombre, teléfono o correo..."
               value="<?php echo htmlspecialchars(isset($_GET['buscar']) ? $_GET['buscar'] : ''); ?>">
        <select name="tipo" style="width:auto;min-width:190px;">
          <option value="">Todos</option>
          <option value="propietario" <?php echo ($tipo == 'propietario') ? 'selected' : ''; ?>>Propietarios</option>
          <option value="inquilino" <?php echo ($tipo == 'inquilino') ? 'selected' : ''; ?>>Inquilinos</option>
          <option value="residente" <?php echo ($tipo == 'residente') ? 'selected' : ''; ?>>Residentes</option>
          <option value="junta" <?php echo ($tipo == 'junta') ? 'selected' : ''; ?>>Junta de Condominio</option>
          <option value="sin_unidad" <?php echo ($tipo == 'sin_unidad') ? 'selected' : ''; ?>>Sin unidad asignada</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="consulta_personas.php" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</a>
      </form>

      <div class="results-info"><?php echo $total_personas; ?> persona(s) encontrada(s)</div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Cédula</th>
              <th>Nombre</th>
              <th>Contacto</th>
              <th>Unidades</th>
              <th>Categoría</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($personas)): ?>
              <tr><td colspan="6" style="text-align:center;padding:30px;">No se encontraron personas</td></tr>
            <?php else: foreach ($personas as $per): ?>
              <tr>
                <td data-label="Cédula"><?php echo htmlspecialchars($per['cedula']); ?></td>
                <td data-label="Nombre"><strong><?php echo htmlspecialchars($per['nombre']); ?></strong></td>
                <td data-label="Contacto">
                  <div><?php echo htmlspecialchars($per['telefono'] ?? '—'); ?></div>
                  <small style="color:var(--gris-500);"><?php echo htmlspecialchars($per['correo'] ?? '—'); ?></small>
                </td>
                <td data-label="Unidades"><?php echo htmlspecialchars($per['unidades'] ?? '—'); ?></td>
                <td data-label="Categoría">
                  <?php
                  $roles = $per['roles_tenencia'] ? explode(',', $per['roles_tenencia']) : [];
                  foreach ($roles as $r):
                    $clase = ($r == 'propietario') ? 'badge-success' : (($r == 'inquilino') ? 'badge-warning' : 'badge-neutral');
                  ?>
                    <span class="badge <?php echo $clase; ?>"><?php echo ucfirst($r); ?></span>
                  <?php endforeach;
                  if (!empty($per['cargo_junta'])): ?>
                    <span class="badge badge-gold">Junta: <?php echo htmlspecialchars($per['cargo_junta']); ?></span>
                  <?php endif;
                  if (empty($roles) && empty($per['cargo_junta'])): ?>
                    <span class="badge badge-neutral">Sin rol</span>
                  <?php endif; ?>
                </td>
                <td data-label="Acciones">
                  <div class="action-buttons">
                    <a href="ver_persona.php?cedula=<?php echo urlencode($per['cedula']); ?>" class="btn-view"><i class="bi bi-eye"></i> Ver</a>
                    <a href="editar_persona.php?cedula=<?php echo urlencode($per['cedula']); ?>" class="btn-edit"><i class="bi bi-pencil"></i> Editar</a>
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
        $q = http_build_query(array_filter([
            'buscar' => $busqueda, 'tipo' => $tipo
        ]));
        $sep = strpos($q, '=') !== false ? '&' : '';
        if ($pagina_actual > 1) echo '<a href="?pagina=' . ($pagina_actual - 1) . "$sep$q\"><i class=\"bi bi-chevron-left\"></i> Anterior</a>";
        for ($i = 1; $i <= $total_paginas; $i++) {
            if ($i == $pagina_actual) echo "<span class=\"current\">$i</span>";
            else echo "<a href=\"?pagina=$i$sep$q\">$i</a>";
        }
        if ($pagina_actual < $total_paginas) echo '<a href="?pagina=' . ($pagina_actual + 1) . "\">Siguiente <i class=\"bi bi-chevron-right\"></i></a>";
        ?>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>