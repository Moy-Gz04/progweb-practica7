<?php
// Consulta el clima actual en Open-Meteo (no requiere API Key).
// Uso: consultaClima.php?lat=19.432608&lon=-99.133209
header('Content-Type: application/json; charset=utf-8');

$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';

if (!is_numeric($lat) || !is_numeric($lon)
    || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Latitud o longitud no válidas']);
    exit;
}

$url = 'https://api.open-meteo.com/v1/forecast'
    . '?latitude=' . rawurlencode((string)(float)$lat)
    . '&longitude=' . rawurlencode((string)(float)$lon)
    . '&current=temperature_2m,relative_humidity_2m,apparent_temperature,'
    . 'weather_code,wind_speed_10m,is_day&timezone=auto';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
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
        'error' => 'Error de conexión con Open-Meteo',
        'detalle' => $error
    ]);
    exit;
}

$datos = json_decode($respuesta, true);

if (!is_array($datos) || $codigo < 200 || $codigo >= 300 || !isset($datos['current'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Open-Meteo devolvió HTTP ' . $codigo]);
    exit;
}

// Códigos WMO de Open-Meteo -> descripción en español
$condiciones = [
    0 => ['Despejado', '☀️'],
    1 => ['Mayormente despejado', '🌤️'],
    2 => ['Parcialmente nublado', '⛅'],
    3 => ['Nublado', '☁️'],
    45 => ['Niebla', '🌫️'],
    48 => ['Niebla con escarcha', '🌫️'],
    51 => ['Llovizna ligera', '🌦️'],
    53 => ['Llovizna moderada', '🌦️'],
    55 => ['Llovizna densa', '🌧️'],
    56 => ['Llovizna helada ligera', '🌧️'],
    57 => ['Llovizna helada densa', '🌧️'],
    61 => ['Lluvia ligera', '🌦️'],
    63 => ['Lluvia moderada', '🌧️'],
    65 => ['Lluvia fuerte', '🌧️'],
    66 => ['Lluvia helada ligera', '🌧️'],
    67 => ['Lluvia helada fuerte', '🌧️'],
    71 => ['Nevada ligera', '🌨️'],
    73 => ['Nevada moderada', '🌨️'],
    75 => ['Nevada fuerte', '❄️'],
    77 => ['Granizo fino', '🌨️'],
    80 => ['Chubascos ligeros', '🌦️'],
    81 => ['Chubascos moderados', '🌧️'],
    82 => ['Chubascos violentos', '⛈️'],
    85 => ['Chubascos de nieve ligeros', '🌨️'],
    86 => ['Chubascos de nieve fuertes', '❄️'],
    95 => ['Tormenta eléctrica', '⛈️'],
    96 => ['Tormenta con granizo ligero', '⛈️'],
    99 => ['Tormenta con granizo fuerte', '⛈️'],
];

$c = $datos['current'];
$u = $datos['current_units'] ?? [];
$codigoClima = (int)($c['weather_code'] ?? -1);
$cond = $condiciones[$codigoClima] ?? ['Condición desconocida', '🌡️'];

echo json_encode([
    'latitud' => $datos['latitude'] ?? (float)$lat,
    'longitud' => $datos['longitude'] ?? (float)$lon,
    'zona_horaria' => $datos['timezone'] ?? '',
    'hora' => $c['time'] ?? '',
    'temperatura' => $c['temperature_2m'] ?? null,
    'sensacion' => $c['apparent_temperature'] ?? null,
    'humedad' => $c['relative_humidity_2m'] ?? null,
    'viento' => $c['wind_speed_10m'] ?? null,
    'es_dia' => ($c['is_day'] ?? 1) == 1,
    'codigo' => $codigoClima,
    'condicion' => $cond[0],
    'icono' => $cond[1],
    'unidades' => [
        'temperatura' => $u['temperature_2m'] ?? '°C',
        'humedad' => $u['relative_humidity_2m'] ?? '%',
        'viento' => $u['wind_speed_10m'] ?? 'km/h',
    ],
], JSON_UNESCAPED_UNICODE);
