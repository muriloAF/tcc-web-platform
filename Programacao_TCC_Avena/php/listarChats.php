<?php
session_start();
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');
$conexao->set_charset('utf8mb4');

function respond($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function resolveCurrentUser()
{
    $tipo = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : null;
    $idPrest = (int) (isset($_SESSION['id_prestadora']) ? $_SESSION['id_prestadora'] : (isset($_SESSION['prestadora']['id_usuario']) ? $_SESSION['prestadora']['id_usuario'] : 0));
    $idCli   = (int) (isset($_SESSION['id_cliente']) ? $_SESSION['id_cliente'] : (isset($_SESSION['cliente']['id_usuario']) ? $_SESSION['cliente']['id_usuario'] : 0));
    if ($tipo === 'cliente' || ($idCli && !$idPrest)) return ['role' => 'cliente', 'id_cliente' => $idCli, 'id_prestadora' => 0];
    if ($tipo === 'profissional' || ($idPrest && !$idCli)) return ['role' => 'prestadora', 'id_cliente' => 0, 'id_prestadora' => $idPrest];
    return ['role' => null, 'id_cliente' => 0, 'id_prestadora' => 0];
}

try {
    $current = resolveCurrentUser();
    if (!$current['role']) {
        respond(['ok' => false, 'erro' => 'Usuário não autenticado'], 401);
    }

    // Marcar mensagens como lidas para um chat (opcional POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_lido_chat_id'])) {
        $chatId = (int) $_POST['marcar_lido_chat_id'];
        $me = $current['role'] === 'cliente' ? $current['id_cliente'] : $current['id_prestadora'];
        $stmtM = $conexao->prepare("UPDATE mensagem SET lido = 1 WHERE id_chat = ? AND id_para = ?");
        if (!$stmtM) throw new Exception('prepare update: ' . $conexao->error);
        $stmtM->bind_param('ii', $chatId, $me);
        $ok = $stmtM->execute();
        $stmtM->close();
        respond(['ok' => $ok]);
    }

    $isCliente = $current['role'] === 'cliente';
    $idAtual = $isCliente ? $current['id_cliente'] : $current['id_prestadora'];

    if ($isCliente) {
        $sql = "SELECT c.id_chat, p.id_usuario AS other_id, p.nome AS other_name,
                       COALESCE(p.imgperfil, '../img/SemFoto.jpg') AS other_photo,
                       (
                           SELECT id_mensagem FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message_id,
                       (
                           SELECT conteudo FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message,
                       (
                           SELECT enviado_em FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message_time,
                       (
                           SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat AND m.id_para = ? AND m.lido = 0
                       ) AS unread_count
                FROM chat c
                INNER JOIN prestadora p ON p.id_usuario = c.id_prestadora
                WHERE c.id_cliente = ?
                ORDER BY last_message_time DESC, c.id_chat DESC";
    } else {
        $sql = "SELECT c.id_chat, cl.id_usuario AS other_id, cl.nome AS other_name,
                       '../img/SemFoto.jpg' AS other_photo,
                       (
                           SELECT id_mensagem FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message_id,
                       (
                           SELECT conteudo FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message,
                       (
                           SELECT enviado_em FROM mensagem m WHERE m.id_chat = c.id_chat
                           ORDER BY m.id_mensagem DESC LIMIT 1
                       ) AS last_message_time,
                       (
                           SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat AND m.id_para = ? AND m.lido = 0
                       ) AS unread_count
                FROM chat c
                INNER JOIN cliente cl ON cl.id_usuario = c.id_cliente
                WHERE c.id_prestadora = ?
                ORDER BY last_message_time DESC, c.id_chat DESC";
    }

    $stmt = $conexao->prepare($sql);
    if (!$stmt) throw new Exception('prepare listarChats: ' . $conexao->error);
    $stmt->bind_param('ii', $idAtual, $idAtual);
    if (!$stmt->execute()) throw new Exception('execute listarChats: ' . $stmt->error);
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'chat_id' => (int) $r['id_chat'],
            'other_id' => (int) $r['other_id'],
            'other_name' => $r['other_name'],
            'other_photo' => $r['other_photo'],
            'last_message_id' => isset($r['last_message_id']) ? (int) $r['last_message_id'] : null,
            'last_message' => $r['last_message'] ?? '',
            'last_message_time' => $r['last_message_time'] ?? null,
            'unread' => isset($r['unread_count']) ? (int) $r['unread_count'] : 0
        ];
    }
    $stmt->close();
    respond(['ok' => true, 'chats' => $out]);
} catch (Exception $e) {
    error_log('listarChats error: ' . $e->getMessage());
    respond(['ok' => false, 'erro' => $e->getMessage()], 500);
}
?>
