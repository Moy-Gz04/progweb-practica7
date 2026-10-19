// Mapa de usuarios: un pin por ubicacion (latitud / longitud).
// Dos modos, con los mismos datos y la misma API:
//   modo 'plano'  -> mapamundi (tema claro "Atlas")
//   modo 'globo'  -> globo 3D que gira solo y se arrastra (tema futurista)
// Usa d3 + topojson locales (assets/vendor) para que funcione sin CDN.
//
//   const mapa = await Mapa.init({
//       contenedor: '#mapa', modo: 'globo' | 'plano',
//       usuarios:   [{cve, nombre, pais, latitud, longitud, num}],
//       interactivo: true,
//       alElegir:   (grupo) => {},          // clic en un pin
//       alMirar:    (grupo|null) => {}      // el cursor / foco entra o sale de un pin
//   });
//   mapa.destacar(cve);  mapa.actualizar({visibles:Set, activo:'lat,lon', num:484});
//   mapa.enfocar(grupo); // en el globo, lo hace girar hasta ese pin
window.Mapa = (() => {
    'use strict';

    const reducirMovimiento = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    // Un pin por ubicacion; si varios usuarios comparten coordenadas se agrupan
    function agrupar(usuarios) {
        const grupos = new Map();
        (usuarios || []).forEach((u) => {
            const lat = Number(u.latitud);
            const lon = Number(u.longitud);
            if (!isFinite(lat) || !isFinite(lon)) return;
            const k = lat.toFixed(3) + ',' + lon.toFixed(3);
            if (!grupos.has(k)) grupos.set(k, { k, lat, lon, num: Number(u.num) || null, pais: u.pais, usuarios: [] });
            grupos.get(k).usuarios.push(u);
        });
        return [...grupos.values()];
    }

    // Tooltip HTML posicionado sobre el panel que contiene al mapa
    function crearTooltip(cont) {
        const marco = cont.closest('.mapa-panel') || cont;
        let tip = marco.querySelector('.tooltip-mapa');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'tooltip-mapa';
            tip.setAttribute('role', 'tooltip');
            marco.appendChild(tip);
        }
        return {
            mostrar(nodo, d) {
                const nombres = d.usuarios.slice(0, 4).map((u) => `<li>${esc(u.nombre)}</li>`).join('');
                const mas = d.usuarios.length > 4 ? `<li class="mas">y ${d.usuarios.length - 4} más</li>` : '';
                tip.innerHTML = `<strong>${esc(d.pais)}</strong><ul>${nombres}${mas}</ul>`;
                const a = nodo.getBoundingClientRect();
                const m = marco.getBoundingClientRect();
                tip.style.left = `${a.left - m.left + a.width / 2}px`;
                tip.style.top = `${a.top - m.top}px`;
                tip.classList.add('visible');
            },
            ocultar() { tip.classList.remove('visible'); },
        };
    }

    // Eventos de los pines (raton, teclado y foco)
    function conectarPines(pins, ctx, extra) {
        const { op, tip } = ctx;
        pins
            .attr('tabindex', 0)
            .attr('role', 'button')
            .attr('aria-label', (d) => `${d.pais}: ${d.usuarios.length} usuario${d.usuarios.length === 1 ? '' : 's'}. Filtrar el directorio.`)
            .on('mouseenter', function (e, d) { tip.mostrar(this, d); if (op.alMirar) op.alMirar(d); if (extra && extra.entra) extra.entra(); })
            .on('focus', function (e, d) { tip.mostrar(this, d); if (op.alMirar) op.alMirar(d); })
            .on('mouseleave', () => { tip.ocultar(); if (op.alMirar) op.alMirar(null); if (extra && extra.sale) extra.sale(); })
            .on('blur', () => { tip.ocultar(); if (op.alMirar) op.alMirar(null); })
            .on('click', (e, d) => { if (op.alElegir) op.alElegir(d); })
            .on('keydown', (e, d) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (op.alElegir) op.alElegir(d);
                }
            });
    }

    // Marcado comun de un pin: zona de clic, anillo y cuerpo
    function dibujarPines(contenedorSvg, grupos, radio) {
        const pins = contenedorSvg.append('g').attr('class', 'pins')
            .selectAll('g').data(grupos).join('g').attr('class', 'pin');

        pins.append('circle').attr('class', 'pin-zona').attr('r', radio.zona);
        pins.append('circle').attr('class', 'pin-anillo').attr('r', radio.anillo);

        const cuerpo = pins.append('g').attr('class', 'pin-cuerpo')
            .style('animation-delay', (d, i) => `${250 + i * 80}ms`);
        cuerpo.append('circle').attr('class', 'pin-punto').attr('r', (d) => (d.usuarios.length > 1 ? radio.grupo : radio.solo));
        cuerpo.filter((d) => d.usuarios.length > 1)
            .append('text').attr('class', 'pin-num').attr('dy', '0.35em')
            .text((d) => d.usuarios.length);
        return pins;
    }

    // ------------------------------------------------------------- plano
    function plano(ctx) {
        const { cont, paises, grupos, conUsuarios, op, interactivo } = ctx;
        const W = 1000;
        const H = 500;

        const proyeccion = d3.geoNaturalEarth1()
            .fitExtent([[6, 6], [W - 6, H - 6]], { type: 'FeatureCollection', features: paises });
        const trazo = d3.geoPath(proyeccion);

        const svg = d3.select(cont).append('svg')
            .attr('viewBox', `0 0 ${W} ${H}`)
            .attr('preserveAspectRatio', 'xMidYMid meet')
            .attr('focusable', 'false');

        svg.append('path').attr('class', 'mar').attr('d', trazo({ type: 'Sphere' }));
        svg.append('path').attr('class', 'reticula').attr('d', trazo(d3.geoGraticule().step([30, 30])()));

        const tierra = svg.append('g').attr('class', 'tierra')
            .selectAll('path').data(paises).join('path')
            .attr('class', (f) => 'pais' + (conUsuarios.has(Number(f.id)) ? ' con-usuarios' : ''))
            .attr('d', trazo);

        const pins = dibujarPines(svg, grupos, { zona: 20, anillo: 11, solo: 7, grupo: 12 })
            .attr('transform', (d) => `translate(${proyeccion([d.lon, d.lat]).join(',')})`);

        if (interactivo) conectarPines(pins, ctx);
        else pins.style('pointer-events', 'none');

        return {
            destacar(cve) {
                pins.classed('pin-destacado', (d) => cve != null && d.usuarios.some((u) => Number(u.cve) === Number(cve)));
            },
            actualizar({ visibles, activo, num }) {
                pins.classed('pin-apagado', (d) => !d.usuarios.some((u) => visibles.has(Number(u.cve))));
                pins.classed('pin-activo', (d) => activo != null && d.k === activo);
                tierra.classed('pais-activo', (f) => num != null && Number(f.id) === Number(num));
            },
            enfocar() { /* el mapa plano ya muestra todo */ },
        };
    }

    // ------------------------------------------------------------- globo
    function globo(ctx) {
        const { cont, paises, grupos, conUsuarios, op, interactivo } = ctx;
        const S = 600;          // lienzo cuadrado
        const R = 255;          // radio de la esfera

        const proy = d3.geoOrthographic().scale(R).translate([S / 2, S / 2]).clipAngle(90).precision(0.6);
        const trazo = d3.geoPath(proy);
        const reticula = d3.geoGraticule().step([30, 30])();

        const svg = d3.select(cont).append('svg')
            .attr('viewBox', `0 0 ${S} ${S}`)
            .attr('preserveAspectRatio', 'xMidYMid meet')
            .attr('class', 'globo')
            .attr('focusable', 'false');

        // Esfera con volumen: luz arriba a la izquierda
        const defs = svg.append('defs');
        const grad = defs.append('radialGradient').attr('id', 'gl-esfera').attr('cx', '36%').attr('cy', '30%').attr('r', '80%');
        grad.append('stop').attr('offset', '0%').attr('stop-color', '#1a2a63');
        grad.append('stop').attr('offset', '60%').attr('stop-color', '#0a1236');
        grad.append('stop').attr('offset', '100%').attr('stop-color', '#050816');

        svg.append('circle').attr('class', 'globo-orbita').attr('cx', S / 2).attr('cy', S / 2).attr('r', R + 22);
        svg.append('circle').attr('class', 'globo-halo').attr('cx', S / 2).attr('cy', S / 2).attr('r', R + 6);
        svg.append('circle').attr('class', 'globo-esfera').attr('cx', S / 2).attr('cy', S / 2).attr('r', R).attr('fill', 'url(#gl-esfera)');
        const retic = svg.append('path').attr('class', 'reticula');
        const tierra = svg.append('g').attr('class', 'tierra')
            .selectAll('path').data(paises).join('path')
            .attr('class', (f) => 'pais' + (conUsuarios.has(Number(f.id)) ? ' con-usuarios' : ''));

        const pins = dibujarPines(svg, grupos, { zona: 17, anillo: 9, solo: 5, grupo: 10 });

        // Vista inicial: Americas, inclinada un poco hacia el norte
        const destino0 = [-75, 12];
        proy.rotate([-destino0[0] - 110, -destino0[1]]);   // arranca girado y se asienta solo

        const centro = () => { const r = proy.rotate(); return [-r[0], -r[1]]; };

        function dibujar() {
            retic.attr('d', trazo(reticula));
            tierra.attr('d', trazo);
            const c = centro();
            pins.each(function (d) {
                const p = proy([d.lon, d.lat]);
                const visible = d3.geoDistance([d.lon, d.lat], c) < Math.PI / 2 - 0.03;
                this.setAttribute('transform', `translate(${p[0].toFixed(1)},${p[1].toFixed(1)})`);
                this.classList.toggle('pin-oculto', !visible);
            });
        }

        // ---- animacion: giro lento, entrada y enfoque a un pin ----
        let pausado = false;
        let arrastrando = false;
        let anim = null;                        // {de, a, t0, dur}
        let ultimo = performance.now();
        let ultimoDibujo = 0;
        const GRADOS_POR_SEG = 6;

        const suave = d3.easeCubicInOut;
        const angulo = (d) => ((d + 540) % 360) - 180;   // diferencia mas corta entre longitudes

        function irA(lon, lat, dur) {
            const r = proy.rotate();
            anim = { de: [r[0], r[1]], a: [-lon, -lat], t0: performance.now(), dur: dur || 1000 };
        }

        // Entrada: del giro inicial al encuadre de las Americas
        if (!reducirMovimiento) irA(destino0[0], destino0[1], 2200);
        else proy.rotate([-destino0[0], -destino0[1]]);

        function cuadro(t) {
            const dt = Math.min(0.1, (t - ultimo) / 1000);
            ultimo = t;

            if (anim) {
                const k = Math.min(1, (t - anim.t0) / anim.dur);
                const e = suave(k);
                const dl = angulo(anim.a[0] - anim.de[0]);
                proy.rotate([anim.de[0] + dl * e, anim.de[1] + (anim.a[1] - anim.de[1]) * e]);
                if (k >= 1) anim = null;
            } else if (!pausado && !arrastrando && !reducirMovimiento && !document.hidden) {
                const r = proy.rotate();
                proy.rotate([r[0] - dt * GRADOS_POR_SEG, r[1]]);
            }

            if (t - ultimoDibujo > 30) {          // ~30 cuadros por segundo bastan
                dibujar();
                ultimoDibujo = t;
            }
            requestAnimationFrame(cuadro);
        }
        dibujar();
        requestAnimationFrame(cuadro);

        // ---- interaccion ----
        if (interactivo) {
            svg.call(d3.drag()
                .on('start', () => { arrastrando = true; anim = null; })
                .on('drag', (e) => {
                    const r = proy.rotate();
                    const k = 0.28;
                    proy.rotate([r[0] + e.dx * k, Math.max(-75, Math.min(75, r[1] - e.dy * k))]);
                    dibujar();
                })
                .on('end', () => { arrastrando = false; }));

            conectarPines(pins, ctx, { entra: () => { pausado = true; }, sale: () => { pausado = false; } });
        } else {
            svg.style('pointer-events', 'none');
        }

        return {
            destacar(cve) {
                pins.classed('pin-destacado', (d) => cve != null && d.usuarios.some((u) => Number(u.cve) === Number(cve)));
            },
            actualizar({ visibles, activo, num }) {
                pins.classed('pin-apagado', (d) => !d.usuarios.some((u) => visibles.has(Number(u.cve))));
                pins.classed('pin-activo', (d) => activo != null && d.k === activo);
                tierra.classed('pais-activo', (f) => num != null && Number(f.id) === Number(num));
            },
            enfocar(g) { if (g) irA(g.lon, g.lat * 0.85, 1100); },
        };
    }

    // -------------------------------------------------------------- init
    async function init(op) {
        const cont = typeof op.contenedor === 'string' ? document.querySelector(op.contenedor) : op.contenedor;
        const base = op.base || 'assets/vendor/';

        let topo;
        try {
            const r = await fetch(base + 'world-110m.json');
            topo = await r.json();
        } catch (e) {
            cont.innerHTML = '<p class="mapa-error">No se pudo cargar el mapa.</p>';
            return null;
        }

        // Sin Antartida: le quita altura al mapa y no aporta nada aqui
        const paises = topojson.feature(topo, topo.objects.countries).features
            .filter((f) => String(f.id).padStart(3, '0') !== '010');

        cont.innerHTML = '';
        const ctx = {
            op,
            cont,
            paises,
            grupos: agrupar(op.usuarios),
            conUsuarios: new Set((op.usuarios || []).map((u) => Number(u.num)).filter(Boolean)),
            interactivo: op.interactivo !== false,
            tip: crearTooltip(cont),
        };

        return op.modo === 'globo' ? globo(ctx) : plano(ctx);
    }

    return { init };
})();
