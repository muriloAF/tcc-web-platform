<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include_once(__DIR__ . '/../php/conexao.php');

//print_r($_SESSION);

// Limpa vestígios antigos
$_SESSION['id_prestadora'] = null;

// Função segura para obter perfil com verificação da coluna imgperfil
function obterPerfil($conexao, $tabela, $idUsuario) {
  $perfil = ['nome'=>null,'imgperfil'=>null];
  $tabelaSegura = ($tabela === 'cliente' || $tabela === 'prestadora') ? $tabela : null;
  if (!$tabelaSegura) { return $perfil; }
  $hasImg = false;
  $colRes = $conexao->query("SHOW COLUMNS FROM $tabelaSegura LIKE 'imgperfil'");
  if ($colRes && $colRes->num_rows > 0) { $hasImg = true; }
  $select = $hasImg ? "SELECT nome, imgperfil FROM $tabelaSegura WHERE id_usuario = ? LIMIT 1" : "SELECT nome FROM $tabelaSegura WHERE id_usuario = ? LIMIT 1";
  $stmt = $conexao->prepare($select);
  if ($stmt) {
    $stmt->bind_param('i', $idUsuario);
    if ($stmt->execute()) {
      $r = $stmt->get_result();
      if ($r && $r->num_rows) {
        $dados = $r->fetch_assoc();
        $perfil['nome'] = $dados['nome'] ?? null;
        if ($hasImg) { $perfil['imgperfil'] = $dados['imgperfil'] ?? null; }
      }
    }
    $stmt->close();
  }
  return $perfil;
}

// Detecta sessão
$perfilLogado = ['nome'=>null,'imgperfil'=>null];
if (!empty($_SESSION['cliente']['id_usuario'])) {
  $perfilLogado = obterPerfil($conexao, 'cliente', (int)$_SESSION['cliente']['id_usuario']);
} elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
  $perfilLogado = obterPerfil($conexao, 'prestadora', (int)$_SESSION['prestadora']['id_usuario']);
}

// Determina link de bem-vindo baseado no tipo de sessão (usa caminho absoluto para consistência)
$homeHref = '../html/Pagina_Inicial.html';
if (!empty($_SESSION['tipo'])) {
  $raw = strtolower((string)$_SESSION['tipo']);
  if (strpos($raw, 'prest') !== false || strpos($raw, 'prof') !== false) {
    $homeHref = '../html/bemVindoPrestadora.php';
  } elseif (strpos($raw, 'client') !== false) {
    $homeHref = '../html/bemVindoCliente.php';
  }
} else {
  // fallback: se existir chave prestadora na sessão, usa prestadora
  if (!empty($_SESSION['prestadora']['id_usuario'])) $homeHref = '../html/bemVindoPrestadora.php';
  elseif (!empty($_SESSION['cliente']['id_usuario'])) $homeHref = '../html/bemVindoCliente.php';
}


// Pega os valores enviados pela URL
$search_servico = isset($_GET['search_servico']) ? trim($_GET['search_servico']) : '';
$search_localizacao = isset($_GET['search_localizacao']) ? trim($_GET['search_localizacao']) : '';

// Query base: só traz quem passou no cadastro
$sql = "SELECT * FROM prestadora WHERE passou_cadastro = 1";

// Adiciona filtros
if (!empty($search_servico)) {
  $sql .= " AND empresa_servicos LIKE '%$search_servico%'";
}

if (!empty($search_localizacao)) {
  $sql .= " AND empresa_localizacao LIKE '%$search_localizacao%'";
}

// Executa e conta resultados
$resultado = mysqli_query($conexao, $sql);
$total = mysqli_num_rows($resultado);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profissionais Próximas | Avena</title>
  <link rel="stylesheet" href="../css/header_nav.css">
  <link rel="stylesheet" href="../css/busca.css">
