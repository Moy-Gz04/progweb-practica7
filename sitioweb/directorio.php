<?php
session_start();
require_once 'soap.php';
exigir_sesion();

// Los datos salen del servicio SOAP (vista vwrptusuarios y catalogo de roles)
$usuarios = soap_llamar('vwRptUsuarios');
$roles    = soap_llamar('rptRoles');

$errorServicio = '';
if (es_error($usuarios))
{
    $errorServicio = $usuarios[0]['MENSAJE'] ?? 'No se pudo consultar el directorio.';
    $usuarios = array();
}
if (es_error($roles))
{
    $roles = array();
}

$paises = lista_paises();

// Datos minimos para el mapa (un pin por ubicacion)
$datosMapa = array();
foreach ($usuarios as $u)
{
    $info = info_pais($u['pais']);
    $datosMapa[] = array(
        'cve'      => (int)$u['cve'],
        'nombre'   => $u['nombre'],
        'pais'     => $u['pais'],
        'latitud'  => (float)$u['latitud'],
        'longitud' => (float)$u['longitud'],
        'num'      => $info ? $info['num'] : null,
    );
}
$totalPaises = count(array_unique(array_column($usuarios, 'pais')));
$tema = tema_actual();
?>
<!DOCTYPE html>
<html lang="es" data-tema="<?= h($tema) ?>" data-bs-theme="<?= $tema === 'futurista' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= h($_SESSION['csrf'] ?? '') ?>">
    <title>Directorio de usuarios · Gómez Aispuro</title>
    <link href="assets/fonts/fuentes.css" rel="stylesheet">
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilo.css?v=2" rel="stylesheet">
<?php if ($tema === 'futurista') { ?>    <link href="assets/futurista.css" rel="stylesheet">
<?php } ?></head>
<body class="pagina">

<header class="barra">
    <a class="marca" href="directorio.php">
        <svg class="marca-icono" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M2 12h20M12 2c3 3.2 3 16.8 0 20M12 2c-3 3.2-3 16.8 0 20" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>
        Atlas
    </a>
    <div class="sesion">
        <span class="sesion-nombre"><?= h($_SESSION['nombre']) ?></span>
        <span class="chip-rol <?= h(clase_rol($_SESSION['rol'])) ?>"><?= h($_SESSION['rol']) ?></span>
        <a class="btn-salir" href="login.php?salir=1">Salir</a>
    </div>
</header>

