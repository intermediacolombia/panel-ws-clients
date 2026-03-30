<?php
/**
 * helpers.php — Funciones de utilidad globales
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Envía un mensaje (texto o archivo) a través de la API WhatsApp.
 *
 * @param string      $phone     Número internacional sin + (ej: 573001234567)
 * @param string      $text      Texto o descripción (siempre requerido)
 * @param string|null $url       URL pública HTTPS del archivo (opcional)
 * @param string|null $filename  Nombre del archivo (opcional)
 * @param string|null $caption   Caption del archivo (opcional)
 * @return array ['success' => bool, 'messageId' => string|null, 'error' => string|null]
 */
function apiSend(string $phone, string $text, ?string $url = null, ?string $filename = null, ?string $caption = null): array
{
    $payload = [
        'phonenumber' => $phone,
        'text'        => $text,
    ];

    if ($url !== null) {
        $payload['url']      = $url;
        $payload['filename'] = $filename ?? basename($url);
        $payload['caption']  = $caption  ?? $text;
    }

    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init(WA_API_URL . '/api/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . WA_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[apiSend] cURL error para ' . $phone . ': ' . $curlErr);
        return ['success' => false, 'messageId' => null, 'error' => 'Error de red: ' . $curlErr];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['success'])) {
        return [
            'success'   => true,
            'messageId' => $decoded['data']['messageId'] ?? null,
            'error'     => null,
        ];
    }

    $errMsg = $decoded['error'] ?? ('HTTP ' . $httpCode);
    error_log('[apiSend] Fallo enviando a ' . $phone . ': ' . $errMsg . ' | body: ' . $response);

    return ['success' => false, 'messageId' => null, 'error' => $errMsg];
}

/**
 * Encuentra o crea automáticamente un departamento a partir del área enviada por el webhook.
 * Usa PANEL_AREAS (definido en config.php) para hacer match por nombre.
 * Si el área no coincide con ninguna entrada, crea una entrada genérica.
 *
 * @param string $areaLabel  Ej: "Ventas - Streaming Radio"
 * @return int|null          ID del departamento
 */
function getOrCreateDepartment(string $areaLabel): ?int
{
    if ($areaLabel === '') return null;

    $areas   = defined('PANEL_AREAS') ? PANEL_AREAS : [];
    $matched = null;
    $lower   = mb_strtolower($areaLabel, 'UTF-8');

    // Buscar en PANEL_AREAS el área cuyo nombre aparezca en el label
    foreach ($areas as $area) {
        $areaLower = mb_strtolower($area['name'], 'UTF-8');
        if (str_contains($lower, $areaLower)) {
            $matched = $area;
            break;
        }
    }

    // Si no hay match, generar datos desde el label
    if ($matched === null) {
        // Tomar primer segmento antes de " - "
        $namePart = trim(explode(' - ', $areaLabel)[0]);
        $slug     = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($namePart, 'UTF-8'));
        $slug     = trim($slug, '_') ?: 'general';
        $matched  = ['name' => $namePart ?: $areaLabel, 'slug' => $slug, 'color' => '#95A5A6', 'icon' => 'fa-folder'];
    }

    try {
        $pdo = DB::get();

        // Buscar por slug
        $stmt = $pdo->prepare('SELECT id FROM departments WHERE slug = ? LIMIT 1');
        $stmt->execute([$matched['slug']]);
        $row = $stmt->fetch();

        if ($row) {
            return (int)$row['id'];
        }

        // Crear si no existe
        $pdo->prepare(
            'INSERT INTO departments (name, slug, color, icon, active, created_at)
             VALUES (?,?,?,?,1,NOW())'
        )->execute([$matched['name'], $matched['slug'], $matched['color'], $matched['icon']]);

        return (int)$pdo->lastInsertId();

    } catch (PDOException $e) {
        error_log('[getOrCreateDepartment] ' . $e->getMessage());
        return null;
    }
}

/**
 * Sincroniza todos los PANEL_AREAS con la tabla departments (INSERT si no existe).
 * Se llama una vez por sesión de panel para garantizar que los depts existen
 * antes de que llegue el primer mensaje del webhook.
 */
function syncPanelAreas(): void
{
    $areas = defined('PANEL_AREAS') ? PANEL_AREAS : [];
    if (empty($areas)) return;

    try {
        $pdo  = DB::get();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO departments (name, slug, color, icon, active, created_at)
             VALUES (?,?,?,?,1,NOW())'
        );
        foreach ($areas as $area) {
            $stmt->execute([$area['name'], $area['slug'], $area['color'], $area['icon']]);
        }
    } catch (PDOException $e) {
        error_log('[syncPanelAreas] ' . $e->getMessage());
    }
}

/**
 * Formatea un datetime para la lista de conversaciones.
 *
 * @param string $datetime  Valor DATETIME de MySQL
 * @return string
 */
function formatTime(string $datetime): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }

    $ts   = strtotime($datetime);
    $now  = time();
    $diff = $now - $ts;

    // Hoy
    if (date('Y-m-d', $ts) === date('Y-m-d', $now)) {
        return date('H:i', $ts);
    }

    // Ayer
    if (date('Y-m-d', $ts) === date('Y-m-d', $now - 86400)) {
        return 'Ayer';
    }

    // Esta semana (últimos 6 días)
    if ($diff < 7 * 86400) {
        $dias = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        return $dias[(int)date('N', $ts)] ?? date('D', $ts);
    }

    return date('d/m/Y', $ts);
}

/**
 * Escapa correctamente para salida HTML.
 *
 * @param mixed $str
 * @return string
 */
function sanitize($str): string
{
    return htmlspecialchars(trim((string)$str), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Devuelve la IP real del cliente considerando proxies de confianza.
 */
function getClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Obtiene la URL de la foto de perfil de WhatsApp de un número.
 *
 * @param string $phone  Número internacional sin + (ej: 573001234567)
 * @return array ['success' => bool, 'url' => string|null, 'error' => string|null]
 */
function apiGetProfilePicture(string $phone): array
{
    $ch = curl_init(WA_API_URL . '/api/profile-picture?' . http_build_query(['phone' => $phone]));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . WA_API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'url' => null, 'error' => 'Error de red: ' . $curlErr];
    }

    $decoded = json_decode($response, true);

    // La API puede devolver la URL en distintos campos según su implementación
    $url = $decoded['profilePicture'] ?? $decoded['profilePic'] ?? $decoded['url']
        ?? $decoded['profilePictureUrl'] ?? $decoded['data']['url'] ?? null;

    if ($httpCode >= 200 && $httpCode < 300 && $url) {
        return ['success' => true, 'url' => $url, 'error' => null];
    }

    return ['success' => false, 'url' => null, 'error' => $decoded['error'] ?? ('HTTP ' . $httpCode)];
}

/**
 * Emite cabecera JSON y termina la ejecución.
 *
 * @param array $data
 * @param int   $code  HTTP status code
 */
function jsonResponse(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
