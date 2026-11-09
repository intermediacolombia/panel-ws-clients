<?php
/**
 * api/link_preview.php — Preview de URL (Open Graph) con caché en disco.
 * GET ?url=https://ejemplo.com/pagina
 * Devuelve JSON { success, title, description, image, site }.
 * Caché en uploads/link_cache/<sha1>.json (7 días hit, 1 día miss).
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$url = trim($_GET['url'] ?? '');

// Validación básica
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
    echo json_encode(['success' => false, 'error' => 'URL inválida.']);
    exit;
}
if (strlen($url) > 2000) {
    echo json_encode(['success' => false, 'error' => 'URL demasiado larga.']);
    exit;
}

define('LP_CACHE_DIR', UPLOAD_DIR . 'link_cache/');
define('LP_TTL_HIT',   604800); // 7 días
define('LP_TTL_MISS',  86400);  // 1 día

if (!is_dir(LP_CACHE_DIR)) mkdir(LP_CACHE_DIR, 0755, true);

$key   = sha1($url);
$file  = LP_CACHE_DIR . $key . '.json';

// Servir caché si está fresca
if (file_exists($file)) {
    $age = time() - filemtime($file);
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    $ttl  = (is_array($data) && !empty($data['success'])) ? LP_TTL_HIT : LP_TTL_MISS;
    if ($age < $ttl && is_array($data)) {
        echo json_encode($data);
        exit;
    }
}

// Limpieza ocasional
if (rand(1, 100) === 1) {
    foreach (glob(LP_CACHE_DIR . '*.json') as $f) {
        if (is_file($f) && (time() - filemtime($f)) > LP_TTL_HIT + 60) @unlink($f);
    }
}

// ── Anti-SSRF: resolver host, rechazar IPs privadas/reservadas ─
$host = parse_url($url, PHP_URL_HOST);
if (!$host) {
    _cacheMiss($file, 'Host inválido');
}
$ip = gethostbyname($host);
if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
    _cacheMiss($file, 'DNS falló');
}
if (filter_var($ip, FILTER_VALIDATE_IP) &&
    !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    _cacheMiss($file, 'IP no pública');
}

// ── Fetch ──────────────────────────────────────────────────────
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 4,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PanelPreview/1.0; +https://panelws.intermediahost.co)',
    CURLOPT_RANGE          => '0-524287', // primeros 512 KB
    CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
]);
$html     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mime     = strtolower(explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '')[0]);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
curl_close($ch);

if (!$html || $httpCode < 200 || $httpCode >= 400 || strpos($mime, 'html') === false) {
    _cacheMiss($file, 'Sin HTML');
}

// ── Parse ──────────────────────────────────────────────────────
$meta = _parseOg($html);
$site = parse_url($finalUrl, PHP_URL_HOST);

// Resolver image relativa
if (!empty($meta['image']) && !preg_match('#^https?://#i', $meta['image'])) {
    $meta['image'] = _absUrl($finalUrl, $meta['image']);
}

if (empty($meta['title'])) {
    _cacheMiss($file, 'Sin título');
}

$result = [
    'success'     => true,
    'title'       => mb_substr($meta['title'], 0, 200),
    'description' => mb_substr($meta['description'] ?? '', 0, 400),
    'image'       => $meta['image'] ?? '',
    'site'        => $site,
    'url'         => $finalUrl,
];
file_put_contents($file, json_encode($result));
echo json_encode($result);
exit;

// ── Helpers ────────────────────────────────────────────────────
function _cacheMiss(string $file, string $reason): void {
    $data = ['success' => false, 'error' => $reason];
    file_put_contents($file, json_encode($data));
    echo json_encode($data);
    exit;
}

function _parseOg(string $html): array {
    $out = ['title' => '', 'description' => '', 'image' => ''];

    // OG tags (property="og:...")
    if (preg_match_all('#<meta[^>]+(?:property|name)=["\']og:([a-z:]+)["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
        foreach ($m[1] as $i => $prop) {
            $val = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($prop === 'title' && !$out['title']) $out['title'] = $val;
            if ($prop === 'description' && !$out['description']) $out['description'] = $val;
            if ($prop === 'image' && !$out['image']) $out['image'] = $val;
        }
    }
    // Twitter tags como fallback
    if (preg_match_all('#<meta[^>]+name=["\']twitter:([a-z:]+)["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
        foreach ($m[1] as $i => $prop) {
            $val = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($prop === 'title' && !$out['title']) $out['title'] = $val;
            if ($prop === 'description' && !$out['description']) $out['description'] = $val;
            if ($prop === 'image' && !$out['image']) $out['image'] = $val;
        }
    }
    // Fallback: <title> y meta description
    if (!$out['title'] && preg_match('#<title[^>]*>([^<]+)</title>#i', $html, $m)) {
        $out['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (!$out['description'] && preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
        $out['description'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $out;
}

function _absUrl(string $base, string $rel): string {
    if (str_starts_with($rel, '//')) {
        return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $rel;
    }
    if (str_starts_with($rel, '/')) {
        $p = parse_url($base);
        return $p['scheme'] . '://' . $p['host'] . $rel;
    }
    return rtrim(dirname($base), '/') . '/' . $rel;
}
