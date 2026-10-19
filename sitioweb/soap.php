<?php
// Utilidades compartidas por las paginas del sitio.
// El sitio NO toca MySQL: todo pasa por el servicio web SOAP (servicioweb/).

date_default_timezone_set('America/Mexico_City');

// Paises disponibles en el formulario: nombre, longitud y latitud de su
// ciudad de referencia (tabla de ejemplo de la practica; se pueden editar a
// mano) y el nombre en ingles con que se busca en REST Countries (en espanol
// la API confunde, p. ej. "Estados Unidos" lo toma por "Estados Unidos Mexicanos").
// Campos: nombre, longitud, latitud, nombre en ingles, codigo ISO-2 (bandera),
// codigo ISO numerico (para resaltar el pais en el mapa).
function lista_paises()
{
    return array(
        array('México',         -99.133209,  19.432608, 'Mexico',                   'mx', 484),
        array('Perú',           -77.042793, -12.046374, 'Peru',                     'pe', 604),
        array('Colombia',       -74.072092,   4.710989, 'Colombia',                 'co', 170),
        array('Argentina',      -58.381559, -34.603722, 'Argentina',                'ar',  32),
        array('España',          -3.703790,  40.416775, 'Spain',                    'es', 724),
        array('Japón',          139.691706,  35.689487, 'Japan',                    'jp', 392),
        array('Estados Unidos', -77.036871,  38.907192, 'United States of America', 'us', 840),
        array('Brasil',         -47.882500, -15.794200, 'Brazil',                   'br',  76),
        array('Chile',          -70.669265, -33.448891, 'Chile',                    'cl', 152),
        array('Canadá',         -75.697193,  45.421530, 'Canada',                   'ca', 124),
        array('Francia',          2.352222,  48.856614, 'France',                   'fr', 250),
        array('Alemania',        13.404954,  52.520008, 'Germany',                  'de', 276),
    );
}

// Datos de un pais de la lista por su nombre (o null si no esta)
function info_pais($nombre)
{
    foreach (lista_paises() as $p)
    {
        if ($p[0] === $nombre)
        {
            return array('api' => $p[3], 'iso2' => $p[4], 'num' => $p[5]);
        }
    }
    return null;
}

// Tema visual elegido (cookie 'tema'): 'futurista' (globo 3D, por defecto) o 'claro' (mapa plano Atlas)
function tema_actual()
{
    return 'claro';
}

// Clase CSS del rol: "ALMACEN" -> "rol-almacen"
function clase_rol($rol)
{
    return 'rol-' . preg_replace('/[^a-z]/', '', strtolower((string)$rol));
}

// Iniciales para el avatar: "Juan Moises Gomez Aispuro" -> "JG"
function iniciales($nom, $pat)
{
    $a = mb_strtoupper(mb_substr(trim((string)$nom), 0, 1));
    $b = mb_strtoupper(mb_substr(trim((string)$pat), 0, 1));
    return $a . $b;
}

// URL del servicio SOAP. Se calcula a partir de donde esta publicado el
// sitio (.../practica5/sitioweb/ -> .../practica5/servicioweb/servicioweb.php),
// asi funciona igual en localhost y en AWS. Se puede forzar con SOAP_URL.
function url_servicio()
{
    $url = getenv('SOAP_URL');

    if (!$url)
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $base  = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
        $url   = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
               . $base . '/servicioweb/servicioweb.php';
    }

    return $url;
}

// Llama a un metodo del servicio SOAP. Si el servicio no responde regresa un
// renglon BAN = 0 con el mensaje, igual que cuando falla la base de datos.
function soap_llamar($metodo, $args = array())
{
    try
    {
        $cliente = new SoapClient(null, array(
            'uri'                => 'http://localhost/',
            'location'           => url_servicio(),
            'exceptions'         => true,
            'connection_timeout' => 10,
        ));

        $res = $cliente->__soapCall($metodo, $args);

        return is_array($res) ? $res : array();
    }
    catch (Throwable $e)
    {
        return array(array('BAN' => '0', 'MENSAJE' => 'No se pudo usar el servicio web: ' . $e->getMessage()));
    }
}

// true si el resultado del servicio es un renglon de error
function es_error($filas)
{
    return isset($filas[0]['BAN']) && (string)$filas[0]['BAN'] === '0';
}

function h($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Exige sesion iniciada; si no la hay manda al login
function exigir_sesion($json = false)
{
    if (empty($_SESSION['usuario']))
    {
        if ($json)
        {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => false, 'mensaje' => 'La sesión expiró. Vuelve a iniciar sesión.'));
        }
        else
        {
            header('Location: login.php');
        }
        exit;
    }
}
