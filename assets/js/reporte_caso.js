// Reporte de un caso clínico para imprimir o guardar como PDF.
// Se carga en reporte_caso.php después del formulario (modo 'ver'): cuando el formulario terminó de
// cargar el caso, este script recorre sus pasos y arma el reporte con las mismas etiquetas, en el mismo
// orden y sin los campos vacíos. Así cualquier campo que se agregue al formulario aparece en el reporte.
// Datos de PHP en window.HV_REPORTE: id, identificador, titulo, fechaRegistro, generadoPor,
// fechaGeneracion, incrustado y adjuntos [{nombre, imagen (data URI) | null, nota}].
(function () {
    'use strict';

    const R = window.HV_REPORTE || {};
    const PALABRAS_EN_MAYUSCULAS = ['SOAP', 'IA', 'ART', 'ART-R', 'ECG', 'EEG', 'EMG', 'IMC', 'TA', 'FC', 'FR', 'LCR', 'RM', 'TAC', 'PCR', 'UNAM', 'FM'];
    const NOMBRES_EN_PORTADA = ['identificador', 'titulo_caso'];

    // ---------------------------------------------------------------- utilidades
    function crear(etiqueta, clase, texto) {
        const e = document.createElement(etiqueta);
        if (clase) e.className = clase;
        if (texto !== undefined && texto !== null) e.textContent = texto;
        return e;
    }

    function textoDe(elemento) {
        return elemento ? (elemento.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    // "RESUMEN DEL CASO:" → "Resumen del caso"; respeta siglas como SOAP o IA
    function limpiarEtiqueta(texto) {
        let t = String(texto || '').replace(/\s+/g, ' ').replace(/[:：]\s*$/, '').trim()
            .replace(/\s*en texto libre\s*\(formato anterior\)/i, '')
            .replace(/\s*\(solo si el caso lo requiere\)/i, '');
        if (t !== '' && t === t.toUpperCase() && /[A-ZÁÉÍÓÚÑ]/.test(t)) {
            t = t.toLowerCase().replace(/[a-záéíóúñü][^\s()]*/gi, palabra => {
                const mayus = palabra.toUpperCase();
                return PALABRAS_EN_MAYUSCULAS.includes(mayus.replace(/[^A-Z-]/g, '')) ? mayus : palabra;
            });
            t = t.charAt(0).toUpperCase() + t.slice(1);
        }
        return t;
    }

    function etiquetaPorId(form, id) {
        return id ? form.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
    }

    // Etiqueta de un campo: la del apartado, la asociada por "for" o la etiqueta más cercana hacia arriba
    function etiquetaElemento(form, control) {
        if (control.type !== 'checkbox' && control.type !== 'radio') {
            const directa = etiquetaPorId(form, control.id);
            if (directa) return directa;
        }
        let nodo = control.parentElement;
        for (let i = 0; nodo && nodo !== form && i < 6; i++, nodo = nodo.parentElement) {
            const encontrada = Array.from(nodo.children).find(hijo =>
                hijo.tagName === 'LABEL' && !hijo.classList.contains('form-check-label') && !hijo.contains(control));
            if (encontrada) return encontrada;
        }
        return null;
    }

    function etiquetaCampo(form, control) {
        if (control.dataset && control.dataset.titulo) return control.dataset.titulo;
        const elemento = etiquetaElemento(form, control);
        if (elemento) return limpiarEtiqueta(textoDe(elemento));
        return limpiarEtiqueta(control.placeholder || control.name || '');
    }

    // Unidad que el formulario muestra junto al campo ("años", "kg"…), si es texto y no un ícono
    function unidadDe(control) {
        const grupo = control.closest('.input-group');
        if (!grupo) return '';
        const unidad = Array.from(grupo.querySelectorAll('.input-group-text')).find(e =>
            (e.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_PRECEDING) && textoDe(e) !== '');
        return unidad ? textoDe(unidad) : '';
    }

    function esCorto(valor) {
        return valor.length <= 90 && valor.indexOf('\n') === -1;
    }

    // Para comparar títulos: sin acentos, sin paréntesis ni signos
    function normalizar(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/\(.*?\)/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
    }

    // ---------------------------------------------------------------- lienzo de una sección
    // Arma el contenido de un paso: encabezados pendientes (se escriben solo si después hay datos),
    // fichas de datos cortos y bloques de texto largo.
    function crearLienzo(contenedor, tituloSeccion) {
        let pendienteH3 = '';
        let pendienteH4 = '';
        let ultimoTitulo = normalizar(tituloSeccion);
        let ficha = null;
        let conContenido = false;

        function escribirPendientes() {
            if (pendienteH3) { contenedor.appendChild(crear('h3', 'rep-subtitulo', pendienteH3)); ultimoTitulo = normalizar(pendienteH3); pendienteH3 = ''; ficha = null; }
            if (pendienteH4) { contenedor.appendChild(crear('h4', 'rep-grupo', pendienteH4)); ultimoTitulo = normalizar(pendienteH4); pendienteH4 = ''; ficha = null; }
            conContenido = true;
        }

        return {
            subtitulo(texto) {
                const limpio = limpiarEtiqueta(texto);
                // El primer subtítulo que repite el título de la sección no se escribe
                pendienteH3 = (!conContenido && normalizar(limpio) === normalizar(tituloSeccion)) ? '' : limpio;
                pendienteH4 = '';
            },
            grupo(texto) { pendienteH4 = limpiarEtiqueta(texto); },
            dato(etiqueta, valor) {
                valor = String(valor).trim();
                if (valor === '') return;
                escribirPendientes();
                if (esCorto(valor)) {
                    if (!ficha) { ficha = crear('dl', 'rep-ficha'); contenedor.appendChild(ficha); }
                    ficha.appendChild(crear('dt', '', etiqueta));
                    ficha.appendChild(crear('dd', '', valor));
                    ultimoTitulo = '';
                } else {
                    ficha = null;
                    const bloque = crear('div', 'rep-bloque');
                    // Si la etiqueta repite el título que está justo arriba, no se escribe otra vez
                    if (normalizar(etiqueta) !== ultimoTitulo) bloque.appendChild(crear('div', 'rep-etiqueta', etiqueta));
                    bloque.appendChild(crear('div', 'rep-texto', valor));
                    contenedor.appendChild(bloque);
                }
            },
            lista(etiqueta, valores) {
                valores = valores.map(v => String(v).trim()).filter(v => v !== '');
                if (valores.length === 0) return;
                if (valores.length === 1) { this.dato(etiqueta, valores[0]); return; }
                escribirPendientes();
                ficha = null;
                const bloque = crear('div', 'rep-bloque');
                bloque.appendChild(crear('div', 'rep-etiqueta', etiqueta));
                const ul = crear('ul', 'rep-lista');
                valores.forEach(v => ul.appendChild(crear('li', '', v)));
                bloque.appendChild(ul);
                contenedor.appendChild(bloque);
                ultimoTitulo = '';
            },
            elemento(nodo) {
                escribirPendientes();
                ficha = null;
                contenedor.appendChild(nodo);
            },
            get conContenido() { return conContenido; }
        };
    }

    // ---------------------------------------------------------------- bloques especiales
    function tabla(encabezados, filas, claseFila) {
        const t = crear('table', 'rep-tabla');
        const thead = crear('thead');
        const tr = crear('tr');
        encabezados.forEach(e => tr.appendChild(crear('th', '', e)));
        thead.appendChild(tr);
        t.appendChild(thead);
        const tbody = crear('tbody');
        filas.forEach((fila, i) => {
            const r = crear('tr', claseFila ? claseFila(i) : '');
            fila.forEach(celda => {
                const td = crear('td');
                if (celda instanceof Node) td.appendChild(celda); else td.textContent = celda;
                r.appendChild(td);
            });
            tbody.appendChild(r);
        });
        t.appendChild(tbody);
        return t;
    }

    function valorNombre(form, nombre) {
        const c = form.querySelector(`[name="${CSS.escape(nombre)}"]`);
        return c ? String(c.value || '').trim() : '';
    }

    // Diagnósticos diferenciales con el correcto señalado
    function bloqueDiferenciales(form, raiz, lienzo, consumidos) {
        const selector = form.querySelector('[name="diagnostico_correcto"]');
        const correcto = selector ? String(selector.value || '').trim() : '';
        if (selector) consumidos.add(selector);
        const filas = [];
        raiz.querySelectorAll('.question-block').forEach(tarjeta => {
            const nombre = tarjeta.querySelector('input[name^="diag_dif_"]:not([name*="favor"]):not([name*="contra"])');
            const favor = tarjeta.querySelector('[name^="diag_dif_favor_"]');
            const contra = tarjeta.querySelector('[name^="diag_dif_contra_"]');
            const v = [nombre, favor, contra].map(c => c ? String(c.value || '').trim() : '');
            if (v.some(x => x !== '')) filas.push(v);
        });
        raiz.querySelectorAll('input, textarea, select').forEach(c => consumidos.add(c));

        if (correcto !== '') {
            const caja = crear('div', 'rep-destacado');
            caja.appendChild(crear('span', 'rep-destacado-etiqueta', 'Diagnóstico correcto'));
            caja.appendChild(crear('span', 'rep-destacado-valor', correcto));
            lienzo.elemento(caja);
        }
        if (filas.length === 0) return;
        const celdas = filas.map(([nombre, favor, contra]) => {
            const celdaNombre = crear('div');
            celdaNombre.appendChild(crear('strong', '', nombre || 'Sin nombre'));
            if (correcto !== '' && nombre === correcto) celdaNombre.appendChild(crear('span', 'rep-insignia', 'Correcto'));
            return [celdaNombre, favor, contra];
        });
        const t = tabla(['Diagnóstico diferencial', 'A favor', 'En contra'], celdas,
            i => (correcto !== '' && filas[i][0] === correcto) ? 'rep-fila-correcta' : '');
        t.classList.add('rep-tabla-diferenciales');
        lienzo.elemento(t);
    }

    // Preguntas de opción múltiple con la respuesta correcta y el razonamiento de cada opción
    function bloquePreguntas(form, raiz, lienzo, consumidos) {
        let numero = 0;
        raiz.querySelectorAll('.question-block').forEach(tarjeta => {
            const enunciado = tarjeta.querySelector('input[name^="pregunta_"]');
            if (!enunciado) return;
            const n = enunciado.name.replace('pregunta_', '');
            const texto = String(enunciado.value || '').trim();
            const opciones = [1, 2, 3, 4].map(k => [valorNombre(form, `p${n}_opcion_${k}`), valorNombre(form, `p${n}_razon_${k}`)]);
            const correcta = valorNombre(form, `p${n}_correcta`).replace(/\D/g, '');
            if (texto === '' && opciones.every(o => o[0] === '' && o[1] === '')) return;

            numero++;
            const caja = crear('div', 'rep-pregunta');
            const titulo = crear('div', 'rep-pregunta-titulo');
            titulo.appendChild(crear('span', 'rep-pregunta-numero', 'Pregunta ' + numero));
            titulo.appendChild(crear('span', '', texto || 'Sin enunciado'));
            caja.appendChild(titulo);
            const filas = [];
            const marcas = [];
            opciones.forEach(([opcion, razon], i) => {
                if (opcion === '' && razon === '') return;
                const letra = crear('div', 'rep-opcion-letra');
                letra.appendChild(crear('span', '', String.fromCharCode(65 + i)));
                const esCorrecta = correcta === String(i + 1);
                const celdaOpcion = crear('div');
                celdaOpcion.appendChild(crear('span', '', opcion || '—'));
                if (esCorrecta) celdaOpcion.appendChild(crear('span', 'rep-insignia', 'Correcta'));
                filas.push([letra, celdaOpcion, razon || '—']);
                marcas.push(esCorrecta);
            });
            if (filas.length) caja.appendChild(tabla(['', 'Opción', 'Razonamiento'], filas, i => marcas[i] ? 'rep-fila-correcta' : ''));
            if (correcta === '') caja.appendChild(crear('p', 'rep-nota', 'Sin respuesta correcta registrada.'));
            lienzo.elemento(caja);
        });
        raiz.querySelectorAll('input, textarea, select').forEach(c => consumidos.add(c));
    }

    // Tablas de renglones (estadificación, tratamiento farmacológico): columnas de COLUMNAS_TABLAS del formulario
    function bloqueTablaFilas(raiz, lienzo, consumidos) {
        const columnas = (typeof COLUMNAS_TABLAS !== 'undefined' && COLUMNAS_TABLAS[raiz.id]) || [];
        const filas = typeof leerFilas === 'function' ? leerFilas(raiz.id) : [];
        raiz.querySelectorAll('input, textarea, select').forEach(c => consumidos.add(c));
        if (filas.length === 0 || columnas.length === 0) return;
        lienzo.elemento(tabla(columnas.map(c => c[1]), filas.map(f => columnas.map(c => f[c[0]] || '—'))));
    }

    // Asignaturas por año, con las "Otra" escritas
    function bloqueAsignaturas(form, raiz, lienzo, consumidos) {
        const lineas = [];
        raiz.querySelectorAll('.p-3, .border.rounded').forEach(caja => {
            if (caja.querySelector('.p-3')) return;
            const anio = textoDe(caja.querySelector('h6'));
            const marcadas = Array.from(caja.querySelectorAll('input[type="checkbox"][name="asignaturas"]:checked'))
                .map(c => textoDe(etiquetaPorId(form, c.id)) || c.value);
            const otra = caja.querySelector('input[type="text"]');
            if (otra && String(otra.value || '').trim() !== '') marcadas.push('Otra: ' + otra.value.trim());
            if (marcadas.length) lineas.push((anio ? anio + ': ' : '') + marcadas.join(', '));
        });
        raiz.querySelectorAll('input, textarea, select').forEach(c => consumidos.add(c));
        const etiqueta = etiquetaElemento(form, raiz.querySelector('input')) ;
        lienzo.lista(etiqueta ? limpiarEtiqueta(textoDe(etiqueta)) : 'Asignaturas relacionadas', lineas);
    }

    // Texto escrito junto a una opción "Otro"/"Otra" (casilla o lista desplegable)
    function textoOtro(control) {
        const contenedor = control.closest('.form-check') ? control.closest('.form-check').parentElement : control.parentElement;
        const campo = contenedor ? Array.from(contenedor.querySelectorAll('input[type="text"]')).find(c => c !== control) : null;
        return campo || null;
    }

    // ---------------------------------------------------------------- recorrido de un paso
    function recorrerPaso(form, paso, lienzo, consumidos, raicesEspeciales) {
        const etiquetasDeCampo = new Set();
        paso.querySelectorAll('input, select, textarea').forEach(c => {
            const e = etiquetaElemento(form, c);
            if (e) etiquetasDeCampo.add(e);
        });
        const procesadas = new Set();

        const nodos = paso.querySelectorAll('h2.section-title, h6, label.form-label, input, select, textarea');
        for (let i = 0; i < nodos.length; i++) {
            const nodo = nodos[i];
            if (consumidos.has(nodo)) continue;
            if (nodo.closest('.vista-previa-juego, .hv-indice-pasos, #nav-pasos, .hv-barra-guardar')) continue;

            // Bloques que se dibujan completos la primera vez que se encuentran
            const raiz = raicesEspeciales.find(r => r.elemento.contains(nodo));
            if (raiz) {
                if (!procesadas.has(raiz.elemento)) {
                    procesadas.add(raiz.elemento);
                    raiz.dibujar(lienzo);
                }
                continue;
            }

            if (nodo.matches('h2.section-title')) { lienzo.subtitulo(textoDe(nodo)); continue; }
            if (nodo.tagName === 'H6') { lienzo.grupo(textoDe(nodo)); continue; }
            if (nodo.tagName === 'LABEL') {
                // Una etiqueta que no pertenece a un campo es el título de un grupo (Signos vitales, Tabla SOAP…)
                if (!etiquetasDeCampo.has(nodo) && !nodo.classList.contains('form-check-label')) lienzo.grupo(textoDe(nodo));
                continue;
            }

            const control = nodo;
            if (['file', 'hidden', 'button', 'submit'].includes(control.type)) continue;
            if (control.classList.contains('control-navegacion')) continue;
            if (NOMBRES_EN_PORTADA.includes(control.name)) continue;

            if (control.type === 'checkbox' || control.type === 'radio') {
                // Todas las opciones del mismo nombre de una vez
                const grupo = control.name
                    ? Array.from(paso.querySelectorAll(`input[name="${CSS.escape(control.name)}"]`))
                    : [control];
                grupo.forEach(c => consumidos.add(c));
                const elegidas = grupo.filter(c => c.checked).map(c => {
                    let t = textoDe(etiquetaPorId(form, c.id)) || c.value;
                    if (/^otr[oa]\b/i.test(t)) {
                        const campo = textoOtro(c);
                        if (campo) {
                            consumidos.add(campo);
                            if (String(campo.value || '').trim() !== '') t = 'Otro: ' + campo.value.trim();
                        }
                    }
                    return t.replace(/:$/, '');
                });
                if (elegidas.length) lienzo.dato(etiquetaCampo(form, control), elegidas.join(', '));
                continue;
            }

            consumidos.add(control);
            if (control.tagName === 'SELECT') {
                const opcion = control.selectedOptions[0];
                if (!opcion || String(control.value || '') === '') continue;
                let t = textoDe(opcion);
                if (control.value === 'Otro') {
                    const campo = textoOtro(control);
                    if (campo) {
                        consumidos.add(campo);
                        if (String(campo.value || '').trim() !== '') t = 'Otro: ' + campo.value.trim();
                    }
                }
                lienzo.dato(etiquetaCampo(form, control), t);
                continue;
            }

            // Campos de texto con el mismo nombre (autores, enlaces, preguntas de simulación…): una lista
            const iguales = control.name ? Array.from(paso.querySelectorAll(`[name="${CSS.escape(control.name)}"]`)) : [control];
            if (iguales.length > 1) {
                iguales.forEach(c => consumidos.add(c));
                lienzo.lista(etiquetaCampo(form, control), iguales.map(c => c.value));
                continue;
            }

            const valor = String(control.value || '').trim();
            if (valor !== '') {
                const unidad = unidadDe(control);
                lienzo.dato(etiquetaCampo(form, control), unidad ? valor + ' ' + unidad : valor);
            }
            if (control.name === 'somatometria_talla' && typeof calcularImc === 'function' && calcularImc()) {
                lienzo.dato('IMC (calculado)', calcularImc() + ' kg/m²');
            }
        }
    }

    // ---------------------------------------------------------------- portada y anexos
    function portada(form) {
        const p = crear('header', 'rep-portada');
        const banda = crear('div', 'rep-banda');
        const logos = crear('div', 'rep-logos');
        [['assets/img/escudo-unam-blanco.png', 'UNAM'], ['assets/img/escudo-fm-blanco-web.png', 'Facultad de Medicina']].forEach(([src, alt]) => {
            const img = crear('img');
            img.src = src;
            img.alt = alt;
            logos.appendChild(img);
        });
        banda.appendChild(logos);
        const institucion = crear('div', 'rep-institucion');
        institucion.appendChild(crear('div', 'rep-institucion-1', 'Universidad Nacional Autónoma de México · Facultad de Medicina'));
        institucion.appendChild(crear('div', 'rep-institucion-2', 'Hospital Virtual FacMed · Banco de casos clínicos'));
        banda.appendChild(institucion);
        p.appendChild(banda);

        const cuerpo = crear('div', 'rep-portada-cuerpo');
        cuerpo.appendChild(crear('div', 'rep-tipo', 'Reporte de caso clínico'));
        cuerpo.appendChild(crear('h1', 'rep-titulo', R.titulo || 'Caso sin título'));

        const sexo = (form.querySelector('input[name="sexo"]:checked') || {}).value || '';
        const edad = valorNombre(form, 'edad');
        const sistemas = Array.from(form.querySelectorAll('input[name="sistemas_anatomicos"]:checked')).map(c => c.value);
        const padecimiento = valorNombre(form, 'padecimiento') === 'Otro'
            ? valorNombre(form, 'padecimiento_otro_texto') : valorNombre(form, 'padecimiento');

        const datos = crear('dl', 'rep-portada-datos');
        [['Identificador', R.identificador || 'Sin asignar'],
         ['Registro', '#' + R.id + (R.fechaRegistro ? ' · ' + R.fechaRegistro : '')],
         ['Paciente', [sexo, edad ? edad + ' años' : ''].filter(Boolean).join(', ')],
         ['Padecimiento', padecimiento],
         ['Sistema(s)', sistemas.join(', ')]
        ].forEach(([k, v]) => {
            if (!v) return;
            datos.appendChild(crear('dt', '', k));
            datos.appendChild(crear('dd', '', v));
        });
        cuerpo.appendChild(datos);
        cuerpo.appendChild(crear('p', 'rep-confidencial',
            'Documento de uso académico interno: contiene el diagnóstico y las respuestas del caso. No lo compartas con los estudiantes.'));
        p.appendChild(cuerpo);
        return p;
    }

    function anexos() {
        const adjuntos = Array.isArray(R.adjuntos) ? R.adjuntos : [];
        if (adjuntos.length === 0) return null;
        const s = crear('section', 'rep-seccion rep-anexos');
        s.appendChild(crear('h2', 'rep-seccion-titulo', 'Anexos: archivos adjuntos'));
        const sinImagen = [];
        adjuntos.forEach((a, i) => {
            if (a.imagen) {
                const fig = crear('figure', 'rep-figura');
                const img = crear('img');
                img.src = a.imagen;
                img.alt = a.nombre || ('Adjunto ' + (i + 1));
                fig.appendChild(img);
                fig.appendChild(crear('figcaption', '', 'Figura ' + (i + 1) + '. ' + (a.nombre || 'Imagen adjunta')));
                s.appendChild(fig);
            } else {
                sinImagen.push((a.nombre || 'Archivo ' + (i + 1)) + (a.nota ? ' (' + a.nota + ')' : ''));
            }
        });
        if (sinImagen.length) {
            s.appendChild(crear('div', 'rep-etiqueta', 'Otros archivos (se consultan en el gestor)'));
            const ul = crear('ul', 'rep-lista');
            sinImagen.forEach(n => ul.appendChild(crear('li', '', n)));
            s.appendChild(ul);
        }
        return s;
    }

    // ---------------------------------------------------------------- armado del reporte
    function armarReporte() {
        const form = document.getElementById('formulario-caso');
        const articulo = crear('article', 'rep-caso');
        articulo.dataset.id = R.id;
        articulo.appendChild(portada(form));

        const consumidos = new Set();
        const especiales = [];
        const agregarEspecial = (elemento, dibujar) => { if (elemento) especiales.push({ elemento, dibujar }); };
        agregarEspecial(document.getElementById('diagnosticos-container'), l => bloqueDiferenciales(form, document.getElementById('diagnosticos-container'), l, consumidos));
        agregarEspecial(document.getElementById('preguntas-container'), l => bloquePreguntas(form, document.getElementById('preguntas-container'), l, consumidos));
        ['filas-estadificacion', 'filas-farmacologico'].forEach(id => {
            const e = document.getElementById(id);
            agregarEspecial(e, l => bloqueTablaFilas(e, l, consumidos));
        });
        const primeraAsignatura = form.querySelector('input[name="asignaturas"]');
        const raizAsignaturas = primeraAsignatura ? primeraAsignatura.closest('.row') : null;
        agregarEspecial(raizAsignaturas, l => bloqueAsignaturas(form, raizAsignaturas, l, consumidos));
        // El selector del diagnóstico correcto se dibuja junto con los diferenciales
        const selector = form.querySelector('[name="diagnostico_correcto"]');
        if (selector) consumidos.add(selector);

        let numero = 0;
        form.querySelectorAll('.paso-formulario').forEach(paso => {
            const seccion = crear('section', 'rep-seccion');
            const titulo = crear('h2', 'rep-seccion-titulo');
            const cuerpo = crear('div', 'rep-seccion-cuerpo');
            const lienzo = crear('div');
            const l = crearLienzo(lienzo, paso.dataset.titulo || '');
            recorrerPaso(form, paso, l, consumidos, especiales);
            if (!l.conContenido) return;
            numero++;
            titulo.appendChild(crear('span', 'rep-seccion-numero', String(numero)));
            titulo.appendChild(document.createTextNode(paso.dataset.titulo || ''));
            seccion.appendChild(titulo);
            cuerpo.appendChild(lienzo);
            seccion.appendChild(cuerpo);
            articulo.appendChild(seccion);
        });

        const a = anexos();
        if (a) articulo.appendChild(a);

        const pie = crear('p', 'rep-generado',
            'Reporte generado el ' + (R.fechaGeneracion || '') + (R.generadoPor ? ' por ' + R.generadoPor : '') + ' desde el Gestor de Casos Clínicos de Hospital Virtual FacMed.');
        articulo.appendChild(pie);
        return articulo;
    }

    function barraHerramientas() {
        const barra = crear('div', 'rep-herramientas');
        const contenido = crear('div', 'rep-herramientas-contenido');
        const info = crear('div', 'rep-herramientas-texto');
        info.appendChild(crear('strong', '', 'Reporte del caso #' + R.id));
        info.appendChild(crear('span', '', 'En el diálogo de impresión elige «Guardar como PDF». Si no ves los colores, activa «Gráficos de fondo».'));
        contenido.appendChild(info);
        const volver = crear('a', 'btn btn-outline-light btn-sm');
        volver.href = 'ver_caso.php?id=' + encodeURIComponent(R.id);
        volver.innerHTML = '<i class="bi bi-arrow-left me-1"></i>Volver al caso';
        const imprimir = crear('button', 'btn btn-oro btn-sm');
        imprimir.type = 'button';
        imprimir.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i>Imprimir o guardar como PDF';
        imprimir.addEventListener('click', () => window.print());
        const botones = crear('div', 'd-flex gap-2');
        botones.appendChild(volver);
        botones.appendChild(imprimir);
        contenido.appendChild(botones);
        barra.appendChild(contenido);
        return barra;
    }

    document.addEventListener('DOMContentLoaded', () => {
        let reporte;
        try {
            reporte = armarReporte();
        } catch (error) {
            console.error('[reporte] No se pudo armar el reporte:', error);
            reporte = crear('article', 'rep-caso rep-error');
            reporte.appendChild(crear('p', '', 'No se pudo generar el reporte del caso #' + R.id + '.'));
        }

        if (R.incrustado && window.parent && window.parent !== window) {
            // Lo pidió reporte_casos.php: se le envía el HTML del reporte
            window.parent.postMessage({ tipo: 'hv-reporte-caso', id: R.id, html: reporte.outerHTML }, window.location.origin);
            return;
        }

        const documento = crear('div', 'rep-documento');
        documento.appendChild(reporte);
        document.body.appendChild(barraHerramientas());
        document.body.appendChild(documento);
        document.title = 'Reporte ' + (R.identificador || ('caso ' + R.id)) + ' · ' + (R.titulo || 'Caso clínico');
        if (R.imprimirAlAbrir) setTimeout(() => window.print(), 600);
    });
})();
