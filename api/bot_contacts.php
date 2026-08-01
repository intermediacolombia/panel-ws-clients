<?php
/**
 * api/bot_contacts.php — Contactos del bot.
 * GET  ?limit=50&offset=0  → lista paginada
 * DELETE ?phone=X&client_id=Y → elimina un contacto
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = DB::get();

    if ($method === 'GET') {
        $limit  = max(1, min(200, (int)($_GET['limit']  ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        $stmt = $pdo->prepare(
            'SELECT id, phone, name, client_id, first_seen, updated_at
               FROM bot_contacts
           ORDER BY updated_at DESC
              LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $total = (int)$pdo->query('SELECT COUNT(*) FROM bot_contacts')->fetchColumn();

        echo json_encode(['success' => true, 'contacts' => $rows, 'total' => $total]);

    } elseif ($method === 'DELETE') {
        $phone     = trim($_GET['phone']     ?? '');
        $client_id = trim($_GET['client_id'] ?? '');

        if ($phone === '' || $client_id === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'phone and client_id required.']);
            exit;
        }

        $pdo->prepare('DELETE FROM bot_contacts WHERE phone = ? AND client_id = ?')
            ->execute([$phone, $client_id]);

        echo json_encode(['success' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error.']);
}
