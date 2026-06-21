<?php
// PON AQUÍ TUS DATOS DE CONEXIÓN REALES
$host = 'localhost'; 
$dbname = 'casoshv';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Generamos el encriptado real y seguro de PHP para 'admin123'
    $passwordPlana = 'admin123';
    $hashValido = password_hash($passwordPlana, PASSWORD_BCRYPT);

    // 2. Actualizamos al usuario administrador en la base de datos
    $stmt = $pdo->prepare("UPDATE usuarios SET password = :password WHERE usuario = 'admin'");
    $stmt->execute([':password' => $hashValido]);

    // 3. Verificamos si funcionó
    if($stmt->rowCount() > 0) {
        echo "<h3 style='color:green;'>¡Éxito!</h3>";
        echo "La contraseña del usuario <b>admin</b> ha sido reparada y encriptada correctamente.<br>";
        echo "La contraseña vuelve a ser: <b>admin123</b><br><br>";
        echo "<a href='login.php'>Haz clic aquí para ir al Login e intentar de nuevo</a>";
    } else {
        echo "<h3 style='color:red;'>Atención</h3>";
        echo "No se encontró al usuario 'admin'. Asegúrate de haber ejecutado el comando INSERT en tu base de datos previamente.";
    }

} catch(PDOException $e) {
    echo "<b>Error de conexión a la Base de Datos:</b> " . $e->getMessage();
}
?>