<?php
/**
 * api/profile_picture.php — Proxy de imagen de perfil de WhatsApp.
 * Hace stream de los bytes de la imagen para evitar restricciones CSP.
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

// 1. Obtener la URL de la foto desde la API de WhatsApp
$result = apiGetProfilePicture($phone);
if (!$result['success'] || empty($result['url'])) {
    http_response_code(404);
    exit;
}

// 2. Descargar la imagen y hacer stream al cliente
$imgUrl = $result['url'];
$ch = curl_init($imgUrl);
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

if (!$imageData || $httpCode < 200 || $httpCode >= 300) {
    http_response_code(404);
    exit;
}

// Asegurarse de que sea realmente una imagen
$mime = strtolower(explode(';', $contentType)[0]);
if (!str_starts_with($mime, 'image/')) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . strlen($imageData));
echo $imageData;