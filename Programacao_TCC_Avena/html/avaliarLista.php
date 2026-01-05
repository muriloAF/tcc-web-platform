<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once(__DIR__ . '/../php/conexao.php');

// Normalize session (supports both flat and nested formats)
$email = $_SESSION['email'] ?? ($_SESSION['prestadora']['email'] ?? ($_SESSION['cliente']['email'] ?? null));
$tipo  = $_SESSION['tipo']  ?? ($_SESSION['prestadora']['tipo']  ?? ($_SESSION['cliente']['tipo']  ?? null));

if (empty($email) || empty($tipo)) {
  echo '<script> window.location.href = "..\\html\\login.php"</script>'; exit;
}

if ($tipo === 'profissional') {
  $stmt = $conexao->prepare("SELECT passou_cadastro FROM prestadora WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$row || $row['passou_cadastro'] == 0) {
    header('Location: ../html/AdicaoServicoPrestadora.php?status=1');
    exit;
  }
} else {
  $stmt = $conexao->prepare("SELECT passou_cadastro FROM cliente WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$row || $row['passou_cadastro'] == 0) {
    header('Location: ../html/AdicaoPerfilCliente.php?status=1');
    exit;
  }
}
// Detecta sessão (prioriza $_SESSION['tipo'] quando disponível)
$session_user_id = null;
$session_tipo = null;

if (!empty($_SESSION['tipo'])) {
  $raw = strtolower((string)$_SESSION['tipo']);
  if (strpos($raw, 'prest') !== false || strpos($raw, 'prof') !== false) {
    $session_tipo = 'profissional';
    if (!empty($_SESSION['prestadora']['id_usuario'])) {
      $session_user_id = (int)$_SESSION['prestadora']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario'])) {
      $session_user_id = (int)$_SESSION['id_usuario'];
    }
  } elseif (strpos($raw, 'client') !== false) {
    $session_tipo = 'cliente';
    if (!empty($_SESSION['cliente']['id_usuario'])) {
      $session_user_id = (int)$_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario'])) {
      $session_user_id = (int)$_SESSION['id_usuario'];
    }
  } else {
    $session_tipo = $raw;
    if (!empty($_SESSION['id_usuario'])) $session_user_id = (int)$_SESSION['id_usuario'];
  }
}

// Se não conseguiu a partir do tipo declarado, tenta inferir
if ($session_user_id === null) {
  if (!empty($_SESSION['cliente']['id_usuario'])) { $session_user_id = (int)$_SESSION['cliente']['id_usuario']; $session_tipo = 'cliente'; }
  elseif (!empty($_SESSION['prestadora']['id_usuario'])) { $session_user_id = (int)$_SESSION['prestadora']['id_usuario']; $session_tipo = 'profissional'; }
  elseif (!empty($_SESSION['id_usuario'])) { $session_user_id = (int)$_SESSION['id_usuario']; $session_tipo = $_SESSION['tipo'] ?? null; }
}

if (!$session_user_id) { echo '<script> window.location.href = "..\\html\\login.php"</script>'; exit; }

$logado_id = $session_user_id;
$tipo = $session_tipo;

// Se cliente → lista prestadoras
if ($tipo == "cliente") {
    $href = '..\html\bemVindoCliente.php';
    $sql = "SELECT id_usuario, nome, imgperfil FROM prestadora";
    $sqlUser = "SELECT nome, imgperfil FROM cliente WHERE id_usuario = $logado_id";
}
// Se prestadora → lista clientes
else {
    $href = '..\html\bemVindoPrestadora.php'; 
    $sql = "SELECT id_usuario, nome, imgperfil FROM cliente";
    $sqlUser = "SELECT nome, imgperfil FROM prestadora WHERE id_usuario = $logado_id";
}

$result = mysqli_query($conexao, $sql);
$resultUser = mysqli_query($conexao, $sqlUser);
$info = mysqli_fetch_assoc($resultUser);
// Fallbacks seguros para evitar imagens/nomes vazios
$info['nome'] = $info['nome'] ?? 'Usuário';
$info['imgperfil'] = !empty($info['imgperfil']) ? str_replace('\\','/',$info['imgperfil']) : '../img/SemFoto.jpg';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel - Avena</title>

 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

 
  

  <link rel="stylesheet" href="../css/avaliarLista.css"> 
</head>
<body>



  <!-- ===============================
       Banner de Consentimento de Cookies
       =============================== -->
  <div id="cookie-banner" class="cookie-banner">
    <div class="cookie-content">
      <h4>Privacidade e Cookies</h4>
      <p>
        A Singularity Solutions utiliza cookies para oferecer uma experiência mais personalizada,
        melhorar o desempenho da plataforma e garantir o funcionamento seguro dos serviços.
        Ao aceitar, você concorda com o uso de cookies conforme nossa
        <a href="..\img\AVENA - Termos de Uso e Política de Privacidade.pdf" target="_blank">
          Política de Privacidade
        </a>.
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
            <p id="mensagemErro">E-mail ou senha incorretos</p>
            <button onclick="fecharModal()">OK</button>
        </div>
    </div>


  <header>
    <nav>
      <div class="logo">
        <a href="Pagina_Inicial.html">
          <img src="../img/logoAvena.png" alt="Logo Avena">
        </a>
      </div>

      <div class="perfil-area">
        <span class="nome"><?= htmlspecialchars($info['nome']) ?></span>

        <img src="<?= htmlspecialchars($info['imgperfil']) ?>" alt="Foto de perfil" class="perfil-foto">

      </div>
    </nav>
  </header>

<!-- 
========================
// BOTÃO VOLTAR
========================
-->
<style>
  .arrow-animated {
    margin-left: 20px;
    margin-bottom: 10px;
    color: #917ba4;
    width: 30px;
    height: 30px;
    animation: floatLeft 1.6s ease-in-out infinite;
  }

  @keyframes floatLeft {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(-2px); }
    100% { transform: translateX(0); }
  }
  h2{
    margin-left: 20px;  
  }
</style>
<a href= <?= $href?>>
<svg xmlns="http://www.w3.org/2000/svg" 
     width="20" height="20" fill="currentColor" 
     class="bi bi-arrow-left arrow-animated"
     viewBox="0 0 16 16">
  <path fill-rule="evenodd" 
        d="M5.854 4.146a.5.5 0 0 1 0 .708L3.707 7H14.5a.5.5 0 0 1 0 1H3.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0z"/>
</svg>
</a>
<!-- 
========================
// BOTÃO VOLTAR
========================
-->
  




  <main>
    <h2>Escolha quem você quer avaliar</h2>

<div class="lista">
<?php while($row = mysqli_fetch_assoc($result)): ?>
  <?php $thumb = !empty($row['imgperfil']) ? $row['imgperfil'] : '../img/SemFoto.jpg'; ?>
  <div class="card">
    <img src="<?= htmlspecialchars($thumb) ?>" alt="Foto de perfil" class="perfil-foto">
    <p><?= htmlspecialchars($row["nome"]) ?></p>
    <a href="avaliar.php?id=<?= urlencode($row['id_usuario']) ?>">
      <button class="btn-avaliar">Avaliar</button>
    </a>
  </div>
<?php endwhile; ?>
</div>
  </main>

 

</body>
   <script src="../js/login.js"></script> 
  <script src="..\js\cookies.js"></script>
</html>

<script>

    const urlParams = new URLSearchParams(window.location.search);
    const ok = urlParams.get('ok');

    if (ok === '1') {
        mostrarModal("Avaliação salva com sucesso!");
    }

</script>
