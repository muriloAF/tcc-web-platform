<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

$chat_id   = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$sender_id = isset($_POST['sender_id']) ? (int)$_POST['sender_id'] : 0;
$body      = isset($_POST['body']) ? trim($_POST['body']) : '';

if ($chat_id <= 0 || $sender_id <= 0 || $body === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

try {
    $conexao->begin_transaction();

    // 1) Insert message
    $stmt = $conexao->prepare("INSERT INTO messages (chat_id, sender_id, body, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('iis', $chat_id, $sender_id, $body);
    $stmt->execute();
    $message_id = $stmt->insert_id;
    $stmt->close();

    // 2) Update last_message and last_message_at on chat
    //    (use a short excerpt if desired)
    $last = mb_substr($body, 0, 255, 'UTF-8');
    $stmt = $conexao->prepare("UPDATE chats SET last_message = ?, last_message_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $last, $chat_id);
    $stmt->execute();
    $stmt->close();

    // 3) Increment unread for all recipients except sender
    //    Assumes a chat_participants(chat_id, user_id) table exists
    $stmt = $conexao->prepare("SELECT user_id FROM chat_participants WHERE chat_id = ? AND user_id <> ?");
    $stmt->bind_param('ii', $chat_id, $sender_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $recipients = [];
    while ($row = $res->fetch_assoc()) {
        $recipients[] = (int)$row['user_id'];
    }
    $stmt->close();

    if ($recipients) {
        $ins = $conexao->prepare("
            INSERT INTO chat_unread (chat_id, user_id, unread_count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE unread_count = unread_count + 1
        ");
        foreach ($recipients as $uid) {
            $ins->bind_param('ii', $chat_id, $uid);
            $ins->execute();
        }
        $ins->close();
    }

    $conexao->commit();
    echo json_encode(['ok' => true, 'message_id' => $message_id]);
} catch (Throwable $e) {
    $conexao->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao enviar mensagem']);
}
