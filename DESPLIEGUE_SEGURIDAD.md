# Despliegue del parche de seguridad

Son dos proyectos independientes y cada uno se sube y se prueba por separado:

- **Estadísticas del juego:** solo la carpeta `game/`.
- **Gestor de casos clínicos:** los archivos de la raíz, sin `game/`.

Antes de empezar, respaldar la base de datos y la carpeta actual del servidor.

## A. Estadísticas del juego (`game/`)

1. En el servidor, crear `game/config.local.php` a partir de `game/config.local.example.php`, con las credenciales **actuales** de MySQL. Debe existir antes de subir el código: sin ese archivo las páginas de `game/` responden con error 500.
2. Subir `game/auth_api.php`, `game/dashboard.php`, `game/config_bd.php` y `game/seguridad.php`.
3. Borrar del servidor `game/save_data.php`, `game/register.php` y `game/dashboard - copia.php`.
4. Probar con una cuenta de prueba:
   - **Juego:** iniciar sesión, jugar un reto y buscar `[BD Sincronizada]` con `"success":true` en el log.
   - **Web:** registrar una cuenta nueva, entrar y abrir el panel.
   - **Administrador:** editar y eliminar la cuenta de prueba.
   - **Sin ser administrador:** `admin_delete_user` debe responder 403.
   - **Límite de intentos:** 8 contraseñas erróneas seguidas deben bloquear la cuenta de prueba 15 minutos.

## B. Gestor de casos clínicos (raíz)

1. En el servidor, crear `config.local.php` en la raíz a partir de `config.local.example.php`, con las credenciales **actuales** de MySQL. Debe existir antes de subir el código.
2. Subir `config_bd.php`, `seguridad.php` y los archivos PHP modificados de la raíz.
3. Borrar del servidor `arreglar_admin.php`.
4. Probar con una cuenta de prueba:
   - Iniciar sesión.
   - Como Invitado, `lista_casos.php?eliminar=<id de un caso de prueba>` debe mostrar "Acceso denegado".
   - Cambiar un rol, restablecer una contraseña y eliminar un usuario de prueba.
5. Revisar en la tabla `usuarios` que no existan administradores desconocidos y en los logs si alguien solicitó `arreglar_admin.php`.
6. Cambiar la contraseña de la cuenta `admin`.

## C. Lo único que comparten: la contraseña de MySQL

Ambos proyectos usan la misma base de datos, aunque con tablas distintas. Cambiarla solo cuando A y B estén publicados y probados:

1. Cambiar la contraseña del usuario MySQL.
2. Ponerla en `game/config.local.php` y en `config.local.php`.
3. Comprobar el acceso a los dos proyectos.
4. Hacer privado el repositorio `sergioteovit/casoshv`.

## Límites conocidos de este parche

- `save_progress` todavía acepta guardados sin sesión para no romper las versiones instaladas del juego. Se cerrará cuando el juego envíe un token de sesión.
- El puntaje se sigue calculando en el juego; las validaciones del servidor llegan con las tablas nuevas.
- "Recuperar contraseña" sigue sin enviar correos.
