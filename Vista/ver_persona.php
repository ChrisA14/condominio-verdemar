<?php
include("../Controlador/ver_persona.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalles de Persona | Sistema de Gestión de Condominio</title>
  <link rel="stylesheet" href="../Estilo/global.css">
  <link rel="stylesheet" href="../assets/css/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../Estilo/verdetalles.css">
</head>
<body>
  <div class="container">
    <a href="consulta_personas.php" class="back-button"><i class="bi bi-arrow-left"></i> Volver a Consulta</a>

    <?php if (!$persona): ?>
      <div class="mensaje error" style="margin-top:18px;">❌ Persona no encontrada</div>
    <?php else: ?>

    <div class="header">
      <h2><i class="bi bi-person"></i> <?php echo htmlspecialchars($persona['nombre']); ?></h2>
    </div>

    <div class="detail-box">
      <div class="section">
        <h3><i class="bi bi-clipboard-data"></i> Información Personal</h3>
        <div class="detail-grid">
          <div class="detail-item"><label>Cédula:</label><span><?php echo htmlspecialchars($persona['cedula']); ?></span></div>
          <div class="detail-item"><label>Nombre:</label><span><?php echo htmlspecialchars($persona['nombre']); ?></span></div>
          <div class="detail-item"><label>Teléfono:</label><span><?php echo htmlspecialchars($persona['telefono'] ?? '—'); ?></span></div>
          <div class="detail-item"><label>Correo:</label><span><?php echo htmlspecialchars($persona['correo'] ?? '—'); ?></span></div>
          <div class="detail-item"><label>Dirección:</label><span><?php echo htmlspecialchars($persona['direccion'] ?? '—'); ?></span></div>
          <div class="detail-item"><label>Fecha de Nacimiento:</label><span><?php echo $persona['fecha_nacimiento'] ? date('d/m/Y', strtotime($persona['fecha_nacimiento'])) : '—'; ?></span></div>
          <div class="detail-item"><label>Registrado:</label><span><?php echo date('d/m/Y H:i', strtotime($persona['fecha_creacion'])); ?></span></div>
        </div>
      </div>

      <div class="section">
        <h3><i class="bi bi-building"></i> Unidades</h3>
        <?php if (empty($tenencias)): ?>
          <p style="color:var(--gris-500);">Sin unidades asignadas.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Unidad</th><th>Tipo</th><th>Rol</th><th>Ingreso</th><th>Estado</th></tr></thead>
              <tbody>
                <?php foreach ($tenencias as $t): ?>
                <tr>
                  <td data-label="Unidad"><strong><?php echo htmlspecialchars($t['numero']); ?></strong></td>
                  <td data-label="Tipo"><?php echo TIPOS_UNIDAD[$t['tipo']] ?? $t['tipo']; ?></td>
                  <td data-label="Rol">
                    <span class="badge <?php echo ($t['rol'] == 'propietario') ? 'badge-success' : (($t['rol'] == 'inquilino') ? 'badge-warning' : 'badge-neutral'); ?>">
                      <?php echo ucfirst($t['rol']); ?>
                    </span>
                  </td>
                  <td data-label="Ingreso"><?php echo $t['fecha_inicio'] ? date('d/m/Y', strtotime($t['fecha_inicio'])) : '—'; ?></td>
                  <td data-label="Estado"><span class="badge <?php echo $t['estado'] == 'activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($t['estado']); ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($junta): ?>
      <div class="section">
        <h3><i class="bi bi-shield-check"></i> Junta de Condominio</h3>
        <div class="detail-grid">
          <div class="detail-item"><label>Cargo:</label><span class="badge badge-gold"><?php echo CARGOS_JUNTA[$junta['cargo']] ?? $junta['cargo']; ?></span></div>
          <div class="detail-item"><label>Designación:</label><span><?php echo $junta['fecha_designacion'] ? date('d/m/Y', strtotime($junta['fecha_designacion'])) : '—'; ?></span></div>
          <div class="detail-item"><label>Periodo:</label><span><?php echo ($junta['periodo_inicio'] ? date('d/m/Y', strtotime($junta['periodo_inicio'])) : '—') . ' → ' . ($junta['periodo_fin'] ? date('d/m/Y', strtotime($junta['periodo_fin'])) : '—'); ?></span></div>
          <div class="detail-item"><label>Estado:</label><span class="badge <?php echo $junta['estado'] == 'activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($junta['estado']); ?></span></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="section">
        <h3><i class="bi bi-cash-stack"></i> Resumen de Cuenta</h3>
        <div class="detail-grid">
          <div class="detail-item"><label>Total Emitido:</label><span>$<?php echo number_format($resumen['total_emitido'], 2); ?></span></div>
          <div class="detail-item"><label>Total Pagado:</label><span>$<?php echo number_format($resumen['total_pagado'], 2); ?></span></div>
          <div class="detail-item"><label>Saldo Pendiente:</label><span style="color:<?php echo $resumen['saldo'] > 0 ? 'var(--rojo)' : 'var(--verde)'; ?>;font-weight:700;">$<?php echo number_format($resumen['saldo'], 2); ?></span></div>
        </div>
      </div>

      <div class="action-buttons">
        <a href="editar_persona.php?cedula=<?php echo urlencode($persona['cedula']); ?>" class="btn btn-edit"><i class="bi bi-pencil"></i> Editar Persona</a>
        <a href="consulta_personas.php" class="btn btn-back"><i class="bi bi-arrow-left"></i> Volver a Consulta</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>