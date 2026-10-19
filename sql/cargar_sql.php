<?php
/*
 * Carga un script .sql en MySQL usando mysqli (PHP), para cuando el cliente
 * mysql.exe de XAMPP no puede entrar a MySQL 8 / RDS (caching_sha2_password).
 * Entiende las lineas DELIMITER de los procedimientos almacenados.
 *
 * Uso:  php cargar_sql.php [archivo.sql]
 * Datos de conexion: ../servicioweb/config.php (o variables DB_HOST, DB_USER, DB_PASS).
 */
mysqli_report(MYSQLI_REPORT_OFF);

$archivo = $argv[1] ?? __DIR__ . '/bd_integradora_aws.sql';
$cfg = is_file(__DIR__ . '/../servicioweb/config.php') ? require __DIR__ . '/../servicioweb/config.php' : array();
$host = getenv('DB_HOST') ?: ($cfg['host'] ?? 'localhost');
$user = getenv('DB_USER') ?: ($cfg['user'] ?? 'root');
$pass = getenv('DB_PASS') ?: ($cfg['pass'] ?? '');

$conn = mysqli_connect($host, $user, $pass);
if (!$conn) { fwrite(STDERR, "No se pudo conectar a $host: " . mysqli_connect_error() . "\n"); exit(1); }
mysqli_set_charset($conn, 'utf8mb4');
echo "Conectado a $host como $user\n";

$delim = ';';
$buffer = '';
$total = 0;

foreach (file($archivo, FILE_IGNORE_NEW_LINES) as $n => $linea)
{
    if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $linea, $m)) { $delim = $m[1]; continue; }
    if ($buffer === '' && (trim($linea) === '' || preg_match('/^\s*--/', $linea))) continue;

    $buffer .= $linea . "\n";
    $fin = rtrim($buffer);

    if (substr($fin, -strlen($delim)) === $delim)
    {
        $sql = trim(substr($fin, 0, -strlen($delim)));
        $buffer = '';
        if ($sql === '') continue;
        if (!mysqli_query($conn, $sql))
        {
            fwrite(STDERR, "ERROR en la linea " . ($n + 1) . ": " . mysqli_error($conn) . "\n  " . substr(preg_replace('/\s+/', ' ', $sql), 0, 160) . "\n");
            exit(1);
        }
        $total++;
    }
}
echo "Listo: $total sentencias ejecutadas desde " . basename($archivo) . "\n";
