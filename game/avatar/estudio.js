// Estudio del vestidor. El retrato 2D sigue disponible si WebGL no arranca.
(() => {
    const T = window.THREE;
    if (!T) return;
    const host = document.querySelector('#estudio');
    let renderer;
    try {
        renderer = new T.WebGLRenderer({alpha:true, antialias:true});
    } catch { return; }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = T.SRGBColorSpace;
    const scene = new T.Scene();
    const camera = new T.PerspectiveCamera(29, 1, 0.1, 40);
    scene.add(new T.HemisphereLight(0xfff4e4, 0xb9a399, 2));
    for (const [x,y,z,intensity] of [[-3,6,5,2.4],[2,3,-4,3]]) {
        const light = new T.DirectionalLight(0xfff3de,intensity);
        light.position.set(x,y,z);
        scene.add(light);
    }
    const figure = new T.Group();
    scene.add(figure);
    const sphere = new T.SphereGeometry(1,32,24);
    const materials = new Map();
    let part = figure;
    let rig;
    let action = 'reposo';
    let elapsed = 0;
    function joint(name, x, y, z, parent = figure) {
        const group = new T.Group();
        group.name = name;
        group.userData.origin = new T.Vector3(x,y,z);
        group.position.copy(group.userData.origin).sub(parent.userData.origin || figure.position);
        parent.add(group);
        return group;
    }
    function attach(mesh) {
        if (part.userData.origin) mesh.position.sub(part.userData.origin);
        part.add(mesh);
        return mesh;
    }
    function material(color) {
        if (!materials.has(color)) materials.set(color,new T.MeshStandardMaterial({color,roughness:.49}));
        return materials.get(color);
    }
    function oval(x,y,z,w,h,d,color,tilt=0) {
        const mesh = new T.Mesh(sphere,material(color));
        mesh.position.set(x,y,z);
        mesh.scale.set(w,h,d);
        mesh.rotation.z = tilt;
        return attach(mesh);
    }
    function tube(points,radius,color) {
        const curve = new T.CatmullRomCurve3(points.map(p => new T.Vector3(...p)));
        const mesh = new T.Mesh(new T.TubeGeometry(curve,32,radius,8,false),material(color));
        return attach(mesh);
    }
    // Contornos de revolución para las prendas y el calzado, en la pose de referencia.
    function garment(x,y,z,profile,depth,color) {
        const geometry = new T.LatheGeometry(profile.map(([r,h]) => new T.Vector2(r,h)),32);
        const mesh = new T.Mesh(geometry,material(color));
        mesh.position.set(x,y,z);
        mesh.scale.z = depth;
        return attach(mesh);
    }
    const stage = new T.Mesh(new T.CylinderGeometry(1.05,1.12,.12,64),material('#c49974'));
    stage.position.y = -.04;
    scene.add(stage);
    let close = false;
    figure.rotation.y = -.18;
    function render() {
        const w = host.clientWidth, h = host.clientHeight;
        if (!w || !h) return;
        if (renderer.domElement.width !== Math.floor(w*renderer.getPixelRatio()) ||
            renderer.domElement.height !== Math.floor(h*renderer.getPixelRatio())) renderer.setSize(w,h,false);
        camera.aspect = w/h;
        const center = close ? 2.62 : 1.78;
        camera.position.set(0, center+.22, (close ? 3.95 : 8.3) / Math.min(camera.aspect,1));
        camera.lookAt(0,center,0);
        camera.updateProjectionMatrix();
        renderer.render(scene,camera);
    }
    function dress() {
        figure.traverse(mesh => {
            if (mesh.geometry && mesh.geometry !== sphere) mesh.geometry.dispose();
        });
        figure.clear();
        figure.position.y = 0;
        const body = joint('torso',0,1.08,0);
        rig = {body,limbs:[],brows:[],mouths:[]};
        const data = new FormData(form);
        const femenino = data.get('modelo') === 'Femenino';
        const masculino = data.get('modelo') === 'Masculino';
        body.scale.x = femenino ? .96 : masculino ? 1.095 : 1;
        const grosorCeja = femenino ? .025 : masculino ? .043 : .035;
        // Altura del centro, profundidad y radios de la nariz.
        const nariz = femenino ? [2.53,.478,.082,.085,.083]
            : masculino ? [2.53,.491,.097,.10,.106] : [2.53,.491,.088,.10,.09];
        const skin = pieles[data.get('piel')][0], hair = cabellos[data.get('cabello')];
        const [cloth,trim] = uniformes[data.get('uniforme')];
        for (const side of [-1,1]) {
            const x = side*.209;
            const hip = joint('cadera',x,1.12,0);
            const knee = joint('rodilla',x,.66,0,hip);
            const foot = joint('tobillo',x,.21,0,knee);
            const shoulder = joint('hombro',side*.4,1.88,0,body);
            const elbow = joint('codo',side*.54,1.55,0,shoulder);
            part = hip;
            oval(x,.91,0,.198,.34,.203,trim);
            part = knee;
            garment(x,.21,0,[[0,0],[.148,.01],[.153,.08],[.173,.32],[.17,.46],[.12,.51],[0,.53]],1.03,trim);
            part = foot;
            oval(x,.16,.073,.185,.135,.29,'#f1eee7');
            const sole = oval(x,.082,.087,.188,.055,.296,'#bcb8aa');
            rig.limbs.push({side,hip,knee,foot,sole,shoulder,elbow});
            for (const z of [.13,.19]) tube([[x-.07,.255,z],[x,.275,z],[x+.07,.255,z]],.011,'#fffdfa');
            part = shoulder;
            oval(side*.52,1.62,0,.122,.16,.13,skin,side*.25);
            garment(side*.51,1.65,0,[[0,0],[.167,0],[.175,.047],[.178,.16],[.14,.25],[0,.29]],1.14,cloth).rotation.z=side*.25;
            part = elbow;
            oval(side*.55,1.405,.015,.113,.20,.118,skin);
            oval(side*.56,1.23,.045,.114,.137,.11,skin);
            oval(side*.48,1.245,.095,.05,.076,.055,skin);
        }
        part = body;
        const bajoFilipina = femenino
            ? [[.413,0],[.438,.04],[.378,.28]]
            : [[.386,0],[.416,.04],[.40,.28]];
        garment(0,1.04,0,[[0,0],...bajoFilipina,[.435,.55],[.459,.74],[.413,.83],[.196,.934],[.15,.94],[0,.94]],.73,cloth);
        oval(0,2,0,.18,.23,.18,skin);
        tube([[-.17,1.979,.102],[-.12,1.929,.22],[0,1.81,.343],[.12,1.929,.22],[.17,1.979,.102]],.023,trim);
        tube([[.146,1.70,.30],[.155,1.53,.295],[.234,1.51,.268],[.292,1.55,.24],[.293,1.70,.25]],.009,trim);
        tube([[.146,1.70,.30],[.22,1.70,.286],[.293,1.70,.25]],.016,trim);
        rig.head = joint('cabeza',0,2.04,0,body);
        part = rig.head;
        const cara = oval(0,2.58,0,femenino ? .54 : .55,femenino ? .617 : .64,.49,skin);
        if (femenino || masculino) {
            rig.head.scale.set(femenino ? 1.015 : 1.018,femenino ? .99 : .982,femenino ? 1.02 : 1.035);
            cara.geometry = sphere.clone();
            const vertices = cara.geometry.attributes.position;
            for (let i=0; i<vertices.count; i++) {
                const y = vertices.getY(i);
                const jaw = Math.max(0,-y);
                const width = femenino ? 1-.18*jaw+.045*Math.exp(-((y+.17)**2)/.1) : 1+.32*jaw;
                vertices.setX(i,vertices.getX(i)*width);
            }
            cara.geometry.computeVertexNormals();
        }
        for (const s of [-1,1]) {
            oval(s*.53,2.56,0,.104,.145,.085,skin);
            oval(s*.21,2.65,.416,femenino ? .153 : .141,femenino ? .114 : .14,femenino ? .068 : .080,'#fff7ed',s*.06);
            oval(s*.205,2.65,.478,.073,.089,.033,'#6c4731');
            oval(s*.205,2.65,.502,.044,.058,.017,'#28212a');
            oval(s*.205-.015,2.683,.518,.016,.021,.007,'#ffffff');
            rig.brows.push(oval(s*.215,2.845,.392,.128,grosorCeja,.041,hair,-s*.09));
            if (femenino) tube([[s*.077,2.68,.445],[s*.18,2.756,.445],[s*.28,2.752,.445],[s*.348,2.707,.44],[s*.37,2.72,.417]],.009,hair);
        }
        oval(0,...nariz,skin);
        for (const [corner,middle] of [femenino ? [2.426,2.378] : [2.40,2.37],[2.42,2.34],[2.345,2.39]]) {
            rig.mouths.push(tube([[-.14,corner,.441],[-.07,(corner+middle)/2,.465],[0,middle,.47],[.07,(corner+middle)/2,.465],[.14,corner,.441]],.014,'#864d45'));
        }
        const style = data.get('peinado');
        oval(0,2.91,-.065,.551,style==='rapado'?.325:.39,.474,hair);
        if (style === 'largo') oval(0,2.54,-.27,.55,.68,.31,hair);
        if (style === 'ondulado') {
            for (let i=0;i<13;i++) {
                const a=i*2*Math.PI/13;
                oval(.432*Math.cos(a),3.01+.043*Math.cos(3*a),.36*Math.sin(a),.154,.17,.16,hair);
            }
        } else if (style === 'recogido') {
            oval(0,2.98,-.49,.29,.28,.261,hair);
            oval(-.216,2.986,.262,.28,.128,.168,hair,.46);
            oval(.199,3.022,.231,.254,.126,.153,hair,-.36);
            for (const s of [-1,1]) oval(s*.45,2.824,.031,.08,.225,.195,hair,-s*.3);
        } else if (style !== 'rapado') {
            for (let i=0;i<5;i++) oval(-.29+i*.13,3.01+i*.016,.242,.145,.20,.16,hair,-.65);
        }
        if (data.get('rostro')==='lentes') {
            for (const s of [-1,1]) {
                const rim = [];
                for (let i=0;i<=20;i++) {
                    const a=i*Math.PI/10;
                    rim.push([s*.215+.165*Math.cos(a),2.65+.145*Math.sin(a),.53]);
                }
                tube(rim,.016,'#38414b');
                tube([[s*.37,2.67,.53],[s*.50,2.70,.22],[s*.53,2.69,0]],.014,'#38414b');
            }
            tube([[-.055,2.68,.53],[0,2.70,.54],[.055,2.68,.53]],.012,'#38414b');
        }
        if (data.get('cuello')==='estetoscopio') {
            part = body;
            tube([[-.19,2.01,.04],[-.24,1.94,.20],[-.28,1.8,.274],[-.22,1.55,.30],[0,1.54,.34],[.13,1.83,.32],[.20,1.94,.215],[.19,2.01,.04]],.024,'#293d4b');
            oval(-.18,1.54,.321,.068,.069,.025,'#acb7ba');
        }
        pose();
        render();
    }
    // Cada pose parte de los mismos pivotes. Cambiar de prenda conserva la fase.
    function pose() {
        const running = action === 'correr';
        const walking = action === 'caminar';
        const happy = action === 'alegria';
        const sad = action === 'tristeza';
        const jump = action === 'saltar';
        const cycle = elapsed * (running ? 9 : 5);
        const beat = Math.sin(cycle);
        const jumpPhase = (elapsed % 1.8) / 1.8;
        const flight = jump && jumpPhase > .25 && jumpPhase < .75
            ? Math.sin(Math.PI * (jumpPhase-.25)*2) : 0;
        const crouch = jump ? Math.max(0,Math.sin(jumpPhase*4*Math.PI)) : 0;
        const {body,head,limbs,brows,mouths} = rig;
        figure.position.y = 0;
        const sigh = sad ? Math.sin(elapsed*2.1) : 0;
        body.rotation.set(sad ? .16+.045*sigh : (running ? .16 : .08*crouch),0,0);
        head.rotation.set(sad ? .22+.065*sigh : (happy ? -.12 : 0),0,happy ? .10*beat : 0);
        mouths.forEach((mouth,i) => { mouth.visible = i === (happy ? 1 : sad ? 2 : 0); });
        for (let i=0;i<limbs.length;i++) {
            const {side,hip,knee,foot,shoulder,elbow} = limbs[i];
            const stride = side*beat;
            const gait = walking || running;
            const swing = running ? .92 : .54;
            hip.rotation.x = gait ? swing*stride : -.53*crouch-.29*flight;
            knee.rotation.x = gait ? (.10 + Math.max(0,side*Math.cos(cycle))*(running ? 1.32 : .65)) : 1.04*crouch+.68*flight;
            foot.rotation.x = -.64*(hip.rotation.x+knee.rotation.x);
            shoulder.rotation.x = gait ? -swing*stride : (sad ? .11 : -.2*crouch);
            shoulder.rotation.z = side*(happy ? 2.22+.12*beat : (.07+1.23*flight));
            elbow.rotation.x = running ? -1.08 : happy ? -.48 : -.14;
            brows[i].rotation.z = side*(sad ? -.34 : happy ? .10 : -.09);
            brows[i].position.y = .805 + (happy ? .045 : sad ? .022 : 0);
        }
        // Apoyar la suela más baja en el pedestal incluso al flexionar las rodillas.
        figure.updateMatrixWorld(true);
        let bottom = Infinity;
        for (const limb of limbs) {
            const m = limb.sole.matrixWorld.elements;
            bottom = Math.min(bottom,m[13]-Math.hypot(m[1],m[5],m[9]));
        }
        figure.position.y = .022-bottom+.42*flight+(running ? .06*beat*beat : 0);
    }
    dress();
    renderer.domElement.setAttribute('role','img');
    renderer.domElement.setAttribute('aria-label','Vista tridimensional del avatar');
    host.append(renderer.domElement);
    canvas.hidden = true;
    const controls = document.querySelector('#camara');
    controls.hidden = false;
    const rehearsal = document.querySelector('#ensayo');
    const selector = document.querySelector('#movimiento');
    const pause = document.querySelector('#pausa');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let playing = false;
    let frame = 0;
    let previousTime = null;
    rehearsal.hidden = false;
    function tick(now) {
        if (previousTime !== null) elapsed += Math.min((now-previousTime)/1000,.08);
        previousTime = now;
        pose();
        render();
        frame = requestAnimationFrame(tick);
    }
    function playback(start) {
        playing = start;
        cancelAnimationFrame(frame);
        previousTime = null;
        if (start && !document.hidden) frame = requestAnimationFrame(tick);
        pause.disabled = action === 'reposo';
        pause.textContent = start || pause.disabled ? 'Pausar' : 'Reproducir';
    }
    selector.addEventListener('change', () => {
        action = selector.value;
        elapsed = reducedMotion.matches ? .20 : 0;
        if (action === 'saltar' && reducedMotion.matches) elapsed = .90;
        pose();
        zoom(false);
        playback(action !== 'reposo' && !reducedMotion.matches);
    });
    pause.addEventListener('click', () => playback(!playing));
    document.addEventListener('visibilitychange', () => playback(playing));
    function zoom(value) {
        close = value;
        controls.querySelector('[data-vista="zoom"]').textContent = close ? 'Ver cuerpo' : 'Ver rostro';
        render();
    }
    controls.addEventListener('click', event => {
        const action = event.target.dataset.vista;
        if (action==='zoom') zoom(!close);
        if (action==='izquierda' || action==='derecha') {
            figure.rotation.y += (action==='izquierda' ? -1 : 1)*Math.PI/6;
            render();
        }
    });
    for (const tab of tabs) tab.addEventListener('click', () => zoom(tab.dataset.seccion==='Apariencia'));
    let previousX;
    const viewport = renderer.domElement;
    viewport.style.touchAction='pan-y';
    viewport.addEventListener('pointerdown', e => { previousX=e.clientX; viewport.setPointerCapture(e.pointerId); });
    viewport.addEventListener('lostpointercapture', () => { previousX=undefined; });
    viewport.addEventListener('pointermove', e => {
        if (previousX === undefined) return;
        figure.rotation.y += (e.clientX-previousX)*.012;
        previousX=e.clientX;
        render();
    });
    form.addEventListener('change', dress);
    new ResizeObserver(render).observe(host);
    viewport.addEventListener('webglcontextlost', () => {
        playback(false);
        rehearsal.hidden=true;
        canvas.hidden=false;
        viewport.hidden=true;
        controls.hidden=true;
    });
})();