<main class="contenedor">

    <div id="alertas"></div>

    <?php if ($errorServicio !== '') { ?>
        <div class="alert alert-danger"><?= h($errorServicio) ?></div>
    <?php } ?>

    <div class="hero">
    <section class="cabecera">
        <div>
            <div class="titulo-fila">
                <h1 class="titulo">Directorio de usuarios</h1>
                <a href="reportePdf.php" target="_blank" rel="noopener" class="btn-pdf" id="btnPdf"
                   title="Abrir vista imprimible en otra pestaña">Reporte</a>
            </div>
            <p class="resumen" id="resumen"
               data-total="<?= count($usuarios) ?>" data-paises="<?= $totalPaises ?>">
                <?= count($usuarios) ?> usuarios en <?= $totalPaises ?> países. Toca un pin para ver quién está ahí.
            </p>
        </div>
        <div class="acciones-cabecera">
            <input type="search" id="buscar" class="buscador" placeholder="Buscar por nombre, país o rol"
                   aria-label="Buscar en el directorio">
            <button type="button" class="btn-principal" id="btnNuevo">Nuevo usuario</button>
        </div>
    </section>

    <section class="mapa-panel" aria-label="Mapa con la ubicación de los usuarios">
        <div id="mapa" class="mapa" data-modo="<?= $tema === 'futurista' ? 'globo' : 'plano' ?>"></div>
        <div id="filtroActivo" class="filtro-activo" hidden>
            <span>Mostrando <strong id="filtroTexto"></strong></span>
            <button type="button" id="quitarFiltro">Quitar filtro</button>
        </div>
    </section>
    </div>

    <section class="tabla-panel">
        <div class="table-responsive">
            <table class="tabla" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>País</th>
                        <th>Coordenadas (lat, lon)</th>
                        <th class="texto-der">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u) { $info = info_pais($u['pais']); ?>
                    <tr data-usuario="<?= h(json_encode($u, JSON_UNESCAPED_UNICODE)) ?>">
                        <td class="clave"><?= h($u['cve']) ?></td>
                        <td>
                            <div class="persona">
                                <span class="avatar <?= h(clase_rol($u['rol'])) ?>"><?= h(iniciales($u['nom'], $u['pat'])) ?></span>
                                <span class="persona-nombre"><?= h($u['nombre']) ?></span>
                            </div>
                        </td>
                        <td><?= h($u['usuario']) ?></td>
                        <td><?= h($u['correo']) ?></td>
                        <td class="num"><?= h($u['telefono']) ?></td>
                        <td><span class="chip-rol <?= h(clase_rol($u['rol'])) ?>"><?= h($u['rol']) ?></span></td>
                        <td>
                            <span class="pais-celda">
                                <?php if ($info) { ?><img class="bandera-mini" src="assets/flags/<?= h($info['iso2']) ?>.svg" alt="" width="22" height="16"><?php } ?>
                                <?= h($u['pais']) ?>
                            </span>
                        </td>
                        <td class="num coords"><span><?= h(number_format((float)$u['latitud'], 6)) ?>,</span> <span><?= h(number_format((float)$u['longitud'], 6)) ?></span></td>
                        <td class="texto-der">
                            <div class="acciones">
                                <button type="button" class="act btn-pais">País</button>
                                <button type="button" class="act btn-clima">Clima</button>
                                <button type="button" class="act btn-editar">Editar</button>
                                <button type="button" class="act act-peligro btn-eliminar">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                    <tr id="filaVacia" <?= $usuarios ? 'hidden' : '' ?>>
                        <td colspan="9" class="vacio">
                            <?= $usuarios ? 'Ningún usuario coincide con la búsqueda.' : 'Todavía no hay usuarios. Usa “Nuevo usuario” para registrar el primero.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<footer class="pie">© 2026 Juan Moisés Gómez Aispuro · Ingeniería en Sistemas Computacionales · TecNM Pachuca · No. de control 22200930</footer>

<script type="application/json" id="datosMapa"><?= json_encode($datosMapa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>


<!-- ============ MODAL: REGISTRAR / MODIFICAR ============ -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="tituloUsuario" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content needs-validation" id="formUsuario" novalidate autocomplete="off">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloUsuario">Registrar usuario</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="errorUsuario" class="alert alert-danger d-none py-2"></div>

                <input type="hidden" id="fAccion" name="accion" value="ins">
                <input type="hidden" id="fCve" name="cve" value="0">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="fNom" class="form-label">Nombre(s)</label>
                        <input type="text" class="form-control" id="fNom" name="nom" maxlength="30" required>
                        <div class="invalid-feedback">Escribe el nombre.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fPat" class="form-label">Apellido paterno</label>
                        <input type="text" class="form-control" id="fPat" name="pat" maxlength="30" required>
                        <div class="invalid-feedback">Escribe el apellido paterno.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fMat" class="form-label">Apellido materno</label>
                        <input type="text" class="form-control" id="fMat" name="mat" maxlength="30">
                    </div>

                    <div class="col-md-6">
                        <label for="fCor" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="fCor" name="cor" maxlength="50" required>
                        <div class="invalid-feedback">Escribe un correo válido.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="fTel" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="fTel" name="tel" maxlength="15"
                               pattern="[0-9 \(\)+\-]{7,15}" required>
                        <div class="invalid-feedback">Solo números, espacios, guiones o paréntesis (7 a 15).</div>
                    </div>

                    <div class="col-md-4">
                        <label for="fUsu" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="fUsu" name="usu" maxlength="15"
                               pattern="[A-Za-z0-9_.]{4,15}" required>
                        <div class="invalid-feedback">De 4 a 15 caracteres: letras, números, punto o guion bajo.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fPwd" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="fPwd" name="pwd" maxlength="15"
                               minlength="6" autocomplete="new-password" required>
                        <div class="form-text d-none" id="ayudaPwd">Déjala vacía para conservar la actual.</div>
                        <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fRol" class="form-label">Rol</label>
                        <select class="form-select" id="fRol" name="rol" required>
                            <option value="">Selecciona…</option>
                            <?php foreach ($roles as $r) { ?>
                                <option value="<?= h($r['cve']) ?>"><?= h($r['nombre']) ?></option>
                            <?php } ?>
                        </select>
                        <div class="invalid-feedback">Selecciona un rol.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="fPais" class="form-label">País</label>
                        <select class="form-select" id="fPais" name="pais" required>
                            <option value="">Selecciona…</option>
                            <?php foreach ($paises as $p) { ?>
                                <option value="<?= h($p[0]) ?>" data-lon="<?= h($p[1]) ?>" data-lat="<?= h($p[2]) ?>" data-api="<?= h($p[3]) ?>" data-iso="<?= h($p[4]) ?>"><?= h($p[0]) ?></option>
                            <?php } ?>
                        </select>
                        <div class="invalid-feedback">Selecciona el país.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fLat" class="form-label">Latitud</label>
                        <input type="number" class="form-control" id="fLat" name="lat" step="any" min="-90" max="90" required>
                        <div class="invalid-feedback">Entre -90 y 90.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fLon" class="form-label">Longitud</label>
                        <input type="number" class="form-control" id="fLon" name="lon" step="any" min="-180" max="180" required>
                        <div class="invalid-feedback">Entre -180 y 180.</div>
                    </div>
                    <div class="col-12 form-text mt-0">Al elegir el país se llenan las coordenadas de su ciudad de referencia; puedes cambiarlas.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ MODAL: CONFIRMACION (alta, cambios, baja) ============ -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="confTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="confTitulo">Confirmar</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="confCuerpo"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confOk">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: PAIS (REST Countries) ============ -->
<div class="modal fade" id="modalPais" tabindex="-1" aria-labelledby="tituloPais" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloPais">País</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoPais"></div>
            <div class="modal-footer fuente">
                <span>Datos de REST Countries</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: CLIMA (Open-Meteo) ============ -->
<div class="modal fade" id="modalClima" tabindex="-1" aria-labelledby="tituloClima" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloClima">Clima</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoClima"></div>
            <div class="modal-footer fuente">
                <span>Datos de Open-Meteo</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/d3.min.js"></script>
<script src="assets/vendor/topojson-client.min.js"></script>
<script src="assets/mapa.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
