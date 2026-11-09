<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();
header('Content-Type: application/json');

require_once __DIR__ . '/config_bd.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Error de base de datos."]);
    exit();
}

// El rol se consulta en la base (no en la sesión) para que un cambio de rol aplique de inmediato.
function exigirAdministrador($conn) {
    $stmt = $conn->prepare("
        SELECT r.RoleName
        FROM Users u
        JOIN Roles r ON u.RoleId = r.RoleId
        WHERE u.UserId = :userId
    ");
    $stmt->execute([':userId' => $_SESSION['UserId'] ?? '']);

    if ($stmt->fetchColumn() !== 'Administrator' || !hv_csrf_valido($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Acceso no autorizado."]);
        exit();
    }
}

$action = $_POST['action'] ?? '';

// === LOGIN ===
if ($action === 'login') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Límite de intentos fallidos. Se cuenta por cuenta + IP (y un tope mayor por cuenta)
    // porque un salón completo puede compartir la misma IP.
    $claveCuenta = 'juego|' . strtolower($email);
    $claveCuentaIp = $claveCuenta . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (hv_limite_excedido($claveCuentaIp, 8, 900) || hv_limite_excedido($claveCuenta, 30, 900)) {
        echo json_encode(["success" => false, "error" => "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo."]);
        exit();
    }

    // 1. Obtener los datos del usuario
    $stmt = $conn->prepare("
        SELECT 
            u.UserId, 
            u.Username, 
            u.PasswordHash, 
            r.RoleName, 
            p.Progress, 
            p.Statistics,
            p.CasesDataBase
        FROM Users u 
        JOIN Roles r ON u.RoleId = r.RoleId
        JOIN PlayerData p ON u.UserId = p.UserId 
        WHERE u.Email = :email
    ");
    
    $stmt->execute([':email' => $email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Comprobar la contraseña
    if ($userRow && password_verify($password, $userRow['PasswordHash'])) {
        
        // --------------------------------------------------------------------
        // 3. NUEVO: Actualizar la columna LastLogin en la base de datos
        // --------------------------------------------------------------------
        $updateLoginStmt = $conn->prepare("
            UPDATE Users 
            SET LastLogin = CURRENT_TIMESTAMP 
            WHERE UserId = :userId
        ");
        $updateLoginStmt->execute([':userId' => $userRow['UserId']]);
        // --------------------------------------------------------------------

        hv_limite_reiniciar($claveCuentaIp);
        hv_limite_reiniciar($claveCuenta);
        session_regenerate_id(true);

        // 4. Guardar información en la sesión de PHP
        $_SESSION['UserId'] = $userRow['UserId'];
        $_SESSION['Username'] = $userRow['Username'];
        $_SESSION['Role'] = $userRow['RoleName'];
        $_SESSION['Progress'] = $userRow['Progress'];
        $_SESSION['Statistics'] = $userRow['Statistics'];
        $_SESSION['CasesDataBase'] = $userRow['CasesDataBase'];

        // El juego guarda el avance del usuario anterior después de iniciar sesión con otro,
        // así que se recuerdan las últimas cuentas autenticadas en esta sesión.
        $usuariosAutenticados = $_SESSION['UsuariosAutenticados'] ?? [];
        $usuariosAutenticados[] = (string)$userRow['UserId'];
        $_SESSION['UsuariosAutenticados'] = array_slice(array_values(array_unique($usuariosAutenticados)), -5);
        
        // echo json_encode([
        //    "success" => true, 
        //    "message" => "Conectando...",
        //    "role" => $userRow['RoleName']
        // ]);
        
        // 2. Obtener Progress y Statistics asegurando que sean CADENAS (string)
        // Si la BD devuelve NULL o está vacío, asignamos una cadena JSON por defecto "{}"
        $rawProgress   = !empty($userRow['Progress'])   ? (string)$userRow['Progress']   : '{}';
        $rawStatistics = !empty($userRow['Statistics']) ? (string)$userRow['Statistics'] : '{}';
        $rawCasesDataBase = !empty($userRow['CasesDataBase']) ? (string)$userRow['CasesDataBase'] : '{}';
        
        // Si en PHP por alguna razón $rawProgress fuera un array, lo convertimos a string con json_encode
        if (is_array($userRow['Progress']) || is_object($userRow['Progress'])) {
            $rawProgress = json_encode($userRow['Progress']);
        }
        if (is_array($userRow['Statistics']) || is_object($userRow['Statistics'])) {
            $rawStatistics = json_encode($userRow['Statistics']);
        }
        if (is_array($userRow['CasesDataBase']) || is_object($userRow['CasesDataBase'])) {
            $rawCasesDataBase = json_encode($userRow['CasesDataBase']);
        }
        
        echo json_encode([
            "success"    => true, 
            "message"    => "Conexión a la base de datos correcta",
            "user_id"    => (string)$userRow['UserId'], // <-- Castear explícitamente a string
            "username"   => (string)$userRow['Username'],
            "role"       => (string)($userRow['RoleName'] ?? 'Player'),
            "progress"   => $rawProgress,
            "statistics" => $rawStatistics,
            "casesDataBase" => $rawCasesDataBase
        ]);
        exit();
    } else {
        hv_limite_registrar_fallo($claveCuentaIp, 900);
        hv_limite_registrar_fallo($claveCuenta, 900);
        echo json_encode(["success" => false, "error" => "Credenciales incorrectas."]);
    }
}

// === REGISTRO ===
elseif ($action === 'register') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[\p{L}\p{N} ._-]{3,50}$/u', $username)) {
        echo json_encode(["success" => false, "error" => "El nombre de usuario debe tener de 3 a 50 caracteres: letras, números, espacios, punto, guion o guion bajo."]);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "El correo electrónico no es válido."]);
        exit();
    }
    if (strlen($password) < 8) {
        echo json_encode(["success" => false, "error" => "La contraseña debe tener al menos 8 caracteres."]);
        exit();
    }

    // 1. Validación de contraseñas en el backend
    if ($password !== $password_confirm) {
        echo json_encode(["success" => false, "error" => "Las contraseñas no coinciden."]);
        exit();
    }

    // 2. Verificar si el Username o Email ya existen
    $stmtCheck = $conn->prepare("SELECT Username, Email FROM Users WHERE Username = :username OR Email = :email");
    $stmtCheck->execute([':username' => $username, ':email' => $email]);
    $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // Verificar exactamente qué es lo que está duplicado para dar un mensaje claro
        if (strtolower($existingUser['Username']) === strtolower($username)) {
            echo json_encode(["success" => false, "error" => "El nombre de usuario ya está en uso. Por favor elige otro."]);
        } else {
            echo json_encode(["success" => false, "error" => "Este correo electrónico ya está registrado. Intenta iniciar sesión."]);
        }
        exit(); // Detener la ejecución
    }

    // 3. Si todo está correcto, procedemos a crear el usuario
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userId = bin2hex(random_bytes(16)); // UUID simplificado

    // Datos por defecto para el juego
    $defaultPrefs = json_encode(["audio" => ["masterVolume" => 1.0], "ui" => ["language" => "es-MX"]]);
    $defaultStats = json_encode(["totalPoints" => 0, "totalStars" => 0, "bronzeStars" => 0]);
    $defaultProg = json_encode(["playerName" => "Player", "gender" => "Neutro", "playerLevel" => 0, "Points" => 0]);
    $defaultCDB = '{}'; // Debe ser un objeto: JsonUtility de Unity no acepta un arreglo en la raíz

    try {
        $conn->beginTransaction();
        
        // Insertar usuario
        $stmtUser = $conn->prepare("INSERT INTO Users (UserId, Username, Email, PasswordHash) VALUES (?, ?, ?, ?)");
        $stmtUser->execute([$userId, $username, $email, $hashedPassword]);

        // Insertar datos iniciales del juego
        $stmtData = $conn->prepare("INSERT INTO PlayerData (UserId, Preferences, Statistics, Progress, CasesDataBase) VALUES (?, ?, ?, ?, ?)");
        $stmtData->execute([$userId, $defaultPrefs, $defaultStats, $defaultProg, $defaultCDB]);

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Cuenta creada exitosamente. Redirigiendo al login..."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["success" => false, "error" => "Hubo un problema al crear la cuenta. Inténtalo de nuevo más tarde."]);
    }
}

