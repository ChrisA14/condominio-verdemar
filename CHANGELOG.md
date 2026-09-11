# Registro de Cambios

Todos los cambios del proyecto se documentan aquí. Cada entrada indica la fecha, el autor y una descripción del cambio.

## [2026-09-10] - Agent_Chris
- Creación del archivo `AGENTS.md` con las reglas de trabajo del proyecto (cambios locales, consultar antes de subir, documentación de cambios).
- Creación de este archivo `CHANGELOG.md` como registro oficial de cambios.
- Inicialización del repositorio Git y primer commit del proyecto.
- Subida inicial del código al repositorio remoto en GitHub.

## [2026-09-10] - Menabagle (bajado del repositorio)
- Actualización del local a la versión remota (el historial fue reemplazado por push forzado de `Menabagle`).
- Nuevos archivos: `Controlador/gestion_cobros.php`, `Vista/gestion_cobros.php`, `Controlador/verificar_pagos.php`, `Vista/verificar_pagos.php`, `Controlador/descargar_comprobante.php`, `Modelo/pagos.php`, `Estilo/reporte_deudores.css`, `index.php` (raíz).
- Novedades: saldo a favor, cobros especiales, verificación de comprobantes de pago, descarga de comprobante, tasa BCV (dólar) y conversión a bolívares (`Modelo/config.php`).
- `backup_clinico.sql` excluido del repo; `.gitignore` actualizado (sube exclusión de `uploads/comprobantes/*`).
- Importación de `condominio.sql` en la base `Proyecto` (la base no existía previamente y fue creada). Esquema nuevo con tablas `comprobantes_pago` y `saldos`, y columnas nuevas en `pagos`.

## [2026-09-11] - Agent_Chris
- En `Vista/ver_persona.php`: el `h2` del header (nombre de persona) se envolvió en una pequeña tarjeta clara (`header-title-card`) para que no se solape con el fondo azul.
- En `Estilo/verdetalles.css`: nueva clase `.header-title-card` (fondo blanco translúcido, borde, radio, sombra y padding).
- Correcciones de seguridad en `registro_persona.php` (Vista y Controlador):
  - XSS: mensajes flash (`mensaje`/`tipo_mensaje`) ahora se escapan con `htmlspecialchars` + `nl2br` en la Vista.
  - CSRF: token en sesión, campo hidden `csrf_token` en el formulario y verificación con `hash_equals` en el Controlador. El token se renueva tras cada registro exitoso.
  - Validación server-side: `rol_unidad` contra `ROLES_TENENCIA`, `cargo_junta` contra `CARGOS_JUNTA`, `unidad_id` como entero positivo y `fecha_nacimiento` con `checkdate` (rechaza futuras).
  - Sin fuga de información: los errores de MySQL ya no se muestran al usuario (se registran en `error_log` y se muestra mensaje genérico); mensaje de cédula duplicada ya no refleja el valor ingresado.
  - `json_encode` con flags `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` para prevenir ruptura del bloque `<script>`.

## [2026-09-11] - Agent_Chris (cédula AJAX)
- `Modelo/config.php`: nueva constante `TIPOS_CEDULA` (V→Venezolano, E→Extranjero).
- `Vista/registro_persona.php`: campo cédula reemplazado por selector de tipo V/E + input numérico (solo dígitos). Verificación AJAX en tiempo real que muestra si la cédula ya está registrada.
- `Controlador/registro_persona.php`: concatena `tipo + '-' + numero`, valida tipo contra `TIPOS_CEDULA` y numero como dígitos (5–18) server-side.
- Nueva carpeta `ajax/` con `ajax/registro_persona.php`: endpoint AJAX con verificación de duplicidad, sanitización server-side y protección CSRF. Usando patrón de switch por `accion` para futuras acciones del módulo.
- `Estilo/global.css`: clase `.aviso-cedula` para el mensaje de advertencia de duplicidad.
- `Vista/registro_persona.php`: campo Teléfono restringido a solo números (filtro `\D` client-side + `inputmode= numeric` + `pattern` 7–15 dígitos). Campo Nombre restringido a letras/espacios (con tildes, ñ, apóstrofo y punto; filtro client-side).
- `Controlador/registro_persona.php`: sanitización server-side de nombre (solo caracteres permitidos, max 100) y teléfono (solo dígitos, max 15); validación de formato para evitar salto de la vista vía POST directo.
- `Modelo/config.php`: nuevos tipos de cédula `J` (Jurídico) y `G` (Gobierno) vía `TIPOS_CEDULA`; nueva constante `TIPOS_NOMBRE_CON_NUMEROS` (J, G).
- `Vista/registro_persona.php`: función `sanearNombre()` que permite números en el nombre según el tipo de cédula seleccionado (J/G) usando la constante PHP.
- `Controlador/registro_persona.php`: el nombre (razón social) admite números solo cuando el tipo es J o G; mensajes de error adaptados.
- `Modelo/config.php` + BD (`Proyecto`): columna `rep_legal_*` (nombre, cédula, correo, teléfono, fecha de nacimiento) agregadas a `personas` para el representante legal de tipos J/G; nueva constante `TIPOS_CEDULA_REP_LEGAL` (solo V/E); `condominio.sql` actualizado (CREATE e INSERT con lista de columnas).
- `Controlador/registro_persona.php`: al registrar tipo J/G se oculta fecha de nacimiento (queda NULL) y se procesan/validan los campos opcionales del representante legal (solo V/E, saneados igual que los principales).
- `Vista/registro_persona.php`: función `cambiarTipoPersona()` — oculta fecha de nacimiento y muestra fieldset "Representante Legal" cuando el tipo es J/G; etiqueta cambia a "Razón Social"; función `sanearNombreRepLegal()`.
- `Controlador/editar_persona.php`: detecta J/G por prefijo de cédula, saneamiento del nombre según tipo, procesa representante legal y lo incluye en el UPDATE.
- `Vista/editar_persona.php`: oculta fecha de nacimiento y muestra fieldset "Representante Legal" precargado para J/G; función `sanearNombreEditar()`.
- `Controlador/ver_persona.php` y `Vista/ver_persona.php`: SELECT y sección "Representante Legal" (solo J/G) en el detalle; etiqueta "Razón Social".
- Etiquetas dinámicas en el principal al seleccionar J/G: "Tipo de Cédula"→"Tipo de RIF" y "Número de Cédula"→"Número de RIF" (`Vista/registro_persona.php`); en editar/detalle "Cédula"→"Rif" (`Vista/editar_persona.php`, `Vista/ver_persona.php`).
- Selector de unidades en "Unidades Asignadas" (`Vista/registro_persona.php`, `Vista/editar_persona.php`): ahora usa 2 selectores dependientes **Piso** (orden numérico 1–19) y **Apto/Letra** (A–F) con confirmación "Unidad: 13-C"; `unidad_id[]` se envía oculto y `rol_unidad[]` queda alineado por fila. Los controladores incluyen `u.piso` y ordenan por `CAST(u.piso AS UNSIGNED), u.numero`; `editar_persona` precarga piso/letra desde las tenencias (`u.piso`).