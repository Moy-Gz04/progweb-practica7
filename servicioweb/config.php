<?php
/*
 * Parametros de conexion a MySQL.
 *
 * Por defecto apuntan al MySQL local (XAMPP / MySQL 8).
 * En AWS se cambian aqui (host = endpoint de RDS) o con variables de
 * entorno DB_HOST, DB_USER, DB_PASS y DB_NAME, sin tocar el resto del codigo.
 */
return array(
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'prosoft',
    'pass' => getenv('DB_PASS') ?: 'bonita_19',
    'bd'   => getenv('DB_NAME') ?: 'bd_prosoft',
);