// === RECUPERAR CONTRASEÑA ===
elseif ($action === 'recover') {
    // Aquí iría la lógica para enviar un email con la función mail() de PHP o PHPMailer.
    echo json_encode(["success" => true, "message" => "Si el correo existe, recibirás un enlace."]);
}

// === ADMINISTRACIÓN: ACTUALIZAR USUARIO Y RESETEAR CONTRASEÑA ===
elseif ($action === 'admin_update_user') {
    // 1. Verificación de Seguridad: Solo administradores pueden ejecutar esto
    exigirAdministrador($conn);

    $targetUserId = $_POST['user_id'] ?? '';
    $newUsername  = trim($_POST['username'] ?? '');
    $newEmail     = trim($_POST['email'] ?? '');
    $newRoleId    = intval($_POST['role_id'] ?? 1);
    $newPassword  = $_POST['new_password'] ?? '';

    if (empty($targetUserId) || empty($newUsername) || empty($newEmail)) {
        echo json_encode(["success" => false, "error" => "Todos los campos obligatorios deben estar completos."]);
        exit();
    }

    $stmtRol = $conn->prepare("SELECT RoleName FROM Roles WHERE RoleId = :roleId");
    $stmtRol->execute([':roleId' => $newRoleId]);
    $nuevoRol = $stmtRol->fetchColumn();

    if ($nuevoRol === false) {
        echo json_encode(["success" => false, "error" => "El rol seleccionado no existe."]);
        exit();
    }
    if ($targetUserId === $_SESSION['UserId'] && $nuevoRol !== 'Administrator') {
        echo json_encode(["success" => false, "error" => "No puedes quitarte a ti mismo el rol de administrador."]);
        exit();
    }
    if (!empty($newPassword) && strlen($newPassword) < 8) {
        echo json_encode(["success" => false, "error" => "La contraseña debe tener al menos 8 caracteres."]);
        exit();
    }

    // 2. Comprobar que el correo o nombre de usuario no pertenezcan a OTRO usuario diferente
    $stmtCheck = $conn->prepare("
        SELECT UserId, Username, Email 
        FROM Users 
        WHERE (Username = :username OR Email = :email) AND UserId != :userId
    ");
    $stmtCheck->execute([
        ':username' => $newUsername,
        ':email'    => $newEmail,
        ':userId'   => $targetUserId
    ]);
    
    if ($stmtCheck->fetch()) {
        echo json_encode(["success" => false, "error" => "El nombre de usuario o correo ya está en uso por otra cuenta."]);
        exit();
    }

    try {
        // 3. Si se ingresó una nueva contraseña, la hasheamos y la incluimos en la actualización
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE Users 
                          SET Username = :username, Email = :email, RoleId = :roleId, PasswordHash = :pass 
                          WHERE UserId = :userId";
            $params = [
                ':username' => $newUsername,
                ':email'    => $newEmail,
                ':roleId'   => $newRoleId,
                ':pass'     => $hashedPassword,
                ':userId'   => $targetUserId
            ];
        } else {
            // Si el campo de contraseña quedó vacío, se conserva la contraseña anterior
            $updateSql = "UPDATE Users 
                          SET Username = :username, Email = :email, RoleId = :roleId 
                          WHERE UserId = :userId";
            $params = [
                ':username' => $newUsername,
                ':email'    => $newEmail,
                ':roleId'   => $newRoleId,
                ':userId'   => $targetUserId
            ];
        }

        $stmtUpdate = $conn->prepare($updateSql);
        $stmtUpdate->execute($params);

        echo json_encode(["success" => true, "message" => "Usuario actualizado exitosamente."]);

    } catch (PDOException $e) {
        echo json_encode(["success" => false, "error" => "Error al actualizar en la base de datos."]);
    }
}

