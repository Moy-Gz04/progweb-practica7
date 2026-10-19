<?php
header('Content-Type: application/json; charset=utf-8');

$pais = trim($_GET['pais'] ?? '');

if ($pais === '' || mb_strlen($pais) > 60) {
    http_response_code(400);
    echo json_encode(['error' => 'País no válido']);
    exit;
}

// Configurar esta variable en Apache/PHP.
// No escribir la clave real en este archivo.
$apiKey = getenv('RESTCOUNTRIES_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Falta configurar la API Key']);
    exit;
}

$url = 'https://api.restcountries.com/countries/v5/name?q='
    . rawurlencode($pais);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15
]);

$respuesta = curl_exec($ch);
$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($respuesta === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Error de conexión con REST Countries',
        'detalle' => $error
    ]);
    exit;
}

$datos = json_decode($respuesta, true);

if (!is_array($datos)) {
    http_response_code(502);
    echo json_encode(['error' => 'Respuesta JSON no válida']);
    exit;
}

if ($codigo < 200 || $codigo >= 300) {
    http_response_code(502);
    echo json_encode([
        'error' => 'REST Countries devolvió HTTP ' . $codigo
    ]);
    exit;
}

echo json_encode($datos, JSON_UNESCAPED_UNICODE);
