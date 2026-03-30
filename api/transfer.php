<?php
/**
 * api/transfer.php — Transfiere una conversación a otro agente en línea.
 * POST { conversationId, targetAgentId }
 * Solo el agente asignado o un supervisor puede transferir.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw           = file_get_contents('php://input');
$data          = json_decode($raw, true);
$convId        = (int)($data['conversationId'] ?? 0);
$targetAgentId = (int)($data['targetAgentId']  ?? 0);

if ($convId <= 0 || $targetAgentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId y targetAgentId son requeridos.']);
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

    // Solo el agente asignado o supervisor puede transferir
    $isAssigned  = (int)$conv['agent_id'] === $currentAgent['id'];
    $isSupervisor = $currentAgent['role'] === 'supervisor';

    if (!$isAssigned && !$isSupervisor) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo el agente asignado o un supervisor puede transferir.']);
        exit;
    }

    if ($conv['status'] !== 'attending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Solo se pueden transferir conversaciones en atención.']);
        exit;
    }

    if ($targetAgentId === $currentAgent['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No puedes transferirte la conversación a ti mismo.']);
        exit;
    }

    // Verificar que el agente destino está activo y en línea
    $agStmt = $pdo->prepare(
        'SELECT id, name FROM agents
         WHERE id = ? AND status = ?
           AND last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         LIMIT 1'
    );
    $agStmt->execute([$targetAgentId, 'active']);
    $targetAgent = $agStmt->fetch();

    if (!$targetAgent) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El agente destino no está disponible o no está en línea.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE conversations SET agent_id = ?, assigned_at = ?, updated_at = ? WHERE id = ?'
    )->execute([$targetAgentId, $now, $now, $convId]);

    // Notificar al agente que recibe la conversación
    $notifMsg = $currentAgent['name'] . ' te transfirió la conversación de ' .
                ($conv['contact_name'] ?: $conv['phone']);
    $pdo->prepare(
        'INSERT INTO notifications (agent_id, conversation_id, type, message, created_at)
         VALUES (?,?,?,?,?)'
    )->execute([$targetAgentId, $convId, 'assigned', $notifMsg, $now]);

    echo json_encode([
        'success'         => true,
        'targetAgentName' => $targetAgent['name'],
    ]);

} catch (PDOException $e) {
    error_log('[api/transfer] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
