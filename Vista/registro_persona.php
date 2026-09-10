<?php
include("../Controlador/registro_persona.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Persona | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-person-plus-fill"></i> Registro de Persona</h2>
      <a href="consulta_personas.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver a Consulta</a>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form action="registro_persona.php" method="post" autocomplete="off">

        <fieldset class="form-section">
          <legend><i class="bi bi-clipboard-data"></i> Información Personal</legend>
          <div class="form-row">
            <div class="form-group">
              <label for="cedula">Cédula: <span class="requerido">*</span></label>
              <input type="text" id="cedula" name="cedula" required
                     value="<?php echo isset($_POST['cedula']) ? htmlspecialchars($_POST['cedula']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
              <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                     max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="nombre">Nombre completo: <span class="requerido">*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="100"
                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="correo">Correo electrónico:</label>
              <input type="email" id="correo" name="correo" maxlength="100"
                     value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="telefono">Teléfono:</label>
              <input type="tel" id="telefono" name="telefono" pattern="[0-9+ -]{7,15}"
                     value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="direccion">Dirección:</label>
            <input type="text" id="direccion" name="direccion" maxlength="200"
                   value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>">
          </div>
        </fieldset>

        <fieldset class="form-section">
          <legend><i class="bi bi-building"></i> Unidades Asignadas</legend>
          <p class="field-note">Asigne las unidades del inmueble en las que esta persona es propietario, inquilino o residente.</p>
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
                       onchange="toggleJunta()"> ¿Es miembro de la junta de condominio?
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
                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="periodo_inicio">Inicio de periodo:</label>
                <input type="date" id="periodo_inicio" name="periodo_inicio">
              </div>
              <div class="form-group">
                <label for="periodo_fin">Fin de periodo:</label>
                <input type="date" id="periodo_fin" name="periodo_fin">
              </div>
            </div>
          </div>
        </fieldset>

        <div class="form-actions">
          <button type="submit" name="guardar-btn" class="btn btn-success"><i class="bi bi-save"></i> Guardar Persona</button>
          <button type="reset" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</button>
          <a href="consulta_personas.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Regresar</a>
        </div>
      </form>
    </div>
  </div>

  <script>
  const unidadesDisponibles = <?php echo json_encode($unidades); ?>;

  function agregarFilaUnidad(unidadId = '') {
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
          op.textContent = u.codigo + ' (' + u.tipo + ')';
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
      agregarFilaUnidad();
      toggleJunta();
  });
  </script>
</body>
</html>