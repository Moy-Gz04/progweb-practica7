// Directorio de usuarios: mapa, filtros, modales, validaciones, confirmaciones
// y consumo de las APIs (pais y clima).
// Altas/bajas/cambios: navegador -> ajax.php -> SOAP -> MySQL.
(() => {
    'use strict';

    const $ = (sel, raiz = document) => raiz.querySelector(sel);
    const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));
    const csrf = $('meta[name="csrf"]').content;

    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
    const norm = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

    const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

    // Espera a que un modal termine de cerrarse
    const cerrar = (id) => new Promise((ok) => {
        const el = document.getElementById(id);
        if (!el.classList.contains('show')) return ok();
        el.addEventListener('hidden.bs.modal', () => ok(), { once: true });
        modal(id).hide();
    });

    // ---------------------------------------------------------- avisos
    function aviso(tipo, mensaje) {
        $('#alertas').innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${esc(mensaje)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>`;
    }

    // Mensaje que sobrevive a la recarga de la pagina despues de guardar
    try {
        const flash = sessionStorage.getItem('flash');
        if (flash) {
            const { tipo, mensaje } = JSON.parse(flash);
            sessionStorage.removeItem('flash');
            aviso(tipo, mensaje);
        }
    } catch (e) { /* sin sessionStorage: no pasa nada */ }

    // ---------------------------------------------------- confirmacion
    // Regresa una promesa: true si el usuario acepta, false si cancela.
    function confirmar({ titulo, html, boton, clase }) {
        return new Promise((resolver) => {
            const el = $('#modalConfirmar');
            const ok = $('#confOk');
            let acepto = false;

            $('#confTitulo').textContent = titulo;
            $('#confCuerpo').innerHTML = html;
            ok.textContent = boton || 'Aceptar';
            ok.className = 'btn ' + (clase || 'btn-primary');

            const alAceptar = () => { acepto = true; modal('modalConfirmar').hide(); };
            ok.addEventListener('click', alAceptar, { once: true });
            el.addEventListener('hidden.bs.modal', () => {
                ok.removeEventListener('click', alAceptar);
                resolver(acepto);
            }, { once: true });

            modal('modalConfirmar').show();
        });
    }

    const dato = (etiqueta, valor) => `<dt>${esc(etiqueta)}</dt><dd>${esc(valor)}</dd>`;

    function resumenUsuario(u) {
        return `<dl class="datos">
            ${dato('Clave', u.cve)}
            ${dato('Nombre', u.nombre)}
            ${dato('Usuario', u.usuario)}
            ${dato('Correo', u.correo)}
            ${dato('Teléfono', u.telefono)}
            ${dato('Rol', u.rol)}
            ${dato('País', u.pais)}
            ${dato('Coordenadas', `${u.latitud}, ${u.longitud}`)}
        </dl>`;
    }

    // ---------------------------------------------------------- envio
    async function enviar(datos) {
        datos.append('csrf', csrf);
        try {
            const r = await fetch('ajax.php', { method: 'POST', body: datos });
            return await r.json();
        } catch (e) {
            return { ok: false, mensaje: 'No se pudo conectar con el servidor.' };
        }
    }

    function recargarConAviso(tipo, mensaje) {
        try { sessionStorage.setItem('flash', JSON.stringify({ tipo, mensaje })); } catch (e) { /* nada */ }
        location.reload();
    }

    // ------------------------------------- filas, filtros y mapa
    const filas = $$('#tablaUsuarios tbody tr[data-usuario]');
    filas.forEach((f) => { f._u = JSON.parse(f.dataset.usuario); });

    const resumen = $('#resumen');
    const totalUsuarios = filas.length;
    const totalPaises = Number(resumen.dataset.paises);

    let mapa = null;
    let ubicacion = null;      // {k, pais, num, cves:Set} cuando se hace clic en un pin

    function aplicarFiltros() {
        const q = norm($('#buscar').value);
        const visibles = new Set();

        filas.forEach((f) => {
            const u = f._u;
            const coincideTexto = !q || norm(f.textContent).includes(q);
            const coincideUbicacion = !ubicacion || ubicacion.cves.has(Number(u.cve));
            const ver = coincideTexto && coincideUbicacion;
            f.classList.toggle('d-none', !ver);
            if (ver) visibles.add(Number(u.cve));
        });

        $('#filaVacia').hidden = !(totalUsuarios > 0 && visibles.size === 0);

        const hayFiltro = !!q || !!ubicacion;
        resumen.textContent = hayFiltro
            ? `${visibles.size} de ${plural(totalUsuarios, 'usuario', 'usuarios')}.`
            : `${plural(totalUsuarios, 'usuario', 'usuarios')} en ${plural(totalPaises, 'país', 'países')}. Toca un pin para ver quién está ahí.`;

        const barra = $('#filtroActivo');
        barra.hidden = !ubicacion;
        if (ubicacion) $('#filtroTexto').textContent = `${ubicacion.pais} (${plural(ubicacion.cves.size, 'usuario', 'usuarios')})`;

        if (mapa) mapa.actualizar({ visibles, activo: ubicacion && ubicacion.k, num: ubicacion && ubicacion.num });
    }

    $('#buscar').addEventListener('input', aplicarFiltros);
    $('#quitarFiltro').addEventListener('click', () => { ubicacion = null; aplicarFiltros(); });

    // Lectura (HUD) bajo el globo: pais, coordenadas y usuarios del pin bajo el cursor
    const hudTexto = $('#hudTexto');
    const hudInicial = hudTexto ? hudTexto.textContent : '';
    function hud(g) {
        if (!hudTexto) return;
        hudTexto.innerHTML = g
            ? `<strong>${esc(g.pais)}</strong><span>${g.lat.toFixed(4)}, ${g.lon.toFixed(4)}</span><span>${plural(g.usuarios.length, 'usuario', 'usuarios')}</span>`
            : esc(hudInicial);
    }

    // El mapa se dibuja con los mismos usuarios que el reporte
    try {
        const datosMapa = JSON.parse($('#datosMapa').textContent);
        Mapa.init({
            contenedor: '#mapa',
            modo: $('#mapa').dataset.modo,
            usuarios: datosMapa,
            alMirar: hud,
            alElegir: (g) => {
                // Un segundo clic en el mismo pin quita el filtro
                ubicacion = (ubicacion && ubicacion.k === g.k)
                    ? null
                    : { k: g.k, pais: g.pais, num: g.num, cves: new Set(g.usuarios.map((u) => Number(u.cve))) };
                aplicarFiltros();
                if (ubicacion) {
                    if (mapa) mapa.enfocar(g);          // el globo gira hasta ese pin
                    $('.tabla-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
        }).then((m) => { mapa = m; aplicarFiltros(); });
    } catch (e) { /* sin mapa el directorio sigue funcionando */ }


    // Al pasar sobre una fila se resalta su pin
    const cuerpo = $('#tablaUsuarios tbody');
    cuerpo.addEventListener('mouseover', (e) => {
        const f = e.target.closest('tr[data-usuario]');
        if (mapa) mapa.destacar(f ? f._u.cve : null);
    });
    cuerpo.addEventListener('mouseleave', () => { if (mapa) mapa.destacar(null); });

    // ------------------------------------------- registrar / modificar
    const form = $('#formUsuario');
    const campo = (id) => $('#' + id);

    function prepararFormulario(u) {
        const editar = !!u;
        form.reset();
        form.classList.remove('was-validated');
        $('#errorUsuario').classList.add('d-none');

        campo('fAccion').value = editar ? 'upd' : 'ins';
        campo('fCve').value = editar ? u.cve : 0;
        $('#tituloUsuario').textContent = editar ? `Modificar usuario #${u.cve}` : 'Registrar usuario';
        $('#btnGuardar').textContent = editar ? 'Guardar cambios' : 'Registrar';

        // En modificacion la contrasena es opcional
        campo('fPwd').required = !editar;
        $('#ayudaPwd').classList.toggle('d-none', !editar);

        if (editar) {
            campo('fNom').value = u.nom;
            campo('fPat').value = u.pat;
            campo('fMat').value = u.mat;
            campo('fCor').value = u.correo;
            campo('fTel').value = u.telefono;
            campo('fUsu').value = u.usuario;
            campo('fRol').value = u.rol_cve;
            campo('fPais').value = u.pais;
            campo('fLat').value = u.latitud;
            campo('fLon').value = u.longitud;
        }
    }

    // Al elegir pais se proponen las coordenadas de su ciudad de referencia
    campo('fPais').addEventListener('change', (e) => {
        const op = e.target.selectedOptions[0];
        if (op && op.dataset.lat) {
            campo('fLat').value = op.dataset.lat;
            campo('fLon').value = op.dataset.lon;
        }
    });

    $('#btnNuevo').addEventListener('click', () => {
        prepararFormulario(null);
        modal('modalUsuario').show();
    });

    cuerpo.addEventListener('click', (e) => {
        const boton = e.target.closest('button');
        if (!boton) return;
        const u = boton.closest('tr')._u;

        if (boton.classList.contains('btn-editar')) {
            prepararFormulario(u);
            modal('modalUsuario').show();
        } else if (boton.classList.contains('btn-eliminar')) {
            eliminar(u);
        } else if (boton.classList.contains('btn-pais')) {
            consultarPais(u);
        } else if (boton.classList.contains('btn-clima')) {
            consultarClima(u);
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.classList.add('was-validated');
        if (!form.checkValidity()) return;

        const editar = campo('fAccion').value === 'upd';
        const datos = new FormData(form);
        const nombre = [campo('fNom').value, campo('fPat').value, campo('fMat').value].join(' ').replace(/\s+/g, ' ').trim();
        const rolTxt = campo('fRol').selectedOptions[0].textContent;

        const resumenCambio = `<p class="mb-3">${editar ? '¿Guardar los cambios de este usuario?' : '¿Registrar este usuario nuevo?'}</p>
            <dl class="datos">
                ${dato('Nombre', nombre)}
                ${dato('Usuario', campo('fUsu').value)}
                ${dato('Correo', campo('fCor').value)}
                ${dato('Teléfono', campo('fTel').value)}
                ${dato('Rol', rolTxt)}
                ${dato('País', campo('fPais').value)}
                ${dato('Coordenadas', `${campo('fLat').value}, ${campo('fLon').value}`)}
                ${editar && campo('fPwd').value ? dato('Contraseña', 'Se actualizará') : ''}
            </dl>`;

        // Se cierra el formulario, se pide confirmacion y, si cancela, se regresa a el
        await cerrar('modalUsuario');
        const acepto = await confirmar({
            titulo: editar ? 'Confirmar modificación' : 'Confirmar registro',
            html: resumenCambio,
            boton: editar ? 'Sí, guardar cambios' : 'Sí, registrar',
        });

        if (!acepto) {
            modal('modalUsuario').show();
            return;
        }

        const r = await enviar(datos);
        if (r.ok) {
            recargarConAviso('success', r.mensaje);
        } else {
            const caja = $('#errorUsuario');
            caja.textContent = r.mensaje;
            caja.classList.remove('d-none');
            modal('modalUsuario').show();
        }
    });

    // ---------------------------------------------------------- baja
    async function eliminar(u) {
        const acepto = await confirmar({
            titulo: 'Eliminar usuario',
            html: `<p class="mb-3 aviso-peligro">Esta acción no se puede deshacer. ¿Eliminar al siguiente usuario?</p>${resumenUsuario(u)}`,
            boton: 'Sí, eliminar',
            clase: 'btn-danger',
        });
        if (!acepto) return;

        const datos = new FormData();
        datos.append('accion', 'del');
        datos.append('cve', u.cve);
        const r = await enviar(datos);

        if (r.ok) recargarConAviso('success', r.mensaje);
        else aviso('danger', r.mensaje);
    }

    // ---------------------------------------------------------- pais
    const carga = (texto) => `<div class="carga"><div class="spinner-border" role="status"></div><p>${esc(texto)}</p></div>`;
    const errorTarjeta = (texto) => `<div class="alert alert-danger mb-0">${esc(texto)}</div>`;

    // Nombres en espanol con Intl (si el navegador no los trae, queda el de la API)
    function nombreIntl(tipo, codigo, alterno) {
        try {
            const n = new Intl.DisplayNames(['es'], { type: tipo }).of(codigo);
            return n && n !== codigo ? n : alterno;
        } catch (e) { return alterno; }
    }
    const REGIONES = { Americas: 'América', Europe: 'Europa', Asia: 'Asia', Africa: 'África', Oceania: 'Oceanía', Antarctic: 'Antártida' };

    async function consultarPais(u) {
        $('#tituloPais').textContent = `País de ${u.nombre}`;
        $('#cuerpoPais').innerHTML = carga(`Consultando ${u.pais}…`);
        modal('modalPais').show();

        try {
            // La API busca mejor por nombre en ingles; cada pais del combo lo trae en data-api
            const op = $$('#fPais option').find((o) => o.value === u.pais);
            const consulta = (op && op.dataset.api) || u.pais;

            const r = await fetch('../apiweb/consultaPais.php?pais=' + encodeURIComponent(consulta));
            const j = await r.json();
            if (!r.ok || j.error) throw new Error(j.error || 'Error al consultar el país');

            const lista = (j.data && j.data.objects) || [];
            // De varias coincidencias se prefiere la que se llama exactamente igual
            const nombreIgual = (o) => [
                o.names && o.names.common,
                o.names && o.names.native && o.names.native.spa && o.names.native.spa.common,
                o.names && o.names.translations && o.names.translations.spa && o.names.translations.spa.common,
            ].some((n) => n && (norm(n) === norm(u.pais) || norm(n) === norm(consulta)));
            const p = lista.find(nombreIgual) || lista[0];
            if (!p) throw new Error('No se encontró información de ' + u.pais);

            const nombreEs = (p.names.translations && p.names.translations.spa && p.names.translations.spa.common) || p.names.common;
            const capital = (p.capitals && p.capitals[0] && p.capitals[0].name) || '—';
            const monedas = (p.currencies || [])
                .map((m) => `${nombreIntl('currency', m.code, m.name)} (${m.code}${m.symbol ? ', ' + m.symbol : ''})`).join(', ') || '—';
            const idiomas = (p.languages || [])
                .map((i) => nombreIntl('language', i.iso639_1 || i.bcp47 || i.iso639_3, i.name)).join(', ') || '—';
            const poblacion = typeof p.population === 'number' ? p.population.toLocaleString('es-MX') + ' habitantes' : '—';
            const region = REGIONES[p.region] || p.region || '—';
            const aqui = filas.filter((f) => f._u.pais === u.pais).length;

            const iso = op && op.dataset.iso;
            const bandera = iso ? `assets/flags/${iso}.svg` : (p.flag && p.flag.url_png);

            $('#cuerpoPais').innerHTML = `
                <img class="bandera" src="${esc(bandera)}" alt="Bandera de ${esc(nombreEs)}">
                <h3 class="pais-nombre">${esc(nombreEs)}</h3>
                <p class="pais-oficial">${esc(p.names.official)}</p>
                <dl class="datos">
                    ${dato('Capital', capital)}
                    ${dato('Población', poblacion)}
                    ${dato('Moneda', monedas)}
                    ${dato('Idiomas', idiomas)}
                    ${dato('Región', region)}
                    ${dato('En este directorio', plural(aqui, 'usuario', 'usuarios'))}
                </dl>`;
        } catch (err) {
            $('#cuerpoPais').innerHTML = errorTarjeta(err.message);
        }
    }

    // --------------------------------------------------------- clima
    // El cielo de la tarjeta cambia con las condiciones reales
    function temaClima(c) {
        const k = Number(c.codigo);
        if (k >= 95) return 'tormenta';
        if ((k >= 71 && k <= 77) || k === 85 || k === 86) return 'nieve';
        if ((k >= 51 && k <= 67) || (k >= 80 && k <= 82)) return 'lluvia';
        if (k === 45 || k === 48) return 'niebla';
        if (!c.es_dia) return 'noche';
        if (k <= 1) return 'soleado';
        return 'nublado';
    }

    async function consultarClima(u) {
        $('#tituloClima').textContent = `Clima en ${u.pais}`;
        $('#cuerpoClima').innerHTML = carga('Consultando el clima…');
        modal('modalClima').show();

        try {
            const url = `../apiweb/consultaClima.php?lat=${encodeURIComponent(u.latitud)}&lon=${encodeURIComponent(u.longitud)}`;
            const r = await fetch(url);
            const c = await r.json();
            if (!r.ok || c.error) throw new Error(c.error || 'Error al consultar el clima');

            const hora = String(c.hora).split('T')[1] || '';
            $('#cuerpoClima').innerHTML = `
                <div class="clima clima-${temaClima(c)}">
                    <div class="icono-clima">${c.icono}</div>
                    <div class="clima-temp">${esc(Math.round(c.temperatura))}<span>${esc(c.unidades.temperatura)}</span></div>
                    <div class="clima-cond">${esc(c.condicion)}</div>
                    <div class="clima-hora">${c.es_dia ? 'De día' : 'De noche'}, ${esc(hora)} hora local</div>
                </div>
                <dl class="datos">
                    ${dato('Temperatura', c.temperatura + ' ' + c.unidades.temperatura)}
                    ${dato('Humedad relativa', c.humedad + ' ' + c.unidades.humedad)}
                    ${dato('Sensación térmica', c.sensacion + ' ' + c.unidades.temperatura)}
                    ${dato('Viento', c.viento + ' ' + c.unidades.viento)}
                    ${dato('Usuario', u.nombre)}
                    ${dato('Coordenadas', `${u.latitud}, ${u.longitud}`)}
                </dl>`;
        } catch (err) {
            $('#cuerpoClima').innerHTML = errorTarjeta(err.message);
        }
    }
})();
