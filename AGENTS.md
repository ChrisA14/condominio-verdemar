# Reglas del Proyecto

Proyecto desarrollado en conjunto. Sigue estas reglas SIEMPRE.

## Flujo de trabajo

1. **Todo cambio se hace en local.** Nunca modifiques directamente el repositorio remoto.
2. **Consultar antes de subir.** No hacer push al repositorio sin consultar y obtener aprobación del propietario.
3. **Pulir en local.** Después de `git pull`, leer SIEMPRE el `CHANGELOG.md` para estar al tanto de los cambios subidos por el compañero o el propietario.
4. **Documentar cada cambio.** Todo cambio realizado se registra en el `CHANGELOG.md` bajo la etiqueta de autor **Agent_Chris**.

## Registro de cambios

- Cada modificación debe agregar una entrada en `CHANGELOG.md`.
- Formato de entrada:

```
## [Fecha] - Agent_Chris
- Cambio realizado (archivo/s afectados)
```

## Antes de subir (push)

- Confirmar con el propietario que los cambios están listos.
- Verificar que `CHANGELOG.md` esté actualizado con los cambios.
- Ejecutar pull y resolver conflictos antes de subir.

## Importante

- No compartir API keys en el repositorio.
- No subir archivos de respaldo SQL (`backup_clinico.sql` ya está en `.gitignore`).
- Las credenciales de la base de datos se mantienen locales (no se comparten en el repo).