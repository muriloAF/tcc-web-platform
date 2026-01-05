<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha</title>
    <link rel="stylesheet" href="..\css\recuperaSenha.css">
</head>
<body>

  <!-- ===============================
     Banner de Consentimento de Cookies - Singularity Solutions
     =============================== -->
<div id="cookie-banner" class="cookie-banner">
  <div class="cookie-content">
  <h4>Privacidade e Cookies</h4>
  <p>
        A Singularity Solutions utiliza cookies para oferecer uma experiência mais personalizada,
        melhorar o desempenho da plataforma e garantir o funcionamento seguro dos serviços.
        Ao aceitar, você concorda com o uso de cookies conforme nossa
    <a href="..\img\AVENA - Termos de Uso e Política de Privacidade.pdf" target="_blank">Política de Privacidade</a>.
  </p>
  <div class="cookie-buttons">
  <button id="accept-cookies" class="cookie-btn accept">Aceitar</button>
  <button id="decline-cookies" class="cookie-btn decline">Recusar</button>
  </div>
  </div>
  </div>


    <!-- Mensagem -->
    <div id="modalErro" class="modal">
        <div class="modal-content">
            <p id="mensagemErro">E-mail não encontrado!</p>
            <button onclick="fecharModal()">OK</button>
        </div>
    </div>

    <form action="..\html\recuperaSenha.php" method="POST">
        <div class="mb-3">
          <label for="email">E-mail</label>
          <input type="email" name="email" id="email" class="form-control" required>
        </div>
         
        <label for="tipo">Tipo de cadastro</label>
        <select id="tipo" name="tipo" required>
          <option value="">Selecione...</option>
          <option value="profissional">Profissional</option>
          <option value="contratante">Contratante</option>
        </select>

        </div>
        <button type="submit" class="btn-login" name="submit" >ENTRAR</button>
        <p class="signup">Ainda não está no Avena? <a href="cadastro.php">Crie uma Conta.</a></p>
      </form>
</body>
<script src="..\js\recuperaSenha.js"></script>
<script src="..\js\cookies.js"></script>
</html>

<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../phpMailer/Exception.php';
require __DIR__ . '/../phpMailer/PHPMailer.php';
require __DIR__ . '/../phpMailer/SMTP.php';

include_once(__DIR__ . '/../php/conexao.php');

if (isset($_POST['submit'])) {
    $conexao->set_charset('utf8mb4');
    $email = trim((string)$_POST['email']);
    $tipo  = $_POST['tipo'];

    // Gera nova senha
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $NovaSenha  = substr(str_shuffle($caracteres), 0, 5);

    $update = false;

    // Verifica tipo
    if ($tipo == 'profissional') {
        $stmt = $conexao->prepare("SELECT 1 FROM prestadora WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows > 0) {
            $upd = $conexao->prepare("UPDATE prestadora SET senha = ? WHERE email = ?");
            $upd->bind_param('ss', $NovaSenha, $email);
            $update = $upd->execute();
            $upd->close();
        }
    } else {
        $stmt = $conexao->prepare("SELECT 1 FROM cliente WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows > 0) {
            $upd = $conexao->prepare("UPDATE cliente SET senha = ? WHERE email = ?");
            $upd->bind_param('ss', $NovaSenha, $email);
            $update = $upd->execute();
            $upd->close();
        }
    }

    if ($update) {

        // Envia e-mail
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {

            // Debug para o LOG (não mostra para o usuário)
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer DEBUG [$level]: $str");
            };

            // Config SMTP SSL
           $mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'singularitysolutions.connect1@gmail.com';
$mail->Password   = 'opnedevagjwzmnnr'; // App Password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
$mail->Port       = 587;
$mail->SMTPAutoTLS = true;
$mail->CharSet    = 'UTF-8';

            // Remetente
            $mail->setFrom('singularitysolutions.connect1@gmail.com', 'Singularity Solutions');
            $mail->addReplyTo('singularitysolutions.connect1@gmail.com', 'Suporte Avena');

            // Destinatário
            $mail->addAddress($email);

            // Conteúdo — primeiro texto simples para garantir envio
            $mail->isHTML(false);
            $mail->Subject = 'Recuperação de senha - Singularity Solutions';
            $mail->Body    =
                "Sua nova senha é: $NovaSenha\n\n" .
                "Acesse seu perfil e altere a senha para uma de sua escolha.";

            // Envia
            if ($mail->send()) {
                echo "<script>mostrarModal('Senha atualizada. Enviamos a nova senha para o seu e-mail.');</script>";
            } else {
                echo "<script>mostrarModal('Senha atualizada. Mas não conseguimos enviar o e-mail agora.');</script>";
            }

        } catch (Exception $e) {
            echo "<script>mostrarModal('Senha atualizada, mas houve erro ao enviar o e-mail: " . addslashes($e->getMessage()) . "');</script>";
        }

    } else {
        echo "<script>mostrarModal('Esse e-mail não está cadastrado!');</script>";
    }
}
?>