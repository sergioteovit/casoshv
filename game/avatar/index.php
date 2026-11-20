<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/seguridad.php';
hv_iniciar_sesion();
header('Cache-Control: no-store');

$userId = $_SESSION['UserId'] ?? null;
if (!is_string($userId) || $userId === '') {
    header('Location: ../acceso.php');
    exit;
}

require_once dirname(__DIR__) . '/config_bd.php';

$catalogo = [
    'modelo' => ['Masculino' => 'Masculino', 'Femenino' => 'Femenino', 'Neutro' => 'Neutro'],
    'piel' => ['clara' => 'Clara', 'media' => 'Media', 'morena' => 'Morena', 'oscura' => 'Oscura'],
    'peinado' => ['corto' => 'Corto', 'ondulado' => 'Ondulado', 'largo' => 'Largo', 'rapado' => 'Rapado', 'recogido' => 'Recogido bajo'],
    'cabello' => ['negro' => 'Negro', 'castano' => 'Castaño', 'cobrizo' => 'Cobrizo', 'gris' => 'Gris'],
    'uniforme' => ['verde' => 'Verde', 'azul' => 'Azul', 'vino' => 'Vino', 'blanco' => 'Blanco'],
    'rostro' => ['ninguno' => 'Sin lentes', 'lentes' => 'Lentes'],
    'cuello' => ['ninguno' => 'Sin accesorio', 'estetoscopio' => 'Estetoscopio'],
];
$etiquetas = ['modelo' => 'Modelo de avatar', 'piel' => 'Tono de piel', 'peinado' => 'Peinado', 'cabello' => 'Color de cabello',
    'uniforme' => 'Uniforme', 'rostro' => 'Rostro', 'cuello' => 'Cuello'];
$predeterminado = ['piel' => 'media', 'peinado' => 'corto', 'cabello' => 'negro',
    'uniforme' => 'verde', 'rostro' => 'ninguno', 'cuello' => 'ninguno'];
