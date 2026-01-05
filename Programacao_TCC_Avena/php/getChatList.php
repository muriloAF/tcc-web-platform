<?php
session_start();
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');
$conexao->set_charset('utf8mb4');

function resolveCurrentUser()
{
    $tipo = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : null;
    $idPrest = (int) (isset($_SESSION['id_prestadora']) ? $_SESSION['id_prestadora'] : (isset($_SESSION['prestadora']['id_usuario']) ? $_SESSION['prestadora']['id_usuario'] : 0));
    $idCli   = (int) (isset($_SESSION['id_cliente']) ? $_SESSION['id_cliente'] : (isset($_SESSION['cliente']['id_usuario']) ? $_SESSION['cliente']['id_usuario'] : 0));

    if ($tipo === 'cliente' || ($idCli && !$idPrest)) {
        return ['role' => 'cliente', 'id_cliente' => $idCli, 'id_prestadora' => 0];
    }
    if ($tipo === 'profissional' || ($idPrest && !$idCli)) {
        return ['role' => 'prestadora', 'id_cliente' => 0, 'id_prestadora' => $idPrest];
    }
    return ['role' => null, 'id_cliente' => 0, 'id_prestadora' => 0];
}

try {
    $current = resolveCurrentUser();
    if (!$current['role']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Usuário não autenticado']);
        exit;
    }

    $isCliente = ($current['role'] === 'cliente');
    $idAtual = $isCliente ? $current['id_cliente'] : $current['id_prestadora'];

    if ($isCliente) {
        $sql = "
            SELECT c.id_chat,
                   p.id_usuario AS other_id,
                   p.nome AS other_name,
                   COALESCE(p.imgperfil, '../img/SemFoto.jpg') AS other_photo,
                   (SELECT id_mensagem FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message_id,
                   (SELECT conteudo FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message,
                   (SELECT enviado_em FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message_time,
                   (SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat) AS message_count,
                   (SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat AND m.id_para = ? AND m.lido = 0) AS unread_count
            FROM chat c
            INNER JOIN prestadora p ON p.id_usuario = c.id_prestadora
            WHERE c.id_cliente = ?
              AND EXISTS (SELECT 1 FROM solicitacoes s WHERE s.id_contratante = c.id_cliente AND s.id_prestadora = c.id_prestadora)
            ORDER BY last_message_time DESC, c.id_chat DESC";
        } else {
                $sql = "
                        SELECT c.id_chat,
                                     cl.id_usuario AS other_id,
                                     cl.nome AS other_name,
                                     COALESCE(cl.imgperfil, '../img/SemFoto.jpg') AS other_photo,
                                     (SELECT id_mensagem FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message_id,
                                     (SELECT conteudo FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message,
                                     (SELECT enviado_em FROM mensagem m WHERE m.id_chat = c.id_chat ORDER BY m.id_mensagem DESC LIMIT 1) AS last_message_time,
                                     (SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat) AS message_count,
                                     (SELECT COUNT(*) FROM mensagem m WHERE m.id_chat = c.id_chat AND m.id_para = ? AND m.lido = 0) AS unread_count
                        FROM chat c
                        INNER JOIN cliente cl ON cl.id_usuario = c.id_cliente
                        WHERE c.id_prestadora = ?
                            AND EXISTS (SELECT 1 FROM solicitacoes s WHERE s.id_contratante = c.id_cliente AND s.id_prestadora = c.id_prestadora)
                        ORDER BY last_message_time DESC, c.id_chat DESC";
        }

    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conexao->error);
    }
    // Bind: primeiro para unread_count subselect (id_para), segundo para WHERE (id_cliente ou id_prestadora)
    $stmt->bind_param('ii', $idAtual, $idAtual);
    $stmt->execute();
    $res = $stmt->get_result();

    $chats = [];
    $hasBadge = false; // indica se deve mostrar bolinha no botão global de mensagens
    while ($row = $res->fetch_assoc()) {
        $messageCount = isset($row['message_count']) ? (int)$row['message_count'] : 0;
        $unreadCount  = isset($row['unread_count']) ? (int)$row['unread_count'] : 0;
        $newChat      = ($messageCount === 0); // chat disponível mas sem mensagens ainda
        if ($newChat && isset($_SESSION['visited_empty_chats'][$row['id_chat']])) {
            // Já foi aberto pelo usuário; não mostrar mais bolinha roxa
            $newChat = false;
        }
        if ($unreadCount > 0 || $newChat) { $hasBadge = true; }
        $chats[] = [
            'id' => (int) $row['other_id'],
            'chatId' => (int) $row['id_chat'],
            'name' => $row['other_name'],
            'photo' => $row['other_photo'],
            'lastMessageId' => isset($row['last_message_id']) ? (int) $row['last_message_id'] : null,
            'lastMessage' => $row['last_message'] ?? '',
            'lastMessageTime' => $row['last_message_time'] ?? null,
            'online' => false,
            'unread' => $unreadCount,
            'messageCount' => $messageCount,
            'newChat' => $newChat
        ];
    }

    $stmt->close();

    // Adiciona placeholders de solicitacoes sem chat criado (após ler chats existentes)
    if ($isCliente) {
        $sqlSolic = "SELECT s.id_prestadora AS other_id, p.nome AS other_name, COALESCE(p.imgperfil,'../img/SemFoto.jpg') AS other_photo
                     FROM solicitacoes s
                     INNER JOIN prestadora p ON p.id_usuario = s.id_prestadora
                     WHERE s.id_contratante = ?
                       AND NOT EXISTS (SELECT 1 FROM chat c WHERE c.id_cliente = s.id_contratante AND c.id_prestadora = s.id_prestadora)";
        } else {
                $sqlSolic = "SELECT s.id_contratante AS other_id, cl.nome AS other_name, COALESCE(cl.imgperfil, '../img/SemFoto.jpg') AS other_photo
                                         FROM solicitacoes s
                                         INNER JOIN cliente cl ON cl.id_usuario = s.id_contratante
                                         WHERE s.id_prestadora = ?
                                             AND NOT EXISTS (SELECT 1 FROM chat c WHERE c.id_cliente = s.id_contratante AND c.id_prestadora = s.id_prestadora)";
        }
    if ($sol = $conexao->prepare($sqlSolic)) {
        $sol->bind_param('i', $idAtual);
        if ($sol->execute()) {
            $rsSol = $sol->get_result();
            while ($r2 = $rsSol->fetch_assoc()) {
                $hasBadge = true; // novo chat disponível (placeholder)
                $chats[] = [
                    'id' => (int)$r2['other_id'],
                    'chatId' => 0,
                    'name' => $r2['other_name'],
                    'photo' => $r2['other_photo'],
                    'lastMessageId' => null,
                    'lastMessage' => '',
                    'lastMessageTime' => null,
                    'online' => false,
                    'unread' => 0,
                    'messageCount' => 0,
                    'newChat' => true
                ];
            }
        }
        $sol->close();
    }

    if (empty($chats)) {
        // Placeholder chat Avena
        $chats[] = [
            'id' => 0,
            'chatId' => 0,
            'name' => 'Avena',
            'photo' => '../img/avenaChat.png',
            'lastMessageId' => null,
            'lastMessage' => 'Este é o chat, onde você pode conversar com o prestador ou com o cliente para acertar os detalhes do serviço.',
            'lastMessageTime' => null,
            'online' => false,
            'placeholder' => true
        ];
    }

    echo json_encode([
        'ok' => true,
        'badge' => $hasBadge ? 1 : 0,
        'role' => $current['role'],
        'chats' => $chats
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log('getChatList error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
