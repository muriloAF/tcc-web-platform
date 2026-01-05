<?php
session_start();
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');
$conexao->set_charset('utf8mb4');

function resolveCurrentUser() {
    $tipo = $_SESSION['tipo'] ?? null;
    $idPrest = (int) ($_SESSION['id_prestadora'] ?? ($_SESSION['prestadora']['id_usuario'] ?? 0));
    $idCli   = (int) ($_SESSION['id_cliente'] ?? ($_SESSION['cliente']['id_usuario'] ?? 0));
    if ($tipo === 'cliente' || ($idCli && !$idPrest)) return ['role'=>'cliente','id_cliente'=>$idCli,'id_prestadora'=>0];
    if ($tipo === 'profissional' || ($idPrest && !$idCli)) return ['role'=>'prestadora','id_cliente'=>0,'id_prestadora'=>$idPrest];
    return ['role'=>null,'id_cliente'=>0,'id_prestadora'=>0];
}

try {
    $chatId = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
    if ($chatId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'erro'=>'chat_id inválido']); exit; }

    $current = resolveCurrentUser();
    if (!$current['role']) { http_response_code(401); echo json_encode(['ok'=>false,'erro'=>'Não autenticado']); exit; }

    // Permissão ampliada: qualquer usuário autenticado pode limpar mensagens de qualquer chat.
    // AVISO: Remove regra de participação. Risco elevado de uso indevido.
    $stmt = $conexao->prepare('SELECT id_chat FROM chat WHERE id_chat = ? LIMIT 1');
    if (!$stmt) throw new Exception('prepare: '.$conexao->error);
    $stmt->bind_param('i',$chatId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'erro'=>'Chat não encontrado']); exit; }

    $delMsg = $conexao->prepare('DELETE FROM mensagem WHERE id_chat = ?');
    if (!$delMsg) throw new Exception('prepare delete: '.$conexao->error);
    $delMsg->bind_param('i',$chatId);
    $delMsg->execute();
    $afetadas = $delMsg->affected_rows;
    $delMsg->close();

    echo json_encode(['ok'=>true,'mensagens_apagadas'=>$afetadas]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erro'=>'Erro interno','detalhe'=>$e->getMessage()]);
}
