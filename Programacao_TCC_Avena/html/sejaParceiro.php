<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seja um Parceiro - Avena</title>
  <link rel="stylesheet" href="..\css\sejaParceiro.css">
</head>
<body>

<!-- ===============================
       Modal de Erro
       =============================== -->
  <div id="modalErro" class="modal">
    <div class="modal-content">
      <p id="mensagemErro">E-mail não encontrado!</p>
      <button onclick="fecharModal()">OK</button>
    </div>
  </div>
    

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

   <!-- Menu -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php">Quem somos</a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php"><span class="sejaParceiro">Seja um Parceiro</span></a></li>
      <li><a href=".\Pagina_Inicial.html">Home</a></li>
    </ul>
  </nav>


    

  <!-- Cabeçalho -->
  <header class="header">
    <nav class="navbar">
      <div class="logo">
        <a href=".\Pagina_Inicial.html"><img src="../img/logoAvena.png" alt="Logo Avena"></a>
      </div>

      <div class="menu">
        <a href=".\login.php" class="btn-entrar">Entrar</a>

          <button class="menu-icon" id="menu-btn">&#9776;</button>
        </div>
      </div>
    </nav>
  </header>

  <main class="conteudo">
    <div class="breadcrumb">
      <a href= "Pagina_Inicial.html" style="text-decoration:none;" >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-fill" viewBox="0 0 16 16">
          <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
          <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/>
        </svg>
      </a>
      <span> / Seja um Parceiro</span>
    </div>

    
  <div class="blocoimg">
    <h1 class="titulo">Seja um Parceiro</h1>
    <div class="imagens">
      <img src="../img\fl-conosco1.jpeg" alt="Imagem exemplo 1">
      <img src="../img\fl-conosco2.jpeg" alt="Imagem exemplo 2">
      <img src="../img\fl-conosco3.jpeg" alt="Imagem exemplo 3">
      <img src="../img\fl-conosco4.jpeg" alt="Imagem exemplo 4">
      <img src="../img\fl-conosco5.jpeg" alt="Imagem exemplo 5">
    </div>

    <div class="form-texto">
      <form class="form-parceiro" method="POST" action="sejaParceiro.php">
        <input type="text" placeholder="DIGITE O SEU NOME" name="nome" required>
        <input type="email" placeholder="DIGITE O SEU E-MAIL" name="email" required>
        <textarea placeholder="DIGITE SUA MENSAGEM" rows="5" name="mensagem" required></textarea>
        <button type="submit" class="btn-enviar" name="submit">ENVIAR</button>
      </form>
      <div class="texto">
        <p>Na <strong>Avena</strong>, acreditamos que grandes mudanças nascem da colaboração.</p>

        <p>Buscamos <strong>parceiros que compartilhem da nossa missão</strong> de promover o empoderamento feminino e o fortalecimento do trabalho autônomo.</p>

        <p>Ao se unir a nós, você contribui para <strong>gerar impacto social, ampliar oportunidades e transformar realidades</strong> — juntos, podemos construir um mercado mais justo e humano.</p>
      </div>
    </div>
  </main>
</body>

<script src="..\js\login.js"></script>
<script src="..\js\cookies.js"></script>

<script src="../js/cadastro.js"></script>

</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../phpMailer/Exception.php';
require __DIR__ . '/../phpMailer/PHPMailer.php';
require __DIR__ . '/../phpMailer/SMTP.php';

include_once(__DIR__ . '/../php/conexao.php');

if (isset($_POST['submit'])){
  $nome = $_POST['nome'];
  $email = $_POST['email'];
  $mensagem = $_POST['mensagem'];

  // Instância da classe PHPMailer
  $mail = new PHPMailer(true);
  $mail->CharSet = 'UTF-8';
  $mail->Encoding = 'base64';
  
    try {
      // Configurações do servidor (Gmail)
      $mail->isSMTP();
      $mail->SMTPAuth    = true;
$mail->Username   = 'singularitysolutions.connect1@gmail.com';
$mail->Password   = 'opnedevagjwzmnnr'; // App Password
      $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->SMTPAutoTLS = true;
      $mail->Host        = 'smtp.gmail.com';
      $mail->Port        = 587;

      // Evite desabilitar verificação de certificado salvo em ambiente local
      // $mail->SMTPOptions = [ 'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true] ];

      // Remetente e destinatário
      $mail->setFrom('singularitysolutions.connect1@gmail.com', 'Formulário Avena', false);
      $mail->Sender = 'singularitysolutions.connect1@gmail.com';
      // Quem preencheu o formulário como Reply-To (responder para)
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($email, $nome ?: $email);
      }
      $mail->addAddress('singularitysolutions.connect1@gmail.com', 'Equipe Avena');

        // Conteúdo do e-mail
        $mail->isHTML(true);
        $mail->Subject = 'Novo parceiro interessado';
        $mail->Body = "
            <h2>Novo contato do formulário</h2>
            <p><b>Nome:</b> {$nome}</p>
            <p><b>E-mail:</b> {$email}</p>
            <p><b>Mensagem:</b> {$mensagem}</p>
        ";

        if ($mail->send()) {
          echo "<script>mostrarModal('Email enviado. Agradecemos muito sua parceria, logo entraremos em contato!');</script>";
        } else {
          error_log('PHPMailer send error (sejaParceiro): ' . $mail->ErrorInfo);
          echo "<script>mostrarModal('Não foi possível enviar o e-mail agora. Tente novamente mais tarde.');</script>";
        }
    } catch (Exception $e) {
         error_log('PHPMailer exception (sejaParceiro): ' . $e->getMessage());
         echo "<script>mostrarModal('Erro no envio do e-mail. Tente novamente mais tarde.');</script>";
    }
}

?>