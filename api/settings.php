<?php
/**
 * api/settings.php — Leer y guardar configuración del panel.
 * GET  → devuelve todos los settings
 * POST → actualiza uno o varios settings
 * Solo accesible por supervisores.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
requireSupervisor();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = DB::get();

    // ── GET ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query('SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key');
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = [
                'value'       => $r['setting_value'],
                'description' => $r['description'],
            ];
        }

        // Parsear business_hours como objeto
        if (isset($settings['business_hours'])) {
            $settings['business_hours']['parsed'] = json_decode(
                $settings['business_hours']['value'], true
            );
        }

        echo json_encode(['success' => true, 'settings' => $settings], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST ─────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'JSON inválido.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        // Procesar business_hours
        if (isset($data['business_hours']) && is_array($data['business_hours'])) {
            $hours = [];
            for ($d = 1; $d <= 7; $d++) {
                $day = $data['business_hours'][$d] ?? [];
                $open  = !empty($day['open']);
                $start = preg_match('/^\d{2}:\d{2}$/', $day['start'] ?? '') ? $day['start'] : '08:00';
                $end   = preg_match('/^\d{2}:\d{2}$/', $day['end']   ?? '') ? $day['end']   : '18:00';
                $hours[$d] = [
                    'open'  => $open,
                    'start' => $open ? $start : '00:00',
                    'end'   => $open ? $end   : '00:00',
                ];
            }
            $stmt->execute(['business_hours', json_encode($hours)]);
        }

        // Forzar horario
        if (isset($data['force_schedule'])) {
            $force = in_array($data['force_schedule'], ['auto','open','closed'])
                ? $data['force_schedule'] : 'auto';
            $stmt->execute(['force_schedule', $force]);
        }

        // Zona horaria
        if (isset($data['timezone'])) {
            $tz = trim($data['timezone']);
            // Validar timezone
            try {
                new DateTimeZone($tz);
                $stmt->execute(['timezone', $tz]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Zona horaria inválida: ' . $tz]);
                exit;
            }
        }

        // Mensaje fuera de horario personalizado
        if (array_key_exists('out_of_hours_message', $data)) {
            $stmt->execute(['out_of_hours_message', trim($data['out_of_hours_message'])]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (PDOException $e) {
    error_log('[api/settings] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