// ELIMINAR REGISTRO DE USUARIO
elseif ($action === 'admin_delete_user') {
    exigirAdministrador($conn);

    $userIdToDelete = $_POST['user_id'] ?? null;

    if (!$userIdToDelete) {
        echo json_encode(["success" => false, "error" => "ID de usuario no proporcionado."]);
        exit();
    }

    // Opcional: Evitar que el administrador se elimine a sí mismo
    if ($userIdToDelete == $_SESSION['UserId']) {
        echo json_encode(["success" => false, "error" => "No puedes eliminar tu propia cuenta mientras estás conectado."]);
        exit();
    }

    // Eliminar primero los datos relacionados en PlayerData si no hay ON DELETE CASCADE en MySQL
    $stmtData = $conn->prepare("DELETE FROM PlayerData WHERE UserId = :userId");
    $stmtData->execute([':userId' => $userIdToDelete]);

    // Eliminar el usuario
    $stmtUser = $conn->prepare("DELETE FROM Users WHERE UserId = :userId");
    $stmtUser->execute([':userId' => $userIdToDelete]);

    echo json_encode(["success" => true, "message" => "Usuario eliminado correctamente."]);
    exit();
}

// === GUARDAR PROGRESO DEL JUEGO DESDE UNITY ===
elseif ($action === 'save_progress') {
    $userId     = $_POST['user_id'] ?? '';
    $progress   = $_POST['progress'] ?? null;
    $statistics = $_POST['statistics'] ?? null;
    // Si no llega (versiones antiguas del juego) se conserva el valor guardado
    $casesDataBase = $_POST['casesDataBase'] ?? null;

    // Un guardado incompleto o dañado no debe borrar la partida
    $jsonValido = function ($valor) {
        return is_string($valor) && is_array(json_decode($valor, true));
    };
    if (!$jsonValido($progress) || !$jsonValido($statistics) || ($casesDataBase !== null && !$jsonValido($casesDataBase))) {
        echo json_encode(["success" => false, "error" => "Datos de guardado inválidos."]);
        exit();
    }

    // TEMPORAL: sin sesión se sigue aceptando para no romper las versiones ya instaladas del juego.
    // Hay que exigir un token cuando el juego lo envíe.
    if (isset($_SESSION['UsuariosAutenticados']) && !in_array($userId, $_SESSION['UsuariosAutenticados'], true)) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "La sesión no corresponde a este usuario."]);
        exit();
    }

    if (empty($userId)) {
        echo json_encode(["success" => false, "error" => "ID de usuario requerido."]);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            UPDATE PlayerData 
            SET Progress = :progress, 
                Statistics = :statistics,
                CasesDataBase = COALESCE(:casesDataBase, CasesDataBase)
            WHERE UserId = :userId
        ");
        
        $stmt->execute([
            ':progress'   => $progress,
            ':statistics' => $statistics,
            ':casesDataBase' => $casesDataBase,
            ':userId'     => $userId
        ]);

        echo json_encode(["success" => true, "message" => "Progreso guardado correctamente."]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "error" => "Error al guardar en la base de datos."]);
    }
}

?>