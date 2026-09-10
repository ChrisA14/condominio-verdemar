<?php
include("../Controlador/registro_unidad.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Unidad | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <h2><i class="bi bi-building-add"></i> Registro de Unidad</h2>
      <a href="consulta_unidades.php" class="btn btn-light-header"><i class="bi bi-arrow-left"></i> Volver a Unidades</a>
    </div>

    <?php if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])): ?>
      <div class="mensaje <?php echo isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : ''; ?>">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
      </div>
    <?php endif; ?>

    <div class="form-container">
      <form action="registro_unidad.php" method="post" autocomplete="off">
        <fieldset class="form-section">
          <legend><i class="bi bi-house-fill"></i> Datos de la Unidad</legend>
          <div class="form-row">
            <div class="form-group">
              <label for="torre">Torre: <span class="requerido">*</span></label>
              <input type="text" id="torre" name="torre" required maxlength="10"
                     placeholder="Ej: A, B, C o PB"
                     value="<?php echo isset($_POST['torre']) ? htmlspecialchars($_POST['torre']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="numero">Número: <span class="requerido">*</span></label>
              <input type="text" id="numero" name="numero" required maxlength="10"
                     placeholder="Ej: 101, PH-1, E-01"
                     value="<?php echo isset($_POST['numero']) ? htmlspecialchars($_POST['numero']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="piso">Piso:</label>
              <input type="text" id="piso" name="piso" maxlength="10"
                     value="<?php echo isset($_POST['piso']) ? htmlspecialchars($_POST['piso']) : ''; ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="tipo">Tipo de unidad:</label>
              <select id="tipo" name="tipo">
                <?php foreach (TIPOS_UNIDAD as $k => $v): ?>
                  <option value="<?php echo $k; ?>" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="estado">Estado:</label>
              <select id="estado" name="estado">
                <option value="activa">Activa</option>
                <option value="inactiva">Inactiva</option>
              </select>
            </div>
          </div>
        </fieldset>

        <div class="form-actions">
          <button type="submit" name="guardar-btn" class="btn btn-success"><i class="bi bi-save"></i> Guardar Unidad</button>
          <button type="reset" class="btn btn-secondary"><i class="bi bi-eraser"></i> Limpiar</button>
          <a href="consulta_unidades.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Regresar</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>