</head>
<body class="fixed-header-page">

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

  <!-- Header compartilhado -->
  <?php $renderSearchBar = true; $renderNotifications = true; include_once(__DIR__ . '/../php/header_nav.php'); ?>

    <!-- Notificações agora são renderizadas no header quando $renderNotifications=true -->

  <!-- Barra de busca abaixo do header -->
  <!-- Barra de busca agora inline no header -->

  <!-- Caminho de navegação -->
  <nav class="breadcrumb">
    <a href="..\html\Pagina_Inicial.html">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-fill" viewBox="0 0 16 16">
        <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
        <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/>
      </svg>
    </a> / 
    <a href="..\html\busca.php">Busca</a>
  </nav>

  <main class="container">
    <div class="header-section">
      <h2>PROFISSIONAIS PRÓXIMAS</h2>
      <span class="count"><?= $total ?> Profissionais</span>
    </div>

    <!-- Cards dinâmicos -->
    <section class="cards-container">
      <?php if ($total > 0) { ?>
        <?php while ($prof = mysqli_fetch_assoc($resultado)) { 
          // Buscar média e quantidade de avaliações para cada prestadora
          $id_prestadora = (int)$prof['id_usuario'];
          $sqlQtd = "SELECT COUNT(*) AS total FROM avaliacoes WHERE avaliado_id = $id_prestadora AND avaliado_tipo = 'prestadora'";
          $resultQtd = mysqli_query($conexao, $sqlQtd);
          $qtdAvaliacoes = mysqli_fetch_assoc($resultQtd)['total'] ?? 0;
          $sqlMedia = "SELECT AVG(nota) AS media FROM avaliacoes WHERE avaliado_id = $id_prestadora AND avaliado_tipo = 'prestadora'";
          $resultMedia = mysqli_query($conexao, $sqlMedia);
          $mediaAvaliacoes = mysqli_fetch_assoc($resultMedia)['media'];
          $mediaAvaliacoes = $mediaAvaliacoes ? number_format($mediaAvaliacoes, 1) : "0.0";
        ?>
          <a href="..\html\servico.php?id_prestadora=<?= $prof['id_usuario']?>" class="cards-link">
          <div class="card">
            <div class="card-img">
              <img src="<?= $prof['banner1'] ?>" alt="<?= $prof['nome'] ?>">
              <button class="heart-btn">🤍</button>
            </div>
            <div class="card-info">
              <h3><?= $prof['nome'] ?></h3>
              <p><?= $prof['empresa_localizacao'] ?> </p>
              <p><?= $prof['empresa_servicos'] ?> </p>
              <div class="avaliacao-busca" style="margin-top: 6px;">
                <span style="font-size:1.1em; color:#f7b731;">⭐ <?= $mediaAvaliacoes ?></span>
                <span style="font-size:0.95em; color:#888; margin-left:8px;">(<?= $qtdAvaliacoes ?> avaliações)</span>
              </div>
            </div>
          </div>
        </a>
        <?php } ?>
      <?php } else { ?>
        <p style="text-align:center; margin-top:30px;">Nenhuma profissional encontrada 😔</p>
      <?php } ?>  
    </section>
  </main>

  <script>
    // Notificações: comportamento já está no header_nav.php
    // Fallback robusto: reforça a existência e binding do botão de menu
    (function(){
      function ensureBtn(){
        var btn = document.getElementById('menu-btn');
        var cluster = document.querySelector('.global-header .menu-cluster');
        if(!btn && cluster){
          // cria um link com ícone de casa que leva à página de bem-vindo adequada
          btn = document.createElement('a');
          btn.id = 'menu-btn'; btn.className = 'menu-icon'; btn.href = <?= json_encode($homeHref) ?>; btn.setAttribute('aria-label','Página inicial do usuário');
          btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/><path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/></svg>';
          cluster.appendChild(btn);
        }
        // não liga toggles para este ícone; preserva menu se existir (não altera comportamento global)
      }
      ensureBtn();
      setTimeout(ensureBtn, 600);
      document.addEventListener('readystatechange', function(){ if(document.readyState==='complete'){ ensureBtn(); } });
    })();

    document.addEventListener("DOMContentLoaded", () => {
      const hearts = document.querySelectorAll(".heart-btn");
      hearts.forEach(btn => {
        btn.addEventListener("click", () => {
          btn.textContent = btn.textContent === "🤍" ? "❤️" : "🤍";
        });
      });
    });

    // Pesquisa
    const search_servico = document.getElementById('search_servico');
    const search_localizacao = document.getElementById('search_localizacao');

    document.addEventListener("keydown", function(event) {
      if (event.key === "Enter") {
        ProcurarServico();
      }
    });

    function ProcurarServico() {
      const servico = encodeURIComponent(search_servico.value);
      const local = encodeURIComponent(search_localizacao.value);
      window.location = `busca.php?search_servico=${servico}&search_localizacao=${local}`;
    }
  </script>
</body>
<script src="../js/cookies.js"></script>
</html>
