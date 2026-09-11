<?php
include("../Controlador/usuario.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Usuarios | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">

    <div class="page-header">
      <h2><i class="bi bi-shield-lock-fill"></i> Gestión de Usuarios</h2>
      <a href="index.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Regresar a inicio</a>
    </div>

    <div class="form-container">
      <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
          <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        </div>
      <?php endif; ?>

      <form action="usuario.php" method="post">
        <fieldset class="form-section">
          <legend><i class="bi bi-key"></i> Datos de Acceso</legend>
          <div class="form-row">
            <div class="form-group">
              <label for="usuario">Nombre Usuario: <span class="requerido">*</span></label>
              <input type="text" id="usuario" name="usuario" required minlength="3" maxlength="50"
                     placeholder="Nombre de Usuario"
                     value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="passw">Contraseña: <span class="requerido">*</span></label>
              <input type="password" id="passw" name="passw" required minlength="6"
                     placeholder="Mínimo 6 caracteres" autocomplete="new-password">
            </div>
          </div>
        </fieldset>

        <fieldset class="form-section">
          <legend><i class="bi bi-person-fill"></i> Rol y Vinculación</legend>
          <div class="form-row">
            <div class="form-group">
              <label for="rol">Rol del usuario: <span class="requerido">*</span></label>
              <select id="rol" name="rol" required>
                <?php foreach (ROLES as $k => $v): ?>
                  <option value="<?php echo $k; ?>" <?php echo (isset($_POST['rol']) && $_POST['rol'] == $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field-note">Administrador y Junta ven toda la comunidad. Propietario/Inquilino solo lo suyo.</span>
            </div>
            <div class="form-group">
              <label for="persona_cedula">Vincular a persona:<span id="req_persona" class="requerido" style="display:none;"> *</span></label>
              <select id="persona_cedula" name="persona_cedula">
                <option value="">— Sin vínculo —</option>
                <?php foreach ($personas as $per): ?>
                  <option value="<?php echo htmlspecialchars($per['cedula']); ?>"
                    <?php echo (isset($_POST['persona_cedula']) && $_POST['persona_cedula'] == $per['cedula']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($per['cedula'] . ' - ' . $per['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="field-note">Obligatorio para Propietario/Inquilino: solo ven sus propias unidades.</span>
            </div>
          </div>
          <div class="form-group">
            <label for="email">Correo electrónico:</label>
            <input type="email" id="email" name="email" maxlength="100"
                   placeholder="ejemplo@correo.com"
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="form-group">
            <label for="cargo">Cargo: <span class="requerido">*</span></label>
            <input type="text" id="cargo" name="cargo" required maxlength="50"
                   placeholder="Ej: Administrador, Presidente, Tesorero"
                   value="<?php echo isset($_POST['cargo']) ? htmlspecialchars($_POST['cargo']) : ''; ?>">
          </div>
        </fieldset>

        <fieldset class="form-section" id="unidades-section">
          <legend><i class="bi bi-building"></i> Unidades Asignadas</legend>
          <p class="field-note">Asigne al menos una unidad del inmueble. Todos los usuarios del mismo apartamento comparten su deuda.</p>
          <div id="unidades-container"></div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="agregarFilaUnidad()">
            <i class="bi bi-plus-lg"></i> Agregar unidad
          </button>
        </fieldset>

        <div class="form-actions">
          <button type="submit" name="guardar-btn" class="btn btn-success"><i class="bi bi-save"></i> Guardar Usuario</button>
          <button type="reset" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  const unidadesDisponibles = <?php echo json_encode($unidades); ?>;
  const rolesTenencia = <?php echo json_encode(ROLES_TENENCIA); ?>;

  function rolVivienda(rol) {
      return (rol === 'propietario' || rol === 'inquilino');
  }

  function toggleUnidades() {
      const esViv = rolVivienda(document.getElementById('rol').value);
      document.getElementById('unidades-section').style.display = esViv ? '' : 'none';
      document.getElementById('req_persona').style.display = esViv ? '' : 'none';
      const selP = document.getElementById('persona_cedula');
      if (esViv) selP.setAttribute('required', 'required');
      else selP.removeAttribute('required');
      if (esViv && !document.querySelector('#unidades-container .unidad-fila')) agregarFilaUnidad();
  }

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
      Object.entries(rolesTenencia).forEach(([k, v]) => {
          const op = document.createElement('option');
          op.value = k;
          op.textContent = v;
          selectRol.appendChild(op);
      });
      selRol.appendChild(labelRol);
      selRol.appendChild(selectRol);

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-secondary btn-sm';
      btn.textContent = 'Quitar';
      btn.onclick = () => fila.remove();

      fila.appendChild(selUnidad);
      fila.appendChild(selRol);
      fila.appendChild(btn);
      cont.appendChild(fila);
  }

  document.getElementById('rol').addEventListener('change', toggleUnidades);
  toggleUnidades();
  </script>
</body>
</html>