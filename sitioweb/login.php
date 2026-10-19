<?php
session_start();
require_once 'soap.php';

if (isset($_GET['salir']))
{
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['usuario']))
{
    header('Location: directorio.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $usu = trim($_POST['usuario'] ?? '');
    $pwd = $_POST['contrasena'] ?? '';

    if ($usu === '' || $pwd === '')
    {
        $error = 'Escribe tu usuario y tu contraseña.';
    }
    else
    {
        $datos = soap_llamar('sp_Acceso', array($usu, $pwd));

        if (!empty($datos[0]['BAN']) && (string)$datos[0]['BAN'] === '1')
        {
            session_regenerate_id(true);
            $_SESSION['cve']     = (int)$datos[0]['CVE'];
            $_SESSION['usuario'] = $datos[0]['USU'];
            $_SESSION['nombre']  = $datos[0]['NOM'];
            $_SESSION['rol']     = $datos[0]['ROL'];
            $_SESSION['csrf']    = bin2hex(random_bytes(16));

            header('Location: directorio.php');
            exit;
        }

        $error = $datos[0]['MENSAJE'] ?? 'Usuario o contraseña incorrectos.';
    }
}
?>
<?php
$tema = tema_actual();
// Pines decorativos del fondo: una ciudad de referencia por pais de la lista
$decorativos = array();
foreach (lista_paises() as $i => $p)
{
    $decorativos[] = array('cve' => $i + 1, 'nombre' => $p[0], 'pais' => $p[0],
                           'latitud' => $p[2], 'longitud' => $p[1], 'num' => $p[5]);
}
?>
<!DOCTYPE html>
<html lang="es" data-tema="<?= h($tema) ?>" data-bs-theme="<?= $tema === 'futurista' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso · Gómez Aispuro</title>
    <link href="assets/fonts/fuentes.css" rel="stylesheet">
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilo.css?v=2" rel="stylesheet">
<?php if ($tema === 'futurista') { ?>    <link href="assets/futurista.css" rel="stylesheet">
<?php } ?></head>
<body class="entrada">

<div class="fondo-mapa" aria-hidden="true">
    <div id="mapa" class="mapa"></div>
</div>

<main class="tarjeta-entrada">
    <span class="marca">
        <svg class="marca-icono" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M2 12h20M12 2c3 3.2 3 16.8 0 20M12 2c-3 3.2-3 16.8 0 20" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>
        Atlas
    </span>
    <h1>Entra al directorio</h1>
    <p>Consulta y administra a los usuarios, estén donde estén.</p>

    <?php if ($error !== '') { ?>
        <div class="alert alert-danger py-2" role="alert"><?= h($error) ?></div>
    <?php } ?>

    <form method="POST" novalidate>
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="usuario" name="usuario"
                   maxlength="15" autocomplete="username" required autofocus
                   value="<?= h($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="mb-4">
            <label for="contrasena" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="contrasena" name="contrasena"
                   maxlength="15" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-principal">Entrar</button>
    </form>
</main>

<footer class="pie pie-entrada">© 2026 Juan Moisés Gómez Aispuro · TecNM Pachuca</footer>

<script type="application/json" id="datosMapa"><?= json_encode($decorativos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/vendor/d3.min.js"></script>
<script src="assets/vendor/topojson-client.min.js"></script>
<script src="assets/mapa.js"></script>
<script>
    Mapa.init({
        contenedor: '#mapa',
        usuarios: JSON.parse(document.getElementById('datosMapa').textContent),
        modo: '<?= $tema === 'futurista' ? 'globo' : 'plano' ?>',
        interactivo: false
    });
</script>
</body>
</html>
