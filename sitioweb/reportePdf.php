<?php
// Vista imprimible del directorio (para "Guardar como PDF" desde el navegador).
// Sin botones de acciones: solo encabezado, datos y pie de pagina.
session_start();
require_once 'soap.php';
exigir_sesion();

$usuarios = soap_llamar('vwRptUsuarios');
if (es_error($usuarios))
{
    $usuarios = array();
}

$generado = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Directorio de usuarios - <?= h(date('Y-m-d')) ?></title>
    <link href="assets/fonts/fuentes.css" rel="stylesheet">
    <style>
        @page { size: letter landscape; margin: 14mm 12mm 18mm; }

        * { box-sizing: border-box; }
        body { font-family: "Instrument Sans", "Segoe UI", Arial, sans-serif; color: #0f2540; margin: 0; padding: 0 1rem 3rem; font-size: 11.5px; }

        .barra { position: sticky; top: 0; background: #f1f5f9; border-bottom: 1px solid #cbd5e1;
                 margin: 0 -1rem 1rem; padding: .6rem 1rem; display: flex; gap: .5rem; align-items: center; }
        .barra button { font: inherit; padding: .4rem .9rem; border-radius: .35rem; border: 1px solid #94a3b8; background: #fff; cursor: pointer; }
        .barra button.principal { background: #0f2540; color: #fff; border-color: #0f2540; }
        .barra span { color: #475569; margin-left: auto; }

        header.encabezado { display: flex; justify-content: space-between; align-items: flex-end;
                            border-bottom: 3px solid #ff5a36; padding-bottom: .5rem; margin-bottom: .8rem; }
        header.encabezado h1 { font-family: "Bricolage Grotesque", "Segoe UI", sans-serif; font-weight: 800; letter-spacing: -.03em; font-size: 26px; margin: 0; color: #0f2540; }
        header.encabezado p { margin: .15rem 0 0; color: #475569; }
        header.encabezado .meta { text-align: right; color: #475569; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #0f2540; color: #fff; text-align: left; padding: 5px 6px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .nowrap { white-space: nowrap; }

        .total { margin-top: .7rem; font-weight: 600; }

        footer.pie { position: fixed; bottom: 0; left: 0; right: 0; padding: 4px 12px;
                     border-top: 1px solid #cbd5e1; color: #64748b; font-size: 10px;
                     display: flex; justify-content: space-between; background: #fff; }

        @media print {
            .no-imprimir { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="barra no-imprimir">
    <button type="button" class="principal" onclick="window.print()">Imprimir / Guardar como PDF</button>
    <button type="button" onclick="window.close()">Cerrar</button>
    <span>En el cuadro de impresión elige “Guardar como PDF”.</span>
</div>

<header class="encabezado">
    <div>
        <h1>Directorio de usuarios</h1>
        <p>Programación Web · Práctica Integradora · Ingeniería en Sistemas Computacionales</p>
    </div>
    <div class="meta">
        Generado: <?= h($generado) ?><br>
        Por: <?= h($_SESSION['nombre']) ?> (<?= h($_SESSION['usuario']) ?>)
    </div>
</header>

<table>
    <thead>
        <tr>
            <th>Clave</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>País</th>
            <th>Latitud</th>
            <th>Longitud</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($usuarios as $u) { ?>
        <tr>
            <td><?= h($u['cve']) ?></td>
            <td><?= h($u['nombre']) ?></td>
            <td><?= h($u['usuario']) ?></td>
            <td><?= h($u['correo']) ?></td>
            <td class="nowrap"><?= h($u['telefono']) ?></td>
            <td><?= h($u['rol']) ?></td>
            <td><?= h($u['pais']) ?></td>
            <td class="nowrap"><?= h(number_format((float)$u['latitud'], 6)) ?></td>
            <td class="nowrap"><?= h(number_format((float)$u['longitud'], 6)) ?></td>
        </tr>
    <?php } ?>
    <?php if (!$usuarios) { ?>
        <tr><td colspan="9">No hay usuarios registrados.</td></tr>
    <?php } ?>
    </tbody>
</table>

<p class="total">Total de usuarios: <?= count($usuarios) ?></p>

<footer class="pie">
    <span>Directorio de usuarios · Práctica Integradora de Programación Web</span>
    <span>Generado el <?= h($generado) ?></span>
</footer>

</body>
</html>
