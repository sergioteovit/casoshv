<?php
// Ver un caso clínico (solo lectura): el formulario está en formulario_caso.php (modo 'ver').
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: lista_casos.php");
    exit;
}
hv_bloquear_invitado();

$rolUsuario = $_SESSION['rol'];
$modo = 'ver';
require __DIR__ . '/formulario_caso.php';
