<?php
include("../Controlador/editar_persona.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Persona | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-pencil-square"></i> Editar Persona</h2>
      <a href="ver_persona.php?cedula=<?php echo urlencode($cedula); ?>" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Ver Persona</a>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <?php if (!$persona): ?>
      <div class="mensaje error">❌ Persona no encontrada</div>
    <?php else: ?>

    <div class="form-container">
      <form action="editar_persona.php?cedula=<?php echo urlencode($persona['cedula']); ?>" method="post" autocomplete="off">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($persona['cedula']); ?>">

        <fieldset class="form-section">
          <legend><i class="bi bi-clipboard-data"></i> Información Personal</legend>
          <div class="form-row">
            <div class="form-group">
              <label>Cédula (no editable):</label>
              <input type="text" class="readonly-field" value="<?php echo htmlspecialchars($persona['cedula']); ?>" readonly>
            </div>
            <div class="form-group">
              <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
              <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo htmlspecialchars($persona['fecha_nacimiento'] ?? ''); ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="nombre">Nombre completo: <span class="requerido">*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="100"
                   value="<?php echo htmlspecialchars($persona['nombre']); ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="correo">Correo electrónico:</label>
              <input type="email" id="correo" name="correo" maxlength="100"
                     value="<?php echo htmlspecialchars($persona['correo'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="telefono">Teléfono:</label>
              <input type="tel" id="telefono" name="telefono" pattern="[0-9+ -]{7,15}"
                     value="<?php echo htmlspecialchars($persona['telefono'] ?? ''); ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="direccion">Dirección:</label>
            <input type="text" id="direccion" name="direccion" maxlength="200"
                   value="<?php echo htmlspecialchars($persona['direccion'] ?? ''); ?>">
          </div>
        </fieldset>

        <fieldset class="form-section">
          <legend><i class="bi bi-building"></i> Unidades Asignadas</legend>
          <div id="unidades-container"></div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="agregarFilaUnidad()">
            <i class="bi bi-plus-lg"></i> Agregar unidad
          </button>
        </fieldset>

        <fieldset class="form-section">
          <legend><i class="bi bi-shield-check"></i> Junta de Condominio</legend>
          <div class="form-row">
            <div class="form-group">
              <label class="checkbox-line" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="es_junta" name="es_junta" value="1" style="width:auto;"
                       onchange="toggleJunta()" <?php echo $junta ? 'checked' : ''; ?>> ¿Es miembro de la junta de condominio?
              </label>
            </div>
          </div>
          <div id="junta-fields" style="display:none;">
            <div class="form-row">
              <div class="form-group">
                <label for="cargo_junta">Cargo:</label>
                <select id="cargo_junta" name="cargo_junta">
                  <option value="">Seleccione cargo</option>
                  <?php foreach (CARGOS_JUNTA as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo ($junta && $junta['cargo'] == $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="periodo_inicio">Inicio de periodo:</label>
                <input type="date" id="periodo_inicio" name="periodo_inicio"
                       value="<?php echo htmlspecialchars($junta['periodo_inicio'] ?? ''); ?>">
              </div>
              <div class="form-group">
                <label for="periodo_fin">Fin de periodo:</label>
                <input type="date" id="periodo_fin" name="periodo_fin"
                       value="<?php echo htmlspecialchars($junta['periodo_fin'] ?? ''); ?>">
              </div>
            </div>
          </div>
        </fieldset>

        <div class="form-actions">
          <button type="submit" name="actualizar-btn" class="btn btn-success"><i class="bi bi-save"></i> Actualizar Persona</button>
          <a href="ver_persona.php?cedula=<?php echo urlencode($persona['cedula']); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancelar</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <script>
  const unidadesDisponibles = <?php echo json_encode($unidades); ?>;
  const tenenciasActuales = <?php echo json_encode($tenencias); ?>;

  function agregarFilaUnidad(unidadId = '', rol = 'propietario') {
      const cont = document.getElementById('unidades-container');
      const fila = document.createElement('div');
      fila.className = 'form-row unidad-fila';
      fila.style.alignItems = 'flex-end';

      const selUnidad = document.createElement('div');
      selUnidad.className = 'form-group';
      selUnidad.style.flex = '1';
      const label = document.createElement('label');
      label.textContent = 'Unidad:';
      const select = document.createElement('select');
      select.name = 'unidad_id[]';
      select.style.width = '100%';
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'Seleccione unidad';
      select.appendChild(opt0);
      unidadesDisponibles.forEach(u => {
          const op = document.createElement('option');
          op.value = u.id;
          op.textContent = u.codigo;
          if (String(u.id) === String(unidadId)) op.selected = true;
          select.appendChild(op);
      });
      selUnidad.appendChild(label);
      selUnidad.appendChild(select);

      const selRol = document.createElement('div');
      selRol.className = 'form-group';
      const labelRol = document.createElement('label');
      labelRol.textContent = 'Rol:';
      const selectRol = document.createElement('select');
      selectRol.name = 'rol_unidad[]';
      selectRol.style.width = '100%';
      Object.entries(<?php echo json_encode(ROLES_TENENCIA); ?>).forEach(([k, v]) => {
          const op = document.createElement('option');
          op.value = k;
          op.textContent = v;
          if (k === rol) op.selected = true;
          selectRol.appendChild(op);
      });
      selRol.appendChild(labelRol);
      selRol.appendChild(selectRol);

      const btnRemove = document.createElement('div');
      btnRemove.className = 'form-group';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-danger btn-sm';
      btn.innerHTML = '<i class="bi bi-trash"></i>';
      btn.onclick = () => fila.remove();
      btnRemove.appendChild(btn);

      fila.appendChild(selUnidad);
      fila.appendChild(selRol);
      fila.appendChild(btnRemove);
      cont.appendChild(fila);
  }

  function toggleJunta() {
      document.getElementById('junta-fields').style.display =
          document.getElementById('es_junta').checked ? 'block' : 'none';
  }

  document.addEventListener('DOMContentLoaded', function() {
      tenenciasActuales.forEach(t => agregarFilaUnidad(t.unidad_id, t.rol));
      if (tenenciasActuales.length === 0) agregarFilaUnidad();
      toggleJunta();
  });
  </script>
</body>
</html>