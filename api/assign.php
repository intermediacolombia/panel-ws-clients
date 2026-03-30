<?php
/**
 * api/assign.php — Asigna una conversación pendiente al agente actual.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$convId = (int)($data['conversationId'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();

    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada.']);
        exit;
    }

    if (!canAccessConversation($conv)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin acceso.']);
        exit;
    }

    if ($conv['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La conversación no está en estado pendiente.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE conversations
         SET status = ?, agent_id = ?, assigned_at = ?, updated_at = ?
         WHERE id = ? AND status = ?'
    )->execute(['attending', $currentAgent['id'], $now, $now, $convId, 'pending']);

    // Notificar a otros agentes del departamento que fue tomada
    if ($conv['department_id']) {
        $agStmt = $pdo->prepare(
            'SELECT a.id FROM agents a
             JOIN agent_departments ad ON ad.agent_id = a.id
             WHERE ad.department_id = ? AND a.status = ? AND a.id != ?'
        );
        $agStmt->execute([$conv['department_id'], 'active', $currentAgent['id']]);
        $otherAgents = $agStmt->fetchAll(PDO::FETCH_COLUMN);

        $notifMsg = $currentAgent['name'] . ' tomó la conversación de ' .
                    ($conv['contact_name'] ?: $conv['phone']);

        $notifStmt = $pdo->prepare(
            'INSERT INTO notifications (agent_id, conversation_id, type, message, created_at)
             VALUES (?,?,?,?,?)'
        );
        foreach ($otherAgents as $agId) {
            $notifStmt->execute([$agId, $convId, 'assigned', $notifMsg, $now]);
        }
    }

    echo json_encode(['success' => true, 'agentId' => $currentAgent['id']]);

} catch (PDOException $e) {
    error_log('[api/assign] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
