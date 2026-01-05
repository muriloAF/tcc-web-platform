<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($chat_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

try {
    // Ensure row exists and set to zero
    $stmt = $conexao->prepare("
        INSERT INTO chat_unread (chat_id, user_id, unread_count)
        VALUES (?, ?, 0)
        ON DUPLICATE KEY UPDATE unread_count = 0
    ");
    $stmt->bind_param('ii', $chat_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao marcar como visto']);
}
