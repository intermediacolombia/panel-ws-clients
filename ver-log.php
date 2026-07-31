<?php
// Protección básica — cambia este token
$token = $_GET['t'] ?? '';
if ($token !== 'sysgym2026') {
    http_response_code(403); die('Acceso denegado');
}

$log = __DIR__ . '/webhook-activgym.log';
$lineas = 100; // últimas N líneas

header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($log)) {
    die("Log no encontrado: $log");
}

$file = new SplFileObject($log);
$file->seek(PHP_INT_MAX);
$total = $file->key();

$desde = max(0, $total - $lineas);
$file->seek($desde);

while (!$file->eof()) {
    echo $file->fgets();
}
