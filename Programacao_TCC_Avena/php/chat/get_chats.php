<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

try {
    // Adjust selected fields if your schema differs
    $sql = "
      SELECT
        c.id AS chat_id,
        c.last_message,
        c.last_message_at,
        (CASE WHEN u.unread_count IS NOT NULL THEN u.unread_count ELSE 0 END) AS unread_count
      FROM chats c
      INNER JOIN chat_participants p
        ON p.chat_id = c.id AND p.user_id = ?
      LEFT JOIN chat_unread u
        ON u.chat_id = c.id AND u.user_id = ?
      ORDER BY c.last_message_at DESC NULLS LAST
    ";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode(['ok' => true, 'chats' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao listar chats']);
}
