<?php
session_start();
include_once(__DIR__ . '/conexao.php');
header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conexao, 'utf8mb4');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Detecta dinamicamente colunas para evitar erro se migração não aplicada
$hasTipo = false; $hasArquivo = false; $hasLido = false;
try {
    $rc1 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'tipo'");
    if ($rc1 && $rc1->num_rows) $hasTipo = true;
    $rc2 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'arquivo'");
    if ($rc2 && $rc2->num_rows) $hasArquivo = true;
    $rc3 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'lido'");
    if ($rc3 && $rc3->num_rows) $hasLido = true;
} catch (Exception $e) {
    // Ignora
}

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
    $otherId = isset($_GET['other_id']) ? (int) $_GET['other_id'] : 0;
    if ($otherId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'other_id ausente ou inválido']);
        exit;
    }

    $current = resolveCurrentUser();
    if (!$current['role']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Usuário não autenticado (sessão ausente)']);
        exit;
    }

    if ($current['role'] === 'cliente') {
        // cliente abrindo prestadora
        $id_cliente = $current['id_cliente'];
        $id_prestadora = $otherId;

        $chk = $conexao->prepare("SELECT 1 FROM prestadora WHERE id_usuario = ? LIMIT 1");
        if (!$chk) throw new Exception('prepare failed: ' . $conexao->error);
        $chk->bind_param('i', $otherId);
        $chk->execute();
        $rchk = $chk->get_result();
        $chk->close();
        if (!$rchk || $rchk->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Prestadora não encontrada']);
            exit;
        }
        $other_table = 'prestadora';
        $other_param = $otherId;
        $current_user_id = $id_cliente;
    } else {
        // prestadora abrindo cliente
    $id_prestadora = $current['id_prestadora'];
        $id_cliente = $otherId;

    $chk = $conexao->prepare("SELECT 1 FROM cliente WHERE id_usuario = ? LIMIT 1");
        if (!$chk) throw new Exception('prepare failed: ' . $conexao->error);
        $chk->bind_param('i', $otherId);
        $chk->execute();
        $rchk = $chk->get_result();
        $chk->close();
        if (!$rchk || $rchk->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado']);
            exit;
        }
        $other_table = 'cliente';
        $other_param = $otherId;
        $current_user_id = $id_prestadora;
    }

    // busca ou cria chat (tabela chat usa id_cliente / id_prestadora)
    $stmt = $conexao->prepare("SELECT id_chat FROM chat WHERE id_cliente = ? AND id_prestadora = ? LIMIT 1");
    if (!$stmt) throw new Exception('prepare failed: ' . $conexao->error);
    $stmt->bind_param('ii', $id_cliente, $id_prestadora);
    if (!$stmt->execute()) throw new Exception('execute failed: ' . $stmt->error);
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $id_chat = (int)$row['id_chat'];
        $stmt->close();
    } else {
        $stmt->close();
        $ins = $conexao->prepare("INSERT INTO chat (id_cliente, id_prestadora, criado_em) VALUES (?, ?, NOW())");
        if (!$ins) throw new Exception('prepare insert failed: ' . $conexao->error);
        $ins->bind_param('ii', $id_cliente, $id_prestadora);
        if (!$ins->execute()) throw new Exception('execute insert failed: ' . $ins->error);
        $id_chat = (int)$conexao->insert_id;
        $ins->close();
    }

    // Removido registro de visita para manter indicador de novo chat até primeira mensagem real

    // Se existir coluna lido: marcar como lido todas as mensagens destinadas ao usuário atual
    if ($hasLido && $current_user_id) {
        $upd = $conexao->prepare('UPDATE mensagem SET lido=1 WHERE id_chat=? AND id_para=? AND lido=0');
        if ($upd) { $upd->bind_param('ii', $id_chat, $current_user_id); $upd->execute(); $upd->close(); }
    }

    // pega mensagens (ajusta conforme presença de colunas tipo/arquivo)
    $messages = [];
    $cols = "id_mensagem, id_de, id_para, conteudo, enviado_em";
    $cols .= $hasTipo ? ", tipo" : ", 'text' AS tipo";
    $cols .= $hasArquivo ? ", arquivo" : ", NULL AS arquivo";
    $sqlMsg = "SELECT $cols FROM mensagem WHERE id_chat = ? ORDER BY enviado_em ASC, id_mensagem ASC";
    $stmt2 = $conexao->prepare($sqlMsg);
    if ($stmt2) {
        $stmt2->bind_param('i', $id_chat);
        if ($stmt2->execute()) {
            $res2 = $stmt2->get_result();
            while ($m = $res2->fetch_assoc()) {
                $rawConteudo = (isset($m['conteudo']) && !is_null($m['conteudo'])) ? $m['conteudo'] : '';
                $tipoMsg = (isset($m['tipo']) && $m['tipo'] !== null) ? $m['tipo'] : 'text';
                $arquivoMsg = (isset($m['arquivo']) && $m['arquivo'] !== null) ? $m['arquivo'] : null;
                // Parse marcador fallback se não houver colunas de anexos
                if ((!$hasArquivo || !$hasTipo) && $arquivoMsg === null && $tipoMsg === 'text') {
                    if (preg_match('/\[\[ATTACH:type=([^;]+);file=([^\]]+)\]\]/', $rawConteudo, $mm)) {
                        $tipoMsg = $mm[1];
                        $arquivoMsg = $mm[2];
                        // remove marcador da parte visível
                        $rawConteudo = trim(preg_replace('/\[\[ATTACH:type=[^;]+;file=[^\]]+\]\]/','',$rawConteudo));
                    }
                }
                // Determina o "papel" (role) do remetente com base nos ids do chat
                $fromRole = null;
                if (isset($m['id_de'])) {
                    if ($m['id_de'] == $id_cliente) { $fromRole = 'cliente'; }
                    elseif ($m['id_de'] == $id_prestadora) { $fromRole = 'prestadora'; }
                }
                $messages[] = [
                    'id' => isset($m['id_mensagem']) ? (int)$m['id_mensagem'] : null,
                    'de' => isset($m['id_de']) ? (int)$m['id_de'] : null,
                    'para' => isset($m['id_para']) ? (int)$m['id_para'] : null,
                    'conteudo' => $rawConteudo,
                    'enviado_em' => isset($m['enviado_em']) ? $m['enviado_em'] : null,
                    'tipo' => $tipoMsg,
                    'arquivo' => $arquivoMsg,
                    'from_role' => $fromRole
                ];
            }
        }
        $stmt2->close();
    }

    // pega dados do outro usuário (usa id_usuario)
    if ($other_table === 'prestadora') {
        $stmt3 = $conexao->prepare("SELECT nome, COALESCE(imgperfil,'../img/SemFoto.jpg') AS photo FROM prestadora WHERE id_usuario = ? LIMIT 1");
    } else {
        $stmt3 = $conexao->prepare("SELECT nome, COALESCE(imgperfil,'../img/SemFoto.jpg') AS photo FROM cliente WHERE id_usuario = ? LIMIT 1");
    }
    if (!$stmt3) throw new Exception('prepare failed: ' . $conexao->error);
    $stmt3->bind_param('i', $other_param);
    if (!$stmt3->execute()) throw new Exception('execute failed: ' . $stmt3->error);
    $r3 = $stmt3->get_result();
    $other = ($r3 && $r3->num_rows) ? $r3->fetch_assoc() : null;
    $stmt3->close();

    // Marca visita a chat vazio para remover bolinha roxa em próximas listagens
    if (!isset($_SESSION['visited_empty_chats'])) $_SESSION['visited_empty_chats'] = [];
    if (count($messages) === 0) {
        $_SESSION['visited_empty_chats'][$id_chat] = 1;
    }

    echo json_encode([
        'ok' => true,
        'id_chat' => $id_chat,
        'current_user_id' => $current_user_id,
        'current_role' => $current['role'],
        'id_cliente' => $id_cliente,
        'id_prestadora' => $id_prestadora,
        'other' => $other,
        'messages' => $messages,
        'unread_cleared' => true,
        'is_empty' => count($messages) === 0
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log('openChat error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno: ' . $e->getMessage(), 'mysqli_error' => isset($conexao->error) ? $conexao->error : null], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
