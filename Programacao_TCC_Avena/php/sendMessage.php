<?php
session_start();
include_once(__DIR__ . '/conexao.php');
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexao->set_charset('utf8mb4');

function resolveUsuarioAtual()
{
    $tipo = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : null;

    $idCli = 0;
    if (!empty($_SESSION['id_cliente'])) {
        $idCli = (int) $_SESSION['id_cliente'];
    } elseif (!empty($_SESSION['cliente']['id_usuario'])) {
        $idCli = (int) $_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['cliente']['id_cliente'])) {
        $idCli = (int) $_SESSION['cliente']['id_cliente'];
    }

    $idPrest = 0;
    if (!empty($_SESSION['id_prestadora'])) {
        $idPrest = (int) $_SESSION['id_prestadora'];
    } elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
        $idPrest = (int) $_SESSION['prestadora']['id_usuario'];
    } elseif (!empty($_SESSION['prestadora']['id_prestadora'])) {
        $idPrest = (int) $_SESSION['prestadora']['id_prestadora'];
    }

    if ($tipo === 'cliente' && $idCli > 0) {
        return ['role' => 'cliente', 'id_cliente' => $idCli, 'id_prestadora' => 0];
    }
    if ($tipo === 'profissional' && $idPrest > 0) {
        return ['role' => 'prestadora', 'id_cliente' => 0, 'id_prestadora' => $idPrest];
    }

    // Preferir cliente quando ambos disponíveis e tipo não definido/ambíguo
    if ($idCli > 0 && $idPrest > 0) {
        return ['role' => 'cliente', 'id_cliente' => $idCli, 'id_prestadora' => 0];
    }
    if ($idCli > 0) {
        return ['role' => 'cliente', 'id_cliente' => $idCli, 'id_prestadora' => 0];
    }
    if ($idPrest > 0) {
        return ['role' => 'prestadora', 'id_cliente' => 0, 'id_prestadora' => $idPrest];
    }
    return ['role' => null, 'id_cliente' => 0, 'id_prestadora' => 0];
}

try {
    // espera POST: id_para (other user id), conteudo (texto)
    $id_para = isset($_POST['id_para']) ? (int) $_POST['id_para'] : 0;
    $conteudo = isset($_POST['conteudo']) ? trim((string) $_POST['conteudo']) : '';

    if ($id_para <= 0 || $conteudo === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Parâmetros inválidos']);
        exit;
    }

    $usuario = resolveUsuarioAtual();
    if (!$usuario['role']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Usuário não autenticado']);
        exit;
    }

    if ($usuario['role'] === 'cliente') {
        $id_de = (int) $usuario['id_cliente'];
        $id_para_user = $id_para;
        $id_cliente = $id_de;
        $id_prestadora = (int) $id_para_user;
        $chk = $conexao->prepare("SELECT 1 FROM prestadora WHERE id_usuario = ? LIMIT 1");
        $chk->bind_param('i', $id_para_user);
        $chk->execute();
        $rchk = $chk->get_result();
        $chk->close();
        if (!$rchk || $rchk->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Prestadora não encontrada']);
            exit;
        }
    } else {
        $id_de = (int) $usuario['id_prestadora'];
        $id_para_user = $id_para;
        $id_prestadora = $id_de;
        $id_cliente = (int) $id_para_user;
        $chk = $conexao->prepare("SELECT 1 FROM cliente WHERE id_usuario = ? LIMIT 1");
        $chk->bind_param('i', $id_para_user);
        $chk->execute();
        $rchk = $chk->get_result();
        $chk->close();
        if (!$rchk || $rchk->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado']);
            exit;
        }
    }

    // encontra ou cria chat
    $stmt = $conexao->prepare("SELECT id_chat FROM chat WHERE id_cliente = ? AND id_prestadora = ? LIMIT 1");
    $stmt->bind_param('ii', $id_cliente, $id_prestadora);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $id_chat = (int)$row['id_chat'];
        $stmt->close();
    } else {
        $stmt->close();
        $ins = $conexao->prepare("INSERT INTO chat (id_cliente, id_prestadora, criado_em) VALUES (?, ?, NOW())");
        $ins->bind_param('ii', $id_cliente, $id_prestadora);
        $ins->execute();
        $id_chat = (int)$conexao->insert_id;
        $ins->close();
    }

    // insere mensagem (garante uso de utf8mb4 na conexão)
    $ins2 = $conexao->prepare("INSERT INTO mensagem (id_chat, id_de, id_para, conteudo, enviado_em) VALUES (?, ?, ?, ?, NOW())");
    $ins2->bind_param('iiis', $id_chat, $id_de, $id_para_user, $conteudo);
    $ins2->execute();
    $id_mensagem = (int)$conexao->insert_id;
    $ins2->close();

    // retorna OK com id + timestamp (servidor)
    $row = $conexao->query("SELECT enviado_em FROM mensagem WHERE id_mensagem = " . $id_mensagem . " LIMIT 1")->fetch_assoc();
    $enviado_em = $row['enviado_em'] ?? null;

    echo json_encode([
        'ok' => true,
        'id_mensagem' => $id_mensagem,
        'enviado_em' => $enviado_em
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (mysqli_sql_exception $e) {
    error_log('sendMessage mysqli error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro no banco: ' . $e->getMessage(), 'mysqli_error' => $conexao->error ?? null], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    error_log('sendMessage error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
