<?php
// Nuevo caso clínico: el formulario está en formulario_caso.php (modo 'nuevo').
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// Los invitados no pueden crear casos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] === 'Invitado') {
    header("Location: lista_casos.php");
    exit;
}

$rolUsuario = $_SESSION['rol'];
$modo = 'nuevo';
require __DIR__ . '/formulario_caso.php';
