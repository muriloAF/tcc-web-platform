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

    // Permissão ampliada: qualquer usuário autenticado pode apagar qualquer chat.
    // AVISO: Isto remove proteção de participação. Alto risco de abuso.
    // Captura também id_cliente e id_prestadora para excluir solicitacao e evitar reaparecer como placeholder.
    $stmt = $conexao->prepare('SELECT id_chat, id_cliente, id_prestadora FROM chat WHERE id_chat = ? LIMIT 1');
    if (!$stmt) throw new Exception('prepare: '.$conexao->error);
    $stmt->bind_param('i',$chatId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'erro'=>'Chat não encontrado']); exit; }

    $conexao->begin_transaction();
    $delMsg = $conexao->prepare('DELETE FROM mensagem WHERE id_chat = ?');
    if ($delMsg) { $delMsg->bind_param('i',$chatId); $delMsg->execute(); $delMsg->close(); }
    // Remove solicitacao correspondente (se existir)
    if (isset($row['id_cliente']) && isset($row['id_prestadora'])) {
        $idCli = (int)$row['id_cliente'];
        $idPrest = (int)$row['id_prestadora'];
        $delSol = $conexao->prepare('DELETE FROM solicitacoes WHERE id_contratante = ? AND id_prestadora = ?');
        if ($delSol) { $delSol->bind_param('ii',$idCli,$idPrest); $delSol->execute(); $delSol->close(); }
    }
    $delChat = $conexao->prepare('DELETE FROM chat WHERE id_chat = ?');
    if ($delChat) { $delChat->bind_param('i',$chatId); $delChat->execute(); $delChat->close(); }
    $conexao->commit();

    echo json_encode(['ok'=>true,'apagado'=>['chat_id'=>$chatId,'solicitacao_removida'=>isset($delSol)]]);
} catch (Throwable $e) {
    if ($conexao && $conexao->errno) { @$conexao->rollback(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'erro'=>'Erro interno','detalhe'=>$e->getMessage()]);
}
