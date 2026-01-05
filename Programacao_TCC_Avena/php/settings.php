<?php
session_start();
include_once(__DIR__ . '/conexao.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se os campos foram enviados
if (
    isset($_POST['id_mensagem']) &&
    isset($_POST['id_chat']) &&
    isset($_POST['id_remetente']) &&
    isset($_POST['id_destinatario']) &&
    isset($_POST['conteudo'])
) {
    // Captura os valores vindos do JavaScript
    $id_mensagem = mysqli_real_escape_string($conexao, $_POST['id_mensagem']);
    $id_chat = mysqli_real_escape_string($conexao, $_POST['id_chat']);
    $id_remetente = mysqli_real_escape_string($conexao, $_POST['id_remetente']);
    $id_destinatario = mysqli_real_escape_string($conexao, $_POST['id_destinatario']);
    $conteudo = mysqli_real_escape_string($conexao, $_POST['conteudo']);

    // === IDENTIFICA QUEM ESTÁ LOGADO ===
    $isCliente = isset($_SESSION['id_cliente']);
    $isPrestadora = isset($_SESSION['id_prestadora']);

    // Define cliente e prestadora com base na sessão
    if ($isCliente) {
        $id_cliente = $_SESSION['id_cliente'];
        $id_prestadora = $id_destinatario;
    } elseif ($isPrestadora) {
        $id_prestadora = $_SESSION['id_prestadora'];
        $id_cliente = $id_destinatario;
    } else {
        echo "Erro: sessão inválida.";
        exit;
    }

    // === VERIFICA SE JÁ EXISTE CHAT ENTRE ELES ===
    $check = $conexao->prepare("SELECT id_chat FROM chat WHERE id_cliente = ? AND id_prestadora = ? LIMIT 1");
    $check->bind_param("ii", $id_cliente, $id_prestadora);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id_chat = $row['id_chat'];
    } else {
        // Cria novo chat se não existir
        $newChat = $conexao->prepare("INSERT INTO chat (id_cliente, id_prestadora, criado_em) VALUES (?, ?, NOW())");
        $newChat->bind_param("ii", $id_cliente, $id_prestadora);
        $newChat->execute();
        $id_chat = $conexao->insert_id;
        $newChat->close();
    }
    $check->close();

    // === SALVA A MENSAGEM ===
    $stmt = $conexao->prepare("
        INSERT INTO mensagem (id_chat, id_de, id_para, conteudo, enviado_em, lido)
        VALUES (?, ?, ?, ?, NOW(), 0)
    ");
    if (!$stmt) {
        echo "Erro ao preparar: " . $conexao->error;
        exit;
    }
    $stmt->bind_param('iiis', $id_chat, $id_remetente, $id_destinatario, $conteudo);

    if ($stmt->execute()) {
        echo "Mensagem salva com sucesso!";
    } else {
        echo "Erro ao salvar: " . $stmt->error;
    }
    $stmt->close();
}
?>
