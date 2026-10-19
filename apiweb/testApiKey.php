<?php

$apiKey = getenv('RESTCOUNTRIES_API_KEY');

if ($apiKey !== false && $apiKey !== '') {
    echo "OK!!: <b>PHP SI reconoce</b> la API Key de REST Countries, la puedes usar en tu código PHP.";
} else {
    echo "ERROR: No se encontro la API Key.";
}
