'use strict';

const form = document.querySelector('#configuracion');
const canvas = document.querySelector('canvas');
const ctx = canvas.getContext('2d');
const pieles = {
    clara: ['#f3c8af', '#db9f85'],
    media: ['#d69a72', '#b97552'],
    morena: ['#ae714d', '#864d35'],
    oscura: ['#78503e', '#593626'],
};
const cabellos = { negro: '#282325', castano: '#553428', cobrizo: '#9b4931', gris: '#a4a09b' };
const uniformes = {
    verde: ['#287565', '#174e45'],
    azul: ['#345f96', '#233d62'],
    vino: ['#84546e', '#56344a'],
    blanco: ['#fcfcf7', '#b3c7bf'],
};

function elipse(x, y, ancho, alto, color) {
    ctx.beginPath();
    ctx.ellipse(x, y, ancho, alto, 0, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
}

function forma(color, pasos) {
    ctx.beginPath();
    pasos();
    ctx.closePath();
    ctx.fillStyle = color;
    ctx.fill();
}

function trazo(color, ancho, pasos) {
    ctx.beginPath();
    pasos();
    ctx.strokeStyle = color;
    ctx.lineWidth = ancho;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();
}

function dibujar() {
    const datos = new FormData(form);
    const [piel, sombra] = pieles[datos.get('piel')];
    const pelo = cabellos[datos.get('cabello')];
    const [uniforme, costura] = uniformes[datos.get('uniforme')];
    const peinado = datos.get('peinado');
    const modelo = datos.get('modelo') || 'Neutro';

    ctx.clearRect(0, 0, 660, 660);
    elipse(330, 325, 265, 265, '#b5d9c9');
    ctx.save();
    ctx.beginPath();
    ctx.arc(330, 325, 265, 0, Math.PI * 2);
    ctx.clip();

    if (peinado === 'largo') elipse(330, 310, 127, 178, pelo);
    if (peinado === 'recogido') elipse(417, 240, 37, 40, pelo);
    forma(uniforme, () => {
        ctx.moveTo(125, 605);
        const hombro = modelo === 'Masculino' ? 19 : modelo === 'Femenino' ? -8 : 0;
        ctx.bezierCurveTo(135-hombro, 474, 207-hombro, 403, 330, 403);
        ctx.bezierCurveTo(453+hombro, 403, 525+hombro, 474, 535, 605);
    });
    forma(sombra, () => {
        ctx.moveTo(285, 344);
        ctx.lineTo(375, 344);
        ctx.lineTo(383, 423);
        ctx.quadraticCurveTo(330, 495, 277, 423);
    });
    trazo(costura, 9, () => {
        ctx.moveTo(277, 423);
        ctx.lineTo(330, 477);
        ctx.lineTo(383, 423);
    });
    trazo(costura, 5, () => {
        ctx.moveTo(406, 508);
        ctx.lineTo(465, 508);
    });
    elipse(221, 289, 21, 33, sombra);
    elipse(439, 289, 21, 33, sombra);
    if (modelo === 'Neutro') {
        elipse(330, 273, 109, 130, piel);
    } else {
        const mandibula = modelo === 'Masculino' ? 102 : 68;
        forma(piel, () => {
            ctx.moveTo(221,268);
            ctx.bezierCurveTo(218,109,442,109,439,268);
            ctx.bezierCurveTo(441,338,330+mandibula,397,330,402);
            ctx.bezierCurveTo(330-mandibula,397,219,338,221,268);
        });
    }

    if (peinado === 'ondulado') {
        elipse(330, 196, 111, 60, pelo);
        for (let i = 0; i < 6; i++) elipse(229 + i * 40, 194 - Math.sin(i * Math.PI / 5) * 33, 31, 37, pelo);
    } else if (peinado === 'rapado') {
        forma(pelo, () => {
            ctx.moveTo(227, 242);
            ctx.bezierCurveTo(252, 111, 408, 111, 433, 242);
            ctx.quadraticCurveTo(330, 147, 227, 242);
        });
    } else {
        forma(pelo, () => {
            ctx.moveTo(219, 261);
            ctx.bezierCurveTo(191, 96, 453, 90, 441, 263);
            ctx.quadraticCurveTo(414, 249, 392, 197);
            ctx.quadraticCurveTo(342, 237, 238, 224);
        });
    }
    for (const x of [283, 377]) {
        trazo(pelo, modelo === 'Femenino' ? 4 : modelo === 'Masculino' ? 8 : 6, () => {
            ctx.moveTo(x - 15, 267);
            ctx.quadraticCurveTo(x, 259, x + 15, 267);
        });
        elipse(x, 286, modelo === 'Femenino' ? 9 : 7, 9, '#252422');
    }
    trazo(sombra, 5, () => {
        ctx.moveTo(333, 284);
        ctx.lineTo(323, 315);
        ctx.lineTo(333, 316);
    });
    trazo('#5d3430', 5, () => {
        ctx.moveTo(311, 344);
        ctx.quadraticCurveTo(330, 360, 350, 344);
    });
    if (datos.get('rostro') === 'lentes') {
        trazo('#293b36', 6, () => {
            ctx.roundRect(253, 272, 60, 36, 12);
            ctx.roundRect(347, 272, 60, 36, 12);
            ctx.moveTo(313, 284);
            ctx.lineTo(347, 284);
        });
    }
    if (datos.get('cuello') === 'estetoscopio') {
        trazo('#243936', 9, () => {
            ctx.moveTo(269, 420);
            ctx.bezierCurveTo(237, 455, 265, 524, 307, 506);
            ctx.bezierCurveTo(352, 488, 330, 445, 385, 421);
        });
        elipse(309, 505, 13, 13, '#3a514c');
        elipse(309, 505, 7, 7, '#cfddd5');
    }
    ctx.restore();
}

if (ctx) {
    form.addEventListener('change', dibujar);
    dibujar();
}

const savedAvatar = JSON.parse(form.dataset.guardado);
const panels = document.querySelectorAll('[data-grupo]');
const tabs = document.querySelectorAll('[data-seccion]');
const restoreButton = document.querySelector('#restaurar');

function openSection(name) {
    for (const panel of panels) panel.hidden = panel.dataset.grupo !== name;
    for (const tab of tabs) tab.setAttribute('aria-pressed', String(tab.dataset.seccion === name));
}
for (const tab of tabs) tab.addEventListener('click', () => openSection(tab.dataset.seccion));
document.querySelector('.secciones').hidden = false;
openSection('Apariencia');

function updateStatus() {
    const values = new FormData(form);
    const dirty = Object.keys(savedAvatar).some(key => savedAvatar[key] !== (values.get(key) || ''));
    restoreButton.hidden = !dirty;
    document.querySelector('#estado').textContent = dirty ? 'Cambios sin guardar' : '';
}
form.addEventListener('change', updateStatus);
restoreButton.addEventListener('click', () => {
    for (const input of form.querySelectorAll('input[type=radio]')) {
        input.checked = input.value === savedAvatar[input.name];
    }
    form.dispatchEvent(new Event('change'));
});
