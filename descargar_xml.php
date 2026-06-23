<?php
session_start();

// 1. SEGURIDAD: Validar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debes iniciar sesión.");
}

// 2. VALIDAR ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No se especificó el ID del caso.");
}

$idCaso = intval($_GET['id']);

// Configuración de la BD
$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. OBTENER EL CASO
    $stmt = $pdo->prepare("SELECT datos_completos FROM casos_clinicos WHERE id = :id");
    $stmt->execute([':id' => $idCaso]);
    $caso = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caso) {
        die("Error: Caso clínico no encontrado.");
    }

    // Decodificar el JSON a un arreglo de PHP
    $datosArray = json_decode($caso['datos_completos'], true);

    if (!is_array($datosArray)) {
        die("Error: Los datos del caso están corruptos o no tienen un formato válido.");
    }

    // Identificador para el nombre del archivo (si no tiene, usa el ID)
    $identificador = isset($datosArray['identificador']) ? $datosArray['identificador'] : 'ID-' . $idCaso;

    // 4. FUNCIÓN RECURSIVA PARA CONVERTIR ARRAY A XML
    function arrayToXml($array, &$xml_info) {
        foreach($array as $key => $value) {
            // Las etiquetas XML no pueden empezar con números ni tener espacios
            $key = preg_replace('/[^a-z0-9_]/i', '_', $key);
            if (is_numeric(substr($key, 0, 1))) {
                $key = "item_" . $key;
            }

            if (is_array($value)) {
                $subnode = $xml_info->addChild($key);
                arrayToXml($value, $subnode);
            } else {
                // Escapar caracteres especiales con htmlspecialchars
                $xml_info->addChild($key, htmlspecialchars("$value"));
            }
        }
    }

    // Inicializar el documento XML
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><CasoClinico></CasoClinico>');
    
    // Inyectar los datos
    arrayToXml($datosArray, $xml);

    // 5. CONFIGURAR CABECERAS PARA FORZAR DESCARGA
    $nombreArchivo = "Caso_" . $identificador . ".xml";
    
    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Imprimir el XML formateado
    $dom = new DOMDocument("1.0");
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    
    echo $dom->saveXML();
    exit;

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    die("Error en el sistema: " . $e->getMessage());
}
?>