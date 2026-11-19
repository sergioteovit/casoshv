<?php
// Descarga del juego: solo se muestra con sesión iniciada (Mi panel).

// Versión para descargar: se lee del mismo version.json que consulta el juego para actualizarse,
// así siempre se ofrece la versión publicada. Devuelve null si falta.
function hv_info_descarga()
{
    $descarga = null;
    $carpetaWindows = __DIR__ . '/../builds/windows/';
    $infoVersion = is_readable($carpetaWindows . 'version.json')
        ? json_decode((string)file_get_contents($carpetaWindows . 'version.json'), true)
        : null;
    if (is_array($infoVersion) && !empty($infoVersion['version']) && !empty($infoVersion['download_url'])) {
        $archivoZip = $carpetaWindows . basename((string)parse_url($infoVersion['download_url'], PHP_URL_PATH));
        $bytesZip = is_file($archivoZip) ? (float)filesize($archivoZip) : 0;
        $descarga = [
            'version' => (string)$infoVersion['version'],
            'url'     => (string)$infoVersion['download_url'],
            'tamano'  => $bytesZip > 0 ? number_format($bytesZip / 1073741824, 1) . ' GB' : '',
        ];
    }
    return $descarga;
}

// Sección con el botón, los pasos de instalación y los requisitos
function hv_seccion_descarga($descarga)
{
    if (!$descarga) {
        return;
    }
    ?>
    <section id="descargar" class="mt-5 pt-2">
        <div class="hv-tarjeta overflow-hidden">
            <div class="row g-0">
                <div class="col-lg-5 hv-panel-oscuro p-4 p-md-5 d-flex flex-column">
                    <span class="hv-etiqueta align-self-start"><i class="bi bi-stars"></i> ¿Es tu primera vez?</span>
                    <h2 class="mt-3">Descarga el juego</h2>
                    <p class="mb-4" style="color: rgba(255,255,255,.85)">
                        Instálalo una sola vez. Después, el juego te avisa cuando hay una versión nueva y se actualiza solo.
                    </p>
                    <div>
                        <a href="<?php echo h($descarga['url']); ?>" class="btn btn-oro btn-lg">
                            <i class="bi bi-windows me-2"></i>Descargar para Windows
                        </a>
                    </div>
                    <div class="small mt-3" style="color: rgba(255,255,255,.8)">
                        Versión <?php echo h($descarga['version']); ?><?php echo $descarga['tamano'] !== '' ? ' · ' . h($descarga['tamano']) : ''; ?> · Windows 10 u 11 de 64 bits
                    </div>
                    <img class="hv-figura-descarga d-none d-lg-block mt-auto mx-auto" src="assets/img/ilustracion-acceso.png" alt="">
                </div>

                <div class="col-lg-7 p-4 p-md-5">
                    <h3 class="h5 mb-3"><i class="bi bi-list-ol me-2 text-primary"></i>Instalación paso a paso</h3>
                    <ol class="hv-pasos">
                        <li>
                            <strong>Descarga el archivo</strong> <code>HospitalVirtual-latest.zip</code> con el botón.
                            Es grande porque incluye el paciente virtual con inteligencia artificial; conviene usar una buena conexión.
                        </li>
                        <li>
                            <strong>Extráelo:</strong> haz clic derecho sobre el archivo y elige <em>Extraer todo…</em>.
                            Elige una carpeta fija, por ejemplo <code>C:\HospitalVirtual</code>, de preferencia sin acentos ni espacios en la ruta.
                            No lo abras desde dentro del zip.
                        </li>
                        <li>
                            <strong>Abre el juego:</strong> en esa carpeta, haz doble clic en <code>HospitalVirtualChallenge.exe</code>.
                            Si Windows muestra <em>«Windows protegió su PC»</em>, elige <em>Más información</em> y luego <em>Ejecutar de todas formas</em>.
                        </li>
                        <li>
                            <strong>Inicia sesión</strong> con la misma cuenta que usas en esta página.
                        </li>
                        <li>
                            <strong>Listo.</strong> Tu avance se guarda solo. Cuando haya una versión nueva, el juego te lo avisará al abrirlo:
                            acepta y no cierres la ventana negra que aparece mientras se instala.
                        </li>
                    </ol>

                    <div class="row g-3 mt-2 small">
                        <div class="col-sm-6">
                            <div class="d-flex gap-2">
                                <span class="hv-icono hv-icono-turquesa" style="width:2.2rem;height:2.2rem;font-size:1rem"><i class="bi bi-hdd"></i></span>
                                <div><strong>Espacio libre:</strong> unos 7 GB para descargar y extraer.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex gap-2">
                                <span class="hv-icono hv-icono-oro" style="width:2.2rem;height:2.2rem;font-size:1rem"><i class="bi bi-gpu-card"></i></span>
                                <div><strong>Recomendado:</strong> tarjeta gráfica NVIDIA, para que el paciente virtual responda más rápido.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
}
