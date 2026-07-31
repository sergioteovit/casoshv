<?php
// 1. Inicializar la sesión.
// Es necesario llamar a session_start() para poder acceder y destruir la sesión actual.
session_start();

// 2. Vaciar todas las variables de sesión.
$_SESSION = array();

// 3. Borrar la cookie de sesión del navegador (Buena práctica de seguridad).
// Si se desea destruir la sesión completamente, borramos también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión en el servidor.
session_destroy();

// 5. Redirigir al usuario de vuelta a la página de inicio/login.
header("Location: index.php");
exit();
?>