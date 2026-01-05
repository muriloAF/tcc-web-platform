<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once(__DIR__ . '/../php/conexao.php');
// Normalize session (supports both flat and nested formats)
$email = $_SESSION['email'] ?? ($_SESSION['prestadora']['email'] ?? ($_SESSION['cliente']['email'] ?? null));
$tipo  = $_SESSION['tipo']  ?? ($_SESSION['prestadora']['tipo']  ?? ($_SESSION['cliente']['tipo']  ?? null));

if (empty($email) || empty($tipo)) {
  header("Location: ../html/login.php");
  exit;
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

// Compatibilidade de sessão: aceita formatos variados e tenta extrair `id_usuario`
$session_user_id = null;
$session_tipo = null;

// Se a sessão declara explicitamente um tipo, priorize-o (evita confundir quando existem ambas chaves)
if (!empty($_SESSION['tipo'])) {
  $raw_tipo = strtolower((string)$_SESSION['tipo']);
  if (strpos($raw_tipo, 'prest') !== false || strpos($raw_tipo, 'prof') !== false) {
    $session_tipo = 'profissional';
  } elseif (strpos($raw_tipo, 'client') !== false) {
    $session_tipo = 'cliente';
  } else {
    $session_tipo = $raw_tipo;
  }

  // Extrai id conforme o tipo declarado
  if ($session_tipo === 'cliente') {
    if (!empty($_SESSION['cliente']) && is_array($_SESSION['cliente'])) {
      $c = $_SESSION['cliente'];
      if (!empty($c['id_usuario'])) $session_user_id = (int)$c['id_usuario'];
      elseif (!empty($c['usuario']['id_usuario'])) $session_user_id = (int)$c['usuario']['id_usuario'];
      elseif (!empty($c['id'])) $session_user_id = (int)$c['id'];
    }
    if ($session_user_id === null && !empty($_SESSION['id_usuario'])) $session_user_id = (int)$_SESSION['id_usuario'];
  } elseif ($session_tipo === 'profissional') {
    if (!empty($_SESSION['prestadora']) && is_array($_SESSION['prestadora'])) {
      $p = $_SESSION['prestadora'];
      if (!empty($p['id_usuario'])) $session_user_id = (int)$p['id_usuario'];
      elseif (!empty($p['usuario']['id_usuario'])) $session_user_id = (int)$p['usuario']['id_usuario'];
      elseif (!empty($p['id'])) $session_user_id = (int)$p['id'];
    }
    if ($session_user_id === null && !empty($_SESSION['id_usuario'])) $session_user_id = (int)$_SESSION['id_usuario'];
  }
}

// Se não foi possível obter a partir do tipo declarado, tente inferir pelo conteúdo das chaves
if ($session_user_id === null) {
  if (!empty($_SESSION['cliente']) && is_array($_SESSION['cliente'])) {
    $c = $_SESSION['cliente'];
    if (!empty($c['id_usuario'])) {
      $session_user_id = (int)$c['id_usuario'];
      $session_tipo = 'cliente';
    } elseif (!empty($c['usuario']['id_usuario'])) {
      $session_user_id = (int)$c['usuario']['id_usuario'];
      $session_tipo = 'cliente';
    } elseif (!empty($c['id'])) {
      $session_user_id = (int)$c['id'];
      $session_tipo = 'cliente';
    }
  }

  if ($session_user_id === null && !empty($_SESSION['prestadora']) && is_array($_SESSION['prestadora'])) {
    $p = $_SESSION['prestadora'];
    if (!empty($p['id_usuario'])) {
      $session_user_id = (int)$p['id_usuario'];
      $session_tipo = 'profissional';
    } elseif (!empty($p['usuario']['id_usuario'])) {
      $session_user_id = (int)$p['usuario']['id_usuario'];
      $session_tipo = 'profissional';
    } elseif (!empty($p['id'])) {
      $session_user_id = (int)$p['id'];
      $session_tipo = 'profissional';
    }
  }

  // fallback plano
  if ($session_user_id === null && !empty($_SESSION['id_usuario'])) {
    $session_user_id = (int)$_SESSION['id_usuario'];
    $session_tipo = $_SESSION['tipo'] ?? $session_tipo;
  }
}

// Normalizar o tipo de usuário para dois valores esperados: 'cliente' ou 'profissional'
if (!empty($session_tipo)) {
  $raw = strtolower((string)$session_tipo);
  if (strpos($raw, 'prest') !== false || strpos($raw, 'prof') !== false) {
    $session_tipo = 'profissional';
  } elseif (strpos($raw, 'client') !== false) {
    $session_tipo = 'cliente';
  }
}


// pegar id da prestadora pela URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Prestadora não encontrada.";
    exit;
}
$id_prestadora = intval($_GET['id']);

// ===============================
// BUSCAR INFO DA PRESTADORA
// ===============================
$sql_prest = "SELECT * FROM prestadora WHERE id_usuario = '$id_prestadora'";
$res_prest = mysqli_query($conexao, $sql_prest);

if (!$res_prest || mysqli_num_rows($res_prest) == 0) {
    echo "Prestadora não encontrada.";
    exit;
}
$prestadora = mysqli_fetch_assoc($res_prest);

// ===============================
// BUSCAR AVALIAÇÕES (JOINs para pegar nome/foto do avaliador dependendo do tipo)
// ===============================
$sql_aval = "
    SELECT 
        a.*,
        c.nome AS nome_cliente,
        c.imgperfil AS img_cliente,
        p.nome AS nome_prestadora,
        p.imgperfil AS img_prestadora
    FROM avaliacoes a
    LEFT JOIN cliente c 
        ON a.avaliador_id = c.id_usuario 
        AND LOWER(a.avaliador_tipo) = 'cliente'
    LEFT JOIN prestadora p 
        ON a.avaliador_id = p.id_usuario 
        AND LOWER(a.avaliador_tipo) = 'prestadora'
    WHERE a.avaliado_id = '$id_prestadora'
      AND LOWER(a.avaliado_tipo) = 'prestadora'
    ORDER BY a.data_avaliacao DESC
";

$res_aval = mysqli_query($conexao, $sql_aval);
if ($res_aval === false) {
    // debug rápido: mostra erro do MySQL
    die("Erro na consulta de avaliações: " . mysqli_error($conexao));
}

$avaliacoes = [];
$sumNotas = 0;   // soma das notas

if (mysqli_num_rows($res_aval) > 0) {

    while ($row = mysqli_fetch_assoc($res_aval)) {

        // escolher dados corretos do avaliador (cliente ou prestadora)
        if (!empty($row['nome_cliente'])) {
            $row['nome_avaliador'] = $row['nome_cliente'];
            $row['foto_avaliador'] = $row['img_cliente'];
        } else {
            $row['nome_avaliador'] = $row['nome_prestadora'];
            $row['foto_avaliador'] = $row['img_prestadora'];
        }

        // garantir que nota seja int
        $row['nota'] = isset($row['nota']) ? intval($row['nota']) : 0;

          // normalizar caminho da foto do avaliador (convertendo backslashes e garantindo padrão)
          if (!empty($row['foto_avaliador'])) {
            $row['foto_avaliador'] = str_replace('\\', '/', $row['foto_avaliador']);
          } else {
            $row['foto_avaliador'] = '../img/perfilPadrao.png';
          }

        $avaliacoes[] = $row;
        $sumNotas += $row['nota'];
    }

    // calcula total e média
    $totalAval = count($avaliacoes);
    $media = $totalAval > 0 ? ($sumNotas / $totalAval) : 0;

} else {
    // sem linhas
    $totalAval = 0;
    $media = 0;
}

// formato de exibição (opcional)
$media_formatada = number_format($media, 1, ',', '.');

// =================================
// info do usuário logado (se houver)
// =================================
// Status logado e info do usuário
$logado = $session_user_id !== null;
$id_usuario = $session_user_id;

// buscar dados do perfil do usuário logado para header (se existir)
$profLog = null;
if ($logado) {
  if ($session_tipo === 'profissional') {
    $href = '..\\html\\bemVindoPrestadora.php';
    $sqlPrestadora = "SELECT nome, imgperfil FROM prestadora WHERE id_usuario = ".$id_usuario;
        $resultadoPrestadora = mysqli_query($conexao, $sqlPrestadora);
        $profLog = mysqli_fetch_assoc($resultadoPrestadora);
    } else {
    $href = '..\\html\\bemVindoCliente.php';
        $sqlCliente = "SELECT nome, imgperfil FROM cliente WHERE id_usuario = ".$id_usuario;
        $resultadoCliente = mysqli_query($conexao, $sqlCliente);
        $profLog = mysqli_fetch_assoc($resultadoCliente);
    }
} else {
  $href = '..\\html\\Pagina_Inicial.html';
}

// Garantir que $profLog seja um array com valores padrão para evitar acesso a null
if (!is_array($profLog)) {
  $profLog = [
    'nome' => '',
    'imgperfil' => '../img/perfilPadrao.png'
  ];
} else {
  $profLog['nome'] = $profLog['nome'] ?? '';
  $profLog['imgperfil'] = $profLog['imgperfil'] ?? '../img/perfilPadrao.png';
}

// Normalizar barras do caminho da imagem do perfil para garantir compatibilidade
$profLog['imgperfil'] = str_replace('\\', '/', $profLog['imgperfil']);

// Debug opcional: acione adicionando `?debug_session=1` na URL para inspecionar sessão e valores
if (!empty($_GET['debug_session']) && $_GET['debug_session'] == 1) {
  echo "<div style='background:#fff;padding:10px;border:2px solid #c33;'><pre>";
  echo "
=== DEBUG DE SESSÃO (apenas quando ?debug_session=1) ===\n";
  echo "SESSION:\n";
  print_r($_SESSION);
  echo "\n-- derived values --\n";
  echo "session_user_id: "; var_export($session_user_id); echo "\n";
  echo "session_tipo: "; var_export($session_tipo); echo "\n";
  echo "profLog:\n"; print_r($profLog);
  echo "</pre></div>";
}
?>