$avatar = $predeterminado;
$mensaje = '';
$error = '';
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($esPost && !hv_csrf_valido($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('La sesión del formulario caducó. Recarga la página e inténtalo de nuevo.');
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_FOUND_ROWS => true,
    ]);
    $stmt = $conn->prepare('SELECT u.Username, a.Configuracion, p.Progress
        FROM Users u LEFT JOIN WebAvatares a ON a.UserId = u.UserId
        LEFT JOIN PlayerData p ON p.UserId = u.UserId WHERE u.UserId = ?');
    $stmt->execute([$userId]);
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$perfil) {
        header('Location: ../logout.php');
        exit;
    }

    $configuracion = json_decode($perfil['Configuracion'] ?? '', true);
    if (isset($configuracion['accesorio'])) {
        if ($configuracion['accesorio'] === 'lentes') $configuracion['rostro'] = 'lentes';
        if ($configuracion['accesorio'] === 'estetoscopio') $configuracion['cuello'] = 'estetoscopio';
    }
    foreach ($catalogo as $campo => $opciones) {
        $valor = $configuracion[$campo] ?? null;
        if (is_string($valor) && isset($opciones[$valor])) {
            $avatar[$campo] = $valor;
        }
    }

    $progreso = json_decode($perfil['Progress'] ?? '');
    $modelo = $progreso->gender ?? null;
    // Una partida antigua se previsualiza como neutra hasta elegir un modelo.
    $avatar['modelo'] = is_string($modelo) && isset($catalogo['modelo'][$modelo]) ? $modelo : '';
    $guardado = $avatar;
    if ($esPost) {
        foreach ($catalogo as $campo => $opciones) {
            $valor = $_POST[$campo] ?? null;
            if ($campo === 'modelo' && $valor === null) {
                $avatar['modelo'] = '';
                continue;
            }
            if (!is_string($valor) || !isset($opciones[$valor])) {
                $error = 'Revisa las opciones seleccionadas e inténtalo de nuevo.';
                continue;
            }
            $avatar[$campo] = $valor;
        }
        if ($error === '') {
            $conn->beginTransaction();
            // Comparar con lo que se mostró al abrir el formulario, no con una
            // selección más reciente de otra pestaña o plataforma.
            if ($avatar['modelo'] !== '' && $avatar['modelo'] !== ($_POST['modelo_inicial'] ?? '')) {
                $stmt = $conn->prepare("UPDATE PlayerData
                    SET Progress = JSON_SET(COALESCE(NULLIF(Progress, ''), '{}'), '$.gender', ?)
                    WHERE UserId = ? AND (Progress IS NULL OR Progress = ''
                        OR CASE WHEN JSON_VALID(Progress) THEN JSON_TYPE(Progress) = 'OBJECT' ELSE FALSE END)");
                $stmt->execute([$avatar['modelo'], $userId]);
                if (!$stmt->rowCount()) {
                    $conn->rollBack();
                    $error = 'No se pudo actualizar el modelo en la partida. Comprueba el progreso de esta cuenta.';
                }
            }
        }
        if ($error === '') {
            $apariencia = $avatar;
            unset($apariencia['modelo']);
            $stmt = $conn->prepare('INSERT INTO WebAvatares (UserId, Configuracion) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE Configuracion = VALUES(Configuracion)');
            $stmt->execute([$userId, json_encode($apariencia, JSON_THROW_ON_ERROR)]);
            $conn->commit();
            $_SESSION['avatar_guardado'] = true;
            header('Location: ./', true, 303);
            exit;
        }
        http_response_code(422);
    } elseif (!empty($_SESSION['avatar_guardado'])) {
        unset($_SESSION['avatar_guardado']);
        $mensaje = 'Avatar guardado en tu perfil.';
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('[avatar web] Error de base de datos: ' . $e->getCode());
    http_response_code(503);
    if (!isset($perfil) || !$perfil) {
        exit('El editor de avatar no está disponible temporalmente.');
    }
    $error = 'No se pudo guardar el avatar. Inténtalo de nuevo.';
}

function avatar_html(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
require_once dirname(__DIR__) . '/plantilla.php';
ob_start();
hv_encabezado('Vestidor de avatar', 'avance');
$encabezado = ob_get_clean();
// Los enlaces del portal son relativos al directorio game/.
echo str_replace('<head>', '<head><base href="../"><link rel="stylesheet" href="avatar/avatar.css?v=' . HV_VERSION_ESTILOS . '">', $encabezado);
$grupos = ['Apariencia' => ['modelo','piel','peinado','cabello'], 'Uniforme' => ['uniforme'], 'Aditamentos' => ['rostro','cuello']];
$colores = ['clara'=>'#f3c8af','media'=>'#d69a72','morena'=>'#ae714d','oscura'=>'#78503e','negro'=>'#282325',
    'castano'=>'#553428','cobrizo'=>'#9b4931','gris'=>'#a4a09b','verde'=>'#287565','azul'=>'#345f96','vino'=>'#84546e','blanco'=>'#fcfcf7'];
?>
<main class="container vestidor py-4">
    <a href="mi_avance.php" class="small"><i class="bi bi-chevron-left"></i> Mi avance</a>
    <h1 class="mt-2 mb-4">Vestidor de avatar</h1>
    <div class="row g-4">
        <div class="col-lg-7 escenario-col">
            <figure class="escenario">
                <figcaption><i class="bi bi-person-circle"></i> <?= avatar_html($perfil['Username']) ?></figcaption>
                <div id="estudio">
                    <canvas id="retrato" width="660" height="660" role="img" aria-label="Retrato del avatar"></canvas>
                </div>
                <div class="camara" id="camara" hidden>
                    <button type="button" data-vista="izquierda" aria-label="Girar a la izquierda">↶</button>
                    <button type="button" data-vista="zoom">Ver rostro</button>
                    <button type="button" data-vista="derecha" aria-label="Girar a la derecha">↷</button>
                </div>
            </figure>
            <div id="ensayo" class="ensayo" hidden>
                <label for="movimiento">Probar movimiento</label>
                <select id="movimiento" class="form-select form-select-sm">
                    <option value="reposo">En reposo</option>
                    <option value="caminar">Caminar</option>
                    <option value="correr">Correr</option>
                    <option value="saltar">Saltar</option>
                    <option value="alegria">Alegría</option>
                    <option value="tristeza">Tristeza</option>
                </select>
                <button type="button" id="pausa" class="btn btn-outline-secondary btn-sm" disabled>Pausar</button>
            </div>
        </div>
        <div class="col-lg-5">
            <form id="configuracion" method="post" action="avatar/" data-guardado="<?= avatar_html(json_encode($guardado)) ?>">
                <input type="hidden" name="csrf_token" value="<?= avatar_html(hv_token_csrf()) ?>">
                <input type="hidden" name="modelo_inicial" value="<?= avatar_html($guardado['modelo']) ?>">
                <div class="secciones" hidden>
                <?php foreach ($grupos as $titulo => $campos): ?>
                    <button type="button" data-seccion="<?= $titulo ?>" aria-controls="grupo-<?= $titulo ?>"><?= $titulo ?></button>
                <?php endforeach; ?>
                </div>
                <?php foreach ($grupos as $titulo => $campos): ?>
                <section id="grupo-<?= $titulo ?>" data-grupo="<?= $titulo ?>">
                    <h2 class="h5"><?= $titulo ?></h2>
                    <?php foreach ($campos as $campo): ?>
                    <fieldset>
                        <legend><?= $etiquetas[$campo] ?></legend>
                        <div class="opciones">
                        <?php foreach ($catalogo[$campo] as $clave => $texto): ?>
                            <label>
                                <input type="radio" name="<?= $campo ?>" value="<?= $clave ?>" <?= $avatar[$campo] === $clave ? 'checked' : '' ?> <?= $campo === 'modelo' ? '' : 'required' ?>>
                                <span><?php if ($campo === 'modelo'): ?><img src="avatar/modelos/<?= $clave ?>.png" width="70" height="70" alt=""><?php elseif (isset($colores[$clave])): ?><b style="--muestra:<?= $colores[$clave] ?>"></b><?php endif; ?><?= $texto ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($campo === 'modelo' && $avatar['modelo'] === ''): ?>
                        <small>Vista previa neutra. Elige un modelo para guardarlo en tu partida.</small>
                        <?php endif; ?>
                    </fieldset>
                    <?php endforeach; ?>
                    <?php if ($titulo === 'Aditamentos'): ?>
                        <details class="bloqueo"><summary><i class="bi bi-lock"></i> Credencial de brigada</summary>
                            Recompensa prevista para el primer reto comunitario. Disponible en una etapa posterior del juego.
                        </details>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>
                <footer class="acciones">
                    <span id="estado" role="status"><?= avatar_html($mensaje) ?></span>
                    <?php if ($error): ?><p class="text-danger" role="alert"><?= avatar_html($error) ?></p><?php endif; ?>
                    <button class="btn btn-oro" type="submit">Guardar avatar</button>
                    <button id="restaurar" class="btn btn-link" type="button" hidden>Restaurar</button>
                </footer>
            </form>
        </div>
    </div>
</main>
<script src="avatar/avatar.js" defer></script>
<script src="avatar/three.min.js" defer></script>
<script src="avatar/estudio.js" defer></script>
<?php hv_pie(); ?>
</body></html>
