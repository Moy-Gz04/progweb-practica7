<?php
// Recibe las acciones del CRUD desde el navegador (fetch), las valida y
// las manda al servicio SOAP. Responde siempre JSON: {ok, mensaje, cve}.
session_start();
require_once 'soap.php';

header('Content-Type: application/json; charset=utf-8');
exigir_sesion(true);

function responder($ok, $mensaje, $extra = array())
{
    echo json_encode(array_merge(array('ok' => $ok, 'mensaje' => $mensaje), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    responder(false, 'Método no permitido.');
}

if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']))
{
    http_response_code(403);
    responder(false, 'Token de seguridad inválido. Recarga la página.');
}

// Misma validacion que hacen los procedimientos almacenados: se revisa
// aqui primero para dar el mensaje sin ir hasta MySQL.
function validar_usuario($d, $esAlta)
{
    if ($d['nom'] === '')                                   return 'El nombre es obligatorio.';
    if ($d['pat'] === '')                                   return 'El apellido paterno es obligatorio.';
    if (!preg_match('/^[0-9 ()+-]{7,15}$/', $d['tel']))     return 'El teléfono no es válido (7 a 15 dígitos).';
    if (!preg_match('/^[^@ ]+@[^@ ]+\.[^@ ]+$/', $d['cor'])) return 'El correo electrónico no es válido.';
    if (!preg_match('/^[A-Za-z0-9_.]{4,15}$/', $d['usu']))  return 'El usuario debe tener de 4 a 15 caracteres (letras, números, punto o guion bajo).';
    if ($esAlta && mb_strlen($d['pwd']) < 6)                return 'La contraseña debe tener al menos 6 caracteres.';
    if (!$esAlta && $d['pwd'] !== '' && mb_strlen($d['pwd']) < 6) return 'La contraseña debe tener al menos 6 caracteres.';
    if ($d['rol'] < 1)                                      return 'Selecciona un rol.';
    if ($d['pais'] === '')                                  return 'El país es obligatorio.';
    if (!is_numeric($d['lat']) || $d['lat'] < -90 || $d['lat'] > 90)    return 'La latitud debe estar entre -90 y 90.';
    if (!is_numeric($d['lon']) || $d['lon'] < -180 || $d['lon'] > 180)  return 'La longitud debe estar entre -180 y 180.';
    return '';
}

function datos_formulario()
{
    $g = function ($k) { return trim((string)($_POST[$k] ?? '')); };

    return array(
        'nom'  => $g('nom'),
        'pat'  => $g('pat'),
        'mat'  => $g('mat'),
        'tel'  => $g('tel'),
        'cor'  => $g('cor'),
        'usu'  => $g('usu'),
        'pwd'  => (string)($_POST['pwd'] ?? ''),
        'rol'  => (int)($_POST['rol'] ?? 0),
        'pais' => $g('pais'),
        'lon'  => $g('lon'),
        'lat'  => $g('lat'),
    );
}

$accion = $_POST['accion'] ?? '';

switch ($accion)
{
    case 'ins':
        $d   = datos_formulario();
        $msg = validar_usuario($d, true);
        if ($msg !== '') responder(false, $msg);

        $r = soap_llamar('sp_InsUsuario', array(
            $d['nom'], $d['pat'], $d['mat'], $d['tel'], $d['cor'], $d['usu'],
            $d['pwd'], $d['rol'], $d['pais'], $d['lon'], $d['lat']
        ));
        break;

    case 'upd':
        $cve = (int)($_POST['cve'] ?? 0);
        if ($cve < 1) responder(false, 'Falta la clave del usuario.');

        $d   = datos_formulario();
        $msg = validar_usuario($d, false);
        if ($msg !== '') responder(false, $msg);

        $r = soap_llamar('sp_UpdUsuario', array(
            $cve, $d['nom'], $d['pat'], $d['mat'], $d['tel'], $d['cor'], $d['usu'],
            $d['pwd'], $d['rol'], $d['pais'], $d['lon'], $d['lat']
        ));
        break;

    case 'del':
        $cve = (int)($_POST['cve'] ?? 0);
        if ($cve < 1) responder(false, 'Falta la clave del usuario.');
        if ($cve === (int)($_SESSION['cve'] ?? 0))
        {
            responder(false, 'No puedes eliminar tu propio usuario mientras tienes la sesión abierta.');
        }

        $r = soap_llamar('sp_DelUsuario', array($cve));
        break;

    default:
        http_response_code(400);
        responder(false, 'Acción no válida.');
}

$ban = isset($r[0]['BAN']) ? (string)$r[0]['BAN'] === '1' : false;
responder($ban, $r[0]['MENSAJE'] ?? 'Sin respuesta del servicio.', array('cve' => (int)($r[0]['CVE'] ?? 0)));