<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($prestadora['nome']) ?> | Avaliações</title>
  <link rel="stylesheet" href="..\css\servico.css">
  <link rel="stylesheet" href="..\css\avaliacoes.css"> 
</head>
<body>

<!-- ===============================
 Banner de Consentimento
=================================== -->
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

<!-- Modal de Erro -->
<div id="modalErro" class="modal">
  <div class="modal-content">
      <p id="mensagemErro">...</p>
      <button onclick="fecharModal()">OK</button>
  </div>
</div>

<!-- ===============================
 Header
=================================== -->
<header class="header">

  <div class="logo">
    <a href="..\html\Pagina_Inicial.html">
      <img src="..\img\logoAvena.png" alt="Logo Avena">
    </a>
  </div>

  <!-- Menu lateral -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href="quemSomos.php">Quem somos</a></li>
      <li><a href="cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href="sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href="Pagina_Inicial.html"><span class="Home">Home</span></a></li>
    </ul>
  </nav>

  <!-- Botão entrar se não logado -->
  <?php if(!$logado){ ?>
    <a href="..\html\login.php" class="btn-entrar" id="btn-entrar">ENTRAR</a>
  <?php } ?>

  <!-- Usuário logado -->
  <?php if($logado){?>
  <div class="perfil-area" id="perfil-area">
    <span class="nome"><?= htmlspecialchars($profLog['nome']); ?></span>
    <img src="<?= htmlspecialchars($profLog['imgperfil']); ?>" alt="Foto de perfil" class="perfil-foto">
  </div>
  <?php } ?>

</header>

<!-- 
========================
// BOTÃO VOLTAR
========================
-->
<style>
  .arrow-animated {
    padding: 20px 40px;
    width: 30px;  
    height: 30px; 
    animation: floatLeft 1.6s ease-in-out infinite;
  }

  @keyframes floatLeft {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(-2px); }
    100% { transform: translateX(0); }
  }
</style>
<a href= <?= $href?> style="text-decoration:none;">
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


<!-- ===============================
 Breadcrumb
=================================== -->
<nav class="breadcrumb">
  <a href="..\html\Pagina_Inicial.html" style="text-decoration:none;">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-fill" viewBox="0 0 16 16">
      <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
      <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/>
    </svg>
  </a>
  /
  <a href="busca.php" style="text-decoration:none;">Busca</a>
  /
  <a href="busca.php" style="text-decoration:none;">Serviço</a>
  /
  <span>Avaliações de <?= htmlspecialchars($prestadora['nome']) ?></span>
</nav>

<!-- ===============================
   CONTEÚDO PRINCIPAL DA PÁGINA
=================================== -->
<main class="avaliacoes-container">

  <div class="left-list">

    <?php if ($totalAval > 0): ?>
      <?php foreach ($avaliacoes as $a): ?>

        <div class="avaliacao-card">

  <img 
    src="<?= !empty($a['foto_avaliador']) ? htmlspecialchars($a['foto_avaliador']) : '../img/perfilPadrao.png' ?>" 
    class="foto-user"
    alt="Foto do avaliador"
  >

  <div class="avaliacao-info">

    <h3 class="nome-user">
      <?= htmlspecialchars($a['nome_avaliador']) ?>
    </h3>

    <div class="estrelas">
      <?php 
        for ($i = 1; $i <= 5; $i++) {
          echo $i <= $a['nota'] ? "★" : "☆";
        }
      ?>
    </div>

    <p class="comentario">
      <?= htmlspecialchars($a['comentario']) ?>
    </p>

  </div>

  <span class="data-avaliacao">
    <?= date('d/m/Y', strtotime($a['data_avaliacao'])) ?>
  </span>

</div>

      <?php endforeach; ?>
    <?php else: ?>

      <p>Nenhuma avaliação encontrada.</p>

    <?php endif; ?>
  </div>


  <div class="right-summary">
    <h1>AVALIAÇÕES</h1>

    <div class="numero-total">
      <?= $totalAval ?>
    </div>

    <div class="media-estrelas">
      <?php
        if ($totalAval > 0) {
          $rounded = round($media);
          for ($i = 1; $i <= 5; $i++) {
            echo $i <= $rounded ? "★" : "☆";
          }
        } else {
          echo "☆☆☆☆☆";
        }
      ?>
    </div>

    <img src="..\img\avaliacao_icon_3d.png" class="icon-analise">
  </div>

</main>

<script src="../js/login.js"></script>
<script src="..\js\cookies.js"></script>

<script>
// ===========================================================
    // Exibir ou ocultar o botão "ENTRAR" com base no status de login. Se estiver logado as informações do perfil aparecem
    // ===========================================================
      const logado = <?= json_encode($logado) ?>;
      if (!logado) {
        document.getElementById("perfil-area").style.display = "none";
        document.getElementById("btn-entrar").style.display = "block";
      } else {
        document.getElementById("perfil-area").style.display = "block";
        document.getElementById("btn-entrar").style.display = "none";
      }
    // ===========================================================
    // Fim do exibir ou ocultar o botão "ENTRAR" com base no status de login. Se estiver logado as informações do perfil aparecem
    // ===========================================================    
</script>
</body>
</html>
<?php

?>