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
      <div class="mensaje <?php echo htmlspecialchars($_SESSION['tipo_mensaje'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo nl2br(htmlspecialchars($_SESSION['mensaje'], ENT_QUOTES, 'UTF-8')); unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form action="registro_persona.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <fieldset class="form-section">
          <legend><i class="bi bi-clipboard-data"></i> Información Personal</legend>
          <div class="form-row">
            <div class="form-group">
              <label for="tipo_ciudadano" id="label_tipo_cedula">Tipo de Cédula: <span class="requerido">*</span></label>
              <select id="tipo_ciudadano" name="tipo_ciudadano" required
                      onchange="cambiarTipoPersona(); verificarCedulaAJAX();">
                <option value="">Seleccione</option>
                <?php foreach (TIPOS_CEDULA as $k => $v): ?>
                  <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo (isset($_POST['tipo_ciudadano']) && $_POST['tipo_ciudadano'] === $k) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($k . ' - ' . $v, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="cedula" id="label_numero_cedula">Número de Cédula: <span class="requerido">*</span></label>
              <input type="text" id="cedula" name="numero_cedula" required maxlength="18"
                     inputmode="numeric" pattern="[0-9]*"
                     placeholder="12345678"
                     oninput="this.value = this.value.replace(/[^0-9]/g, ''); verificarCedulaAJAX();"
                     value="<?php echo isset($_POST['numero_cedula']) ? htmlspecialchars($_POST['numero_cedula']) : ''; ?>">
              <span id="cedula-aviso" class="aviso-cedula" style="display:none;"></span>
            </div>
            <div class="form-group" id="grupo_fecha_nacimiento">
              <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
              <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                     max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="nombre" id="label_nombre">Nombre completo: <span class="requerido">*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="100" autocomplete="off"
                   oninput="sanearNombre(this)"
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
              <input type="tel" id="telefono" name="telefono" inputmode="numeric"
                     maxlength="15" pattern="[0-9]{7,15}"
                     oninput="this.value = this.value.replace(/\D/g, '')"
                     value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="direccion">Dirección:</label>
            <input type="text" id="direccion" name="direccion" maxlength="200"
                   value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>">
          </div>
        </fieldset>

        <fieldset class="form-section" id="rep-legal-fields" style="display:none;">
          <legend><i class="bi bi-person-badge"></i> Representante Legal</legend>
          <p class="field-note">Datos del representante legal de la persona jurídica o gubernamental. Opcional.</p>
          <div class="form-group">
            <label for="rep_legal_nombre">Nombre del representante:</label>
            <input type="text" id="rep_legal_nombre" name="rep_legal_nombre" maxlength="100" autocomplete="off"
                   oninput="sanearNombreRepLegal(this)"
                   value="<?php echo isset($_POST['rep_legal_nombre']) ? htmlspecialchars($_POST['rep_legal_nombre']) : ''; ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="rep_legal_tipo_cedula">Tipo de Cédula:</label>
              <select id="rep_legal_tipo_cedula" name="rep_legal_tipo_cedula">
                <option value="">Seleccione</option>
                <?php foreach (TIPOS_CEDULA_REP_LEGAL as $k => $v): ?>
                  <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo (isset($_POST['rep_legal_tipo_cedula']) && $_POST['rep_legal_tipo_cedula'] === $k) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($k . ' - ' . $v, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="rep_legal_numero_cedula">Número de Cédula:</label>
              <input type="text" id="rep_legal_numero_cedula" name="rep_legal_numero_cedula" maxlength="18"
                     inputmode="numeric" pattern="[0-9]*"
                     oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                     value="<?php echo isset($_POST['rep_legal_numero_cedula']) ? htmlspecialchars($_POST['rep_legal_numero_cedula']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="rep_legal_fecha_nac">Fecha de Nacimiento:</label>
              <input type="date" id="rep_legal_fecha_nac" name="rep_legal_fecha_nac"
                     max="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo isset($_POST['rep_legal_fecha_nac']) ? htmlspecialchars($_POST['rep_legal_fecha_nac']) : ''; ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="rep_legal_correo">Correo electrónico:</label>
              <input type="email" id="rep_legal_correo" name="rep_legal_correo" maxlength="100"
                     value="<?php echo isset($_POST['rep_legal_correo']) ? htmlspecialchars($_POST['rep_legal_correo']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="rep_legal_telefono">Teléfono:</label>
              <input type="tel" id="rep_legal_telefono" name="rep_legal_telefono" inputmode="numeric"
                     maxlength="15" pattern="[0-9]{7,15}"
                     oninput="this.value = this.value.replace(/\D/g, '')"
                     value="<?php echo isset($_POST['rep_legal_telefono']) ? htmlspecialchars($_POST['rep_legal_telefono']) : ''; ?>">
            </div>
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
  const unidadesDisponibles = <?php echo json_encode($unidades, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function pisosUnicos() {
      return Array.from(new Set(unidadesDisponibles.map(u => String(u.piso))))
          .sort((a, b) => parseInt(a, 10) - parseInt(b, 10));
  }

  function letraDeNumero(numero) {
      const idx = String(numero).lastIndexOf('-');
      return idx >= 0 ? String(numero).slice(idx + 1) : String(numero);
  }

  function unidadesPorPiso(piso) {
      return unidadesDisponibles.filter(u => String(u.piso) === String(piso));
  }

  function letrasDisponibles(piso) {
      return Array.from(new Set(unidadesPorPiso(piso).map(u => letraDeNumero(u.numero)))).sort();
  }

  function agregarFilaUnidad(unidadId = '', rol = 'propietario') {
      const cont = document.getElementById('unidades-container');
      const fila = document.createElement('div');
      fila.className = 'form-row unidad-fila';
      fila.style.alignItems = 'flex-end';
      fila.style.flexWrap = 'wrap';

      const divPiso = document.createElement('div');
      divPiso.className = 'form-group';
      divPiso.style.minWidth = '130px';
      const lblPiso = document.createElement('label');
      lblPiso.textContent = 'Piso:';
      const selPiso = document.createElement('select');
      selPiso.style.width = '100%';
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'Seleccione';
      selPiso.appendChild(opt0);
      pisosUnicos().forEach(p => {
          const op = document.createElement('option');
          op.value = p;
          op.textContent = p;
          selPiso.appendChild(op);
      });
      divPiso.appendChild(lblPiso);
      divPiso.appendChild(selPiso);

      const divApto = document.createElement('div');
      divApto.className = 'form-group';
      divApto.style.minWidth = '110px';
      const lblApto = document.createElement('label');
      lblApto.textContent = 'Apto:';
      const selApto = document.createElement('select');
      selApto.style.width = '100%';
      const optA0 = document.createElement('option');
      optA0.value = '';
      optA0.textContent = '—';
      selApto.appendChild(optA0);
      divApto.appendChild(lblApto);
      divApto.appendChild(selApto);

      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'unidad_id[]';
      hidden.value = '';

      const divInfo = document.createElement('div');
      divInfo.className = 'form-group';
      divInfo.style.minWidth = '150px';
      const txt = document.createElement('span');
      txt.textContent = 'Unidad: —';
      divInfo.appendChild(txt);

      const selRol = document.createElement('div');
      selRol.className = 'form-group';
      selRol.style.flex = '1';
      selRol.style.minWidth = '150px';
      const labelRol = document.createElement('label');
      labelRol.textContent = 'Rol:';
      const selectRol = document.createElement('select');
      selectRol.name = 'rol_unidad[]';
      selectRol.style.width = '100%';
      Object.entries(<?php echo json_encode(ROLES_TENENCIA, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>).forEach(([k, v]) => {
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

      function actualizarAptos() {
          const p = selPiso.value;
          while (selApto.options.length > 1) selApto.remove(1);
          if (!p) return;
          letrasDisponibles(p).forEach(l => {
              const op = document.createElement('option');
              op.value = l;
              op.textContent = l;
              selApto.appendChild(op);
          });
      }

      function actualizarUnidad() {
          const p = selPiso.value;
          const l = selApto.value;
          const unit = unidadesDisponibles.find(u => String(u.piso) === p && String(u.numero) === p + '-' + l);
          if (unit) {
              hidden.value = unit.id;
              txt.textContent = 'Unidad: ' + p + '-' + l + (unit.tipo ? ' (' + unit.tipo + ')' : '');
          } else {
              hidden.value = '';
              txt.textContent = 'Unidad: —';
          }
      }

      selPiso.addEventListener('change', () => { actualizarAptos(); actualizarUnidad(); });
      selApto.addEventListener('change', actualizarUnidad);

      fila.appendChild(divPiso);
      fila.appendChild(divApto);
      fila.appendChild(hidden);
      fila.appendChild(divInfo);
      fila.appendChild(selRol);
      fila.appendChild(btnRemove);

      if (unidadId) {
          const unit = unidadesDisponibles.find(u => String(u.id) === String(unidadId));
          if (unit) {
              selPiso.value = String(unit.piso);
              actualizarAptos();
              selApto.value = letraDeNumero(unit.numero);
          }
      }
      actualizarUnidad();

      cont.appendChild(fila);
  }

  function toggleJunta() {
      document.getElementById('junta-fields').style.display =
          document.getElementById('es_junta').checked ? 'block' : 'none';
  }

  function sanearNombre(el) {
      const tipo = document.getElementById('tipo_ciudadano').value;
      const conNumeros = <?php echo json_encode(TIPOS_NOMBRE_CON_NUMEROS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.includes(tipo);
      const patron = conNumeros ? /[^A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s',.]/g : /[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s',.]/g;
      el.value = el.value.replace(patron, '');
  }

  function sanearNombreRepLegal(el) {
      el.value = el.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s'.]/g, '');
  }

  function cambiarTipoPersona() {
      const tipo = document.getElementById('tipo_ciudadano').value;
      const esJg = <?php echo json_encode(TIPOS_NOMBRE_CON_NUMEROS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.includes(tipo);
      document.getElementById('grupo_fecha_nacimiento').style.display = esJg ? 'none' : '';
      document.getElementById('rep-legal-fields').style.display = esJg ? 'block' : 'none';
      document.getElementById('label_nombre').innerHTML = esJg
          ? 'Razón Social: <span class="requerido">*</span>'
          : 'Nombre completo: <span class="requerido">*</span>';
      document.getElementById('label_tipo_cedula').innerHTML = esJg
          ? 'Tipo de RIF: <span class="requerido">*</span>'
          : 'Tipo de Cédula: <span class="requerido">*</span>';
      document.getElementById('label_numero_cedula').innerHTML = esJg
          ? 'Número de RIF: <span class="requerido">*</span>'
          : 'Número de Cédula: <span class="requerido">*</span>';
  }

  function verificarCedulaAJAX() {
      const aviso = document.getElementById('cedula-aviso');
      const tipo = document.getElementById('tipo_ciudadano').value;
      const numero = document.getElementById('cedula').value.trim();
      if (!tipo || numero.length < 5) {
          aviso.style.display = 'none';
          aviso.textContent = '';
          return;
      }
      const fd = new FormData();
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
      fd.append('accion', 'verificar_cedula');
      fd.append('tipo_ciudadano', tipo);
      fd.append('numero_cedula', numero);
      fetch('../ajax/registro_persona.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(d => {
              if (d.ok && d.existe) {
                  aviso.textContent = '⚠ Esta cédula ya está registrada: ' + d.completo;
                  aviso.style.display = 'block';
                  aviso.className = 'aviso-cedula error';
              } else if (d.ok) {
                  aviso.textContent = '';
                  aviso.style.display = 'none';
                  aviso.className = 'aviso-cedula';
              } else {
                  aviso.textContent = '⚠ ' + (d.mensaje || 'No se pudo verificar la cédula.');
                  aviso.style.display = 'block';
                  aviso.className = 'aviso-cedula error';
              }
          })
          .catch(() => {
              aviso.textContent = '';
              aviso.style.display = 'none';
          });
  }

  document.addEventListener('DOMContentLoaded', function() {
      agregarFilaUnidad();
      toggleJunta();
      cambiarTipoPersona();
      verificarCedulaAJAX();
  });
  </script>
</body>
</html>