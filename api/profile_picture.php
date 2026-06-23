<?php
/**
 * api/profile_picture.php — Proxy de imagen de perfil con caché en disco.
 *
 * Caché positivo: imagen guardada en uploads/pp_cache/{phone}.jpg (TTL 24 h)
 * Caché negativo: archivo uploads/pp_cache/{phone}.none  (TTL 12 h)
 * Ambos evitan llamadas repetidas a la API de WhatsApp.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$phone = trim($_GET['phone'] ?? '');
if (!preg_match('/^\d{7,15}$/', $phone)) {
    http_response_code(400);
    exit;
}

define('PP_CACHE_DIR',    UPLOAD_DIR . 'pp_cache/');
define('PP_TTL_HIT',      86400);   // 24 h — tiene foto
define('PP_TTL_MISS',     43200);   // 12 h — no tiene foto

function serveDefaultAvatar(): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="#e0e0e0"/><circle cx="20" cy="16" r="7" fill="#9e9e9e"/><ellipse cx="20" cy="36" rx="12" ry="8" fill="#9e9e9e"/></svg>';
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=' . PP_TTL_MISS);
    header('Content-Length: ' . strlen($svg));
    echo $svg;
    exit;
}

// Asegurar directorio de caché
if (!is_dir(PP_CACHE_DIR)) {
    mkdir(PP_CACHE_DIR, 0755, true);
}

$imgFile  = PP_CACHE_DIR . $phone . '.jpg';
$noneFile = PP_CACHE_DIR . $phone . '.none';

// ── Caché negativo (no tiene foto) ──────────────────────────
if (file_exists($noneFile) && (time() - filemtime($noneFile)) < PP_TTL_MISS) {
    serveDefaultAvatar();
}

// ── Caché positivo (tiene foto) ─────────────────────────────
if (file_exists($imgFile) && (time() - filemtime($imgFile)) < PP_TTL_HIT) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=' . PP_TTL_HIT);
    header('Content-Length: ' . filesize($imgFile));
    readfile($imgFile);
    exit;
}

// ── Limpieza de archivos expirados (1% de las requests) ─────
if (rand(1, 100) === 1) {
    $maxAge = max(PP_TTL_HIT, PP_TTL_MISS) + 60;
    foreach (glob(PP_CACHE_DIR . '*') as $f) {
        if (is_file($f) && (time() - filemtime($f)) > $maxAge) @unlink($f);
    }
}

// ── Sin caché: consultar API de WhatsApp ────────────────────
$result = apiGetProfilePicture($phone);
if (!$result['success'] || empty($result['url'])) {
    touch($noneFile);
    serveDefaultAvatar();
}

$ch = curl_init($result['url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$imageData   = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$mime = strtolower(explode(';', $contentType ?? '')[0]);

if (!$imageData || $httpCode < 200 || $httpCode >= 300 || !str_starts_with($mime, 'image/')) {
    touch($noneFile);
    serveDefaultAvatar();
}

// Guardar en caché y servir
file_put_contents($imgFile, $imageData);

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=' . PP_TTL_HIT);
header('Content-Length: ' . strlen($imageData));
echo $imageData;
