<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once(__DIR__ . '/../php/conexao.php'); // ajuste caminho se precisar


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

// -------------------- TRATAMENTO DO POST --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['solicitar']) || isset($_POST['ajax']))) {
  // Detecta id do cliente logado usando novo padrão de sessão
  $id_contratante = null;
  $sess_tipo_raw = strtolower((string)($_SESSION['tipo'] ?? ''));

  // Se a sessão declara explicitamente que é cliente, permita usar o id de topo ou o id em ['cliente']
  if (strpos($sess_tipo_raw, 'client') !== false) {
    if (!empty($_SESSION['cliente']['id_usuario'])) {
      $id_contratante = (int)$_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario'])) {
      $id_contratante = (int)$_SESSION['id_usuario'];
    }
  }

  // Se a sessão declara profissional, bloqueia desde o início
  if (strpos($sess_tipo_raw, 'prest') !== false || strpos($sess_tipo_raw, 'prof') !== false) {
    if (!empty($_POST['ajax'])) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'erro'=>'Prestadoras não podem solicitar serviços.']);
      exit;
    } else {
      header("Location: ../html/login.php");
      exit;
    }
  }

  // Se tipo não declarado, inferir: prefira cliente se ['cliente'] existe or top-level id without ['prestadora']
  if ($id_contratante === null) {
    if (!empty($_SESSION['cliente']['id_usuario'])) {
      $id_contratante = (int)$_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario']) && empty($_SESSION['prestadora'])) {
      // top-level id and no prestadora array -> tratar como cliente
      $id_contratante = (int)$_SESSION['id_usuario'];
    } elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
      // existe prestadora e tipo não declarou cliente -> tratar como prestadora (bloqueio)
      if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'erro'=>'Prestadoras não podem solicitar serviços.']);
        exit;
      } else {
        header("Location: ../html/login.php");
        exit;
      }
    }
  }

  if (!$id_contratante) {
    if (!empty($_POST['ajax'])) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'erro'=>'Faça login para solicitar.']);
      exit;
    } else {
      header("Location: ../html/login.php");
      exit;
    }
  }

    // ID DA PRESTADORA
    $id_prestadora = intval($_POST['id_prestadora']);

    // INSERIR NA TABELA
    $insert = mysqli_prepare(
        $conexao,
        "INSERT INTO solicitacoes (id_contratante, id_prestadora) VALUES (?, ?)"
    );

    mysqli_stmt_bind_param($insert, "ii", $id_contratante, $id_prestadora);
    $ok = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    if ($ok) {
        // Cria chat entre cliente (id_contratante) e prestadora (id_prestadora) se não existir
        try {
          // Verifica se já há chat com os dois participantes
          $stmt = $conexao->prepare("SELECT c.id FROM chats c JOIN chat_participants p1 ON p1.chat_id=c.id AND p1.user_id=? JOIN chat_participants p2 ON p2.chat_id=c.id AND p2.user_id=? LIMIT 1");
          if($stmt){ $stmt->bind_param('ii', $id_contratante, $id_prestadora); $stmt->execute(); $res=$stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; $stmt->close(); }
          $chat_id = $row['id'] ?? null;
          if(!$chat_id){
            // Cria chat
            $last = 'Solicitação enviada';
            $stmt = $conexao->prepare("INSERT INTO chats (last_message, last_message_at) VALUES (?, NOW())");
            if($stmt){ $stmt->bind_param('s',$last); $stmt->execute(); $chat_id = $stmt->insert_id; $stmt->close(); }
            // Participantes
            if($chat_id){
              $ins = $conexao->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?), (?, ?)");
              if($ins){ $ins->bind_param('iiii', $chat_id, $id_contratante, $chat_id, $id_prestadora); $ins->execute(); $ins->close(); }
              // Marca como não lida para ambos inicialmente (cliente verá badge vermelha)
              $iu = $conexao->prepare("INSERT INTO chat_unread (chat_id, user_id, unread_count) VALUES (?, ?, 1), (?, ?, 1) ON DUPLICATE KEY UPDATE unread_count = VALUES(unread_count)");
              if($iu){ $iu->bind_param('iiii', $chat_id, $id_contratante, $chat_id, $id_prestadora); $iu->execute(); $iu->close(); }
            }
          }
          // Notificação para o cliente: novo chat disponível
          $msgNotif = 'Um novo chat com a prestadora foi criado.';
          $stmt = $conexao->prepare("INSERT INTO notificacoes (id_usuario, mensagem, visualizado, data) VALUES (?, ?, 0, NOW())");
          if($stmt){ $stmt->bind_param('is', $id_contratante, $msgNotif); $stmt->execute(); $stmt->close(); }
        } catch (Throwable $e) { /* silencia para não quebrar fluxo */ }
        // Garantir que a sessão reflita que o usuário atual é o cliente
        // (algumas páginas/endpoint esperam $_SESSION['id_cliente'] ou $_SESSION['cliente']['id_usuario'])
        try {
          if (!empty($id_contratante)) {
            if (empty($_SESSION['id_cliente'])) $_SESSION['id_cliente'] = (int)$id_contratante;
            if (empty($_SESSION['cliente']) || empty($_SESSION['cliente']['id_usuario'])) {
              if (!isset($_SESSION['cliente'])) $_SESSION['cliente'] = [];
              $_SESSION['cliente']['id_usuario'] = (int)$id_contratante;
            }
            // Define tipo quando não estiver definido para evitar ambiguidade
            if (empty($_SESSION['tipo'])) $_SESSION['tipo'] = 'cliente';
          }
        } catch (Throwable $e) { /* ignora falhas ao manipular sessão */ }
        // Garantia de compatibilidade com o sistema de chat legada
        // Alguns endpoints (openChat.php, getChatList.php, listarChats.php)
        // esperam uma tabela `chat` com colunas `id_chat, id_cliente, id_prestadora`.
        // Se a solicitação criou apenas o novo esquema (`chats`), criamos aqui
        // o registro legada para que o contato apareça imediatamente para o cliente.
        try {
          $legacyChatId = null;
          $stmtL = $conexao->prepare("SELECT id_chat FROM chat WHERE id_cliente = ? AND id_prestadora = ? LIMIT 1");
          if ($stmtL) {
            $stmtL->bind_param('ii', $id_contratante, $id_prestadora);
            $stmtL->execute();
            $rL = $stmtL->get_result();
            if ($rL && $rL->num_rows > 0) {
              $legacyChatId = (int)$rL->fetch_assoc()['id_chat'];
            }
            $stmtL->close();
          }
          if (!$legacyChatId) {
            $insL = $conexao->prepare("INSERT INTO chat (id_cliente, id_prestadora, criado_em) VALUES (?, ?, NOW())");
            if ($insL) {
              $insL->bind_param('ii', $id_contratante, $id_prestadora);
              $insL->execute();
              $legacyChatId = (int)$conexao->insert_id;
              $insL->close();
            }
          }
        } catch (Throwable $e) { /* ignora erros de compatibilidade */ }
        if (!empty($_POST['ajax'])) {
          header('Content-Type: application/json');
          echo json_encode(['ok'=>true,'id_prestadora'=>$id_prestadora]);
          exit;
        } else {
          header("Location: servico.php?id_prestadora={$id_prestadora}&success=1");
          exit;
        }
    } else {
        if (!empty($_POST['ajax'])) {
          header('Content-Type: application/json');
          echo json_encode(['ok'=>false,'erro'=>'Erro ao inserir solicitação.']);
          exit;
        } else {
          echo "<script>mostrarModal('Erro ao solicitar serviço. Veja se id_contratante e id_prestadora existem.');</script>";
        }
    }
}
// -------------------- FIM TRATAMENTO POST --------------------

// GET id_prestadora (a que veio do card)
if (!isset($_GET['id_prestadora'])) {
    header("Location: busca.php");
    exit;
}
$id_prestadora = intval($_GET['id_prestadora']);
$_SESSION['id_prestadora'] = $id_prestadora;

// busca dados da prestadora
$sql = "SELECT * FROM prestadora WHERE id_usuario = $id_prestadora";
$resultado = mysqli_query($conexao, $sql);
if (mysqli_num_rows($resultado) == 0) {
    header("Location: busca.php");
    exit;
}
$prof = mysqli_fetch_assoc($resultado);

// ======================
// BUSCAR AVALIAÇÕES DA PRESTADORA
// ======================

// QUANTIDADE DE AVALIAÇÕES
$sqlQtd = "
    SELECT COUNT(*) AS total 
    FROM avaliacoes 
    WHERE avaliado_id = $id_prestadora 
      AND avaliado_tipo = 'prestadora'
";
$resultQtd = mysqli_query($conexao, $sqlQtd);
$qtdAvaliacoes = mysqli_fetch_assoc($resultQtd)['total'] ?? 0;

// MÉDIA DAS NOTAS
$sqlMedia = "
    SELECT AVG(nota) AS media 
    FROM avaliacoes 
    WHERE avaliado_id = $id_prestadora 
      AND avaliado_tipo = 'prestadora'
";


// =================================
// info do usuário logado (se houver)
// =================================
$logado = isset($_SESSION['id_usuario']);
$id_usuario = $logado ? intval($_SESSION['id_usuario']) : null;

// buscar dados do perfil do usuário logado para header (se existir)
$profLog = null;
if ($logado) {
    if ($_SESSION['tipo'] === 'profissional') {
        $href = '..\html\bemVindoPrestadora.php';
        $sqlPrestadora = "SELECT nome, imgperfil FROM prestadora WHERE id_usuario = ".$id_usuario;
        $resultadoPrestadora = mysqli_query($conexao, $sqlPrestadora);
        $profLog = mysqli_fetch_assoc($resultadoPrestadora);
    } else {
        $href = '..\html\bemVindoCliente.php';
        $sqlCliente = "SELECT nome, imgperfil FROM cliente WHERE id_usuario = ".$id_usuario;
        $resultadoCliente = mysqli_query($conexao, $sqlCliente);
        $profLog = mysqli_fetch_assoc($resultadoCliente);
    }
}else {
    $href = '..\html\Pagina_Inicial.html';
}


$resultMedia = mysqli_query($conexao, $sqlMedia);
$mediaAvaliacoes = mysqli_fetch_assoc($resultMedia)['media'];
$mediaAvaliacoes = $mediaAvaliacoes ? number_format($mediaAvaliacoes, 1) : "0.0";
// ===== Detecta usuário logado (prioriza $_SESSION['tipo'] quando disponível) =====
$profLog = null; $logado = false; $id_usuario = null; $tipoSess = null;

// Prioriza tipo declarado na sessão para evitar inferências incorretas
if (!empty($_SESSION['tipo'])) {
  $raw = strtolower((string)$_SESSION['tipo']);
  if (strpos($raw, 'prest') !== false || strpos($raw, 'prof') !== false) {
    $tipoSess = 'profissional';
    if (!empty($_SESSION['prestadora']['id_usuario'])) {
      $id_usuario = (int)$_SESSION['prestadora']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario'])) {
      $id_usuario = (int)$_SESSION['id_usuario'];
    }
  } elseif (strpos($raw, 'client') !== false) {
    $tipoSess = 'cliente';
    if (!empty($_SESSION['cliente']['id_usuario'])) {
      $id_usuario = (int)$_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['id_usuario'])) {
      $id_usuario = (int)$_SESSION['id_usuario'];
    }
  } else {
    $tipoSess = $raw;
    if (!empty($_SESSION['id_usuario'])) $id_usuario = (int)$_SESSION['id_usuario'];
  }
}

// Se não conseguiu a partir do tipo declarado, tenta inferir pelo conteúdo das chaves
if ($id_usuario === null) {
  if (!empty($_SESSION['cliente']['id_usuario'])) {
    $id_usuario = (int)$_SESSION['cliente']['id_usuario'];
    $tipoSess = 'cliente';
  } elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
    $id_usuario = (int)$_SESSION['prestadora']['id_usuario'];
    $tipoSess = 'profissional';
  } elseif (!empty($_SESSION['id_usuario'])) {
    $id_usuario = (int)$_SESSION['id_usuario'];
    $tipoSess = $_SESSION['tipo'] ?? $tipoSess;
  }
}

if ($id_usuario !== null) {
  $logado = true;
  if ($tipoSess === 'profissional') {
    $stmt = $conexao->prepare("SELECT * FROM prestadora WHERE id_usuario = ? LIMIT 1");
    if ($stmt) { $stmt->bind_param('i',$id_usuario); if ($stmt->execute()) { $res = $stmt->get_result(); $profLog = $res->fetch_assoc(); } $stmt->close(); }
  } else {
    $stmt = $conexao->prepare("SELECT * FROM cliente WHERE id_usuario = ? LIMIT 1");
    if ($stmt) { $stmt->bind_param('i',$id_usuario); if ($stmt->execute()) { $res = $stmt->get_result(); $profLog = $res->fetch_assoc(); } $stmt->close(); }
  }
  if (!$profLog) { $profLog = ['nome'=>'Usuário','imgperfil'=>'../img/SemFoto.jpg']; }
  if (empty($profLog['imgperfil'])) $profLog['imgperfil'] = '../img/SemFoto.jpg';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($prof['nome']) ?> | Avena</title>
  <link rel="stylesheet" href="../css/servico.css">
  <link rel="stylesheet" href="../css/header_nav.css">
</head>
<body class="fixed-header-page">

   <!-- ===============================
     Banner de Consentimento de Cookies - Singularity Solutions
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


  <!-- Mensagem -->
    <div id="modalErro" class="modal">
        <div class="modal-content">
            <p id="mensagemErro">...</p>
            <button onclick="fecharModal()">OK</button>
        </div>
    </div>

  <?php $renderSearchBar = true; $renderNotifications = true; include_once(__DIR__ . '/../php/header_nav.php'); ?>

  <nav class="breadcrumb">
    <a href= <?= $href?> style="text-decoration:none;">

    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-fill" viewBox="0 0 16 16">
        <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
        <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/>
      </svg>
    </a>/
    <a href="busca.php" style="text-decoration:none;">Busca</a> /
    <span><?= htmlspecialchars($prof['nome']) ?></span>
  </nav>

  <main class="container">
    <section class="info">
    <div class="abaixo-img">
      <div class="perfil">
        <div class="perfil">
          <div class="topo">
            <img src="<?= htmlspecialchars($prof['imgperfil']) ?>" class="foto-perfil" alt="<?= htmlspecialchars($prof['nome']) ?>">
          <div class="lado-img">
              <h1><?= htmlspecialchars($prof['empresa_nome']) ?></h1>
          </div>
        </div>


</div>
        <h3>Sobre <?= htmlspecialchars($prof['empresa_nome']) ?></h3>
      </div>

    <div class="avaliacao">
    ⭐ <?= $mediaAvaliacoes ?>
</div>

<a href="avaliacoes.php?id=<?= $id_prestadora ?>" class="avaliacoes">
    <?= $qtdAvaliacoes ?> Avaliações
</a>

        
            
            <p><?= nl2br(htmlspecialchars($prof['empresa_biografia'])) ?></p>
            <p><?= nl2br(htmlspecialchars($prof['empresa_servicos'])) ?></p>

            <p><strong>Contato:</strong> <?= htmlspecialchars($prof['empresa_telefone']) ?></p>

            <?php if ($logado): ?>
              <form id="solicitacao-form" method="POST" action="">
                <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($id_usuario) ?>">
                <input type="hidden" name="id_prestadora" value="<?= htmlspecialchars($id_prestadora) ?>">
                <?php if($_SESSION['tipo'] === 'profissional'): ?>
                  <button type="button" onclick="mostrarModal('Prestadoras não podem solicitar serviços.');" class="solicitar-btn">
                    Solicitar Serviço
                  </button>
                <?php else: ?>
                  <button type="submit" name="solicitar" class="solicitar-btn">Solicitar Serviço</button>
                <?php endif; ?>
              </form>
            <?php else: ?>
              <a href="../html/login.php" class="solicitar-btn">Entrar para Solicitar</a>
            <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <script>mostrarModal('Solicitação enviada com sucesso!');</script>
  <?php elseif (isset($errorMsg)): ?>
    <script>mostrarModal('<?= addslashes($errorMsg) ?>');</script>
  <?php endif; ?>

        </div>
    </div>

      <div class="banners">
        <img src="<?= htmlspecialchars($prof['banner1']) ?>" alt="Banner 1" class="banner-principal">
        <div class="mini-banners">
          <img src="<?= htmlspecialchars($prof['banner2']) ?>" alt="Banner 2" >
          <img src="<?= htmlspecialchars($prof['banner3']) ?>" alt="Banner 3" >
        </div>
      </div>
    </section>
  </main>
  <!-- login.js será carregado uma vez ao final -->
</body>
<script src="../js/login.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const url = new URL(window.location.href);
    const success = url.searchParams.get("success");
    const erro = url.searchParams.get("erro");

    if (success) {
        mostrarModal("Solicitação enviada com sucesso!");
        // Força exibição imediata da badge de chat no nav indicando novo chat disponível
        try {
          const badge = document.getElementById('global-chat-badge');
          if (badge) {
            // Notificação de solicitação: vermelho (não roxo)
            badge.style.display = 'inline-block';
            badge.classList.remove('new-chat');
            badge.style.background = '#dc2626';
            badge.style.animation = 'pulseBadge 1.3s ease-in-out infinite';
          }
          // Dispara evento customizado para outros scripts que queiram reagir
          document.dispatchEvent(new CustomEvent('chatPlaceholderCreated', { detail:{ source:'solicitacao', ts: Date.now() } }));
        } catch(e) { /* silencia */ }
        // Limpa o parâmetro success da URL para evitar reexibir modal ao voltar/atualizar
        try {
          const cleanUrl = window.location.pathname + '?id_prestadora=<?= (int)$id_prestadora ?>';
          window.history.replaceState({}, document.title, cleanUrl);
        } catch(e) { /* ignore */ }
    }

    if (erro) {
        mostrarModal(erro);
    }
});
</script>
<script>
// Intercepta envio para AJAX e evita reload/redirect
(function(){
  const form = document.getElementById('solicitacao-form');
  if(!form || form.__boundAjax) return; form.__boundAjax = true;
  form.addEventListener('submit', async function(ev){
    // Se for prestadora, deixa lógica padrão do botão bloquear
    if (form.querySelector('button[onclick]')) return;
    ev.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if(btn){ btn.disabled = true; btn.textContent = 'Enviando...'; }
    try {
      const fd = new FormData(form);
      // garante que o backend entre no bloco POST mesmo via fetch
      fd.append('solicitar','1');
      // marca como chamada AJAX para o backend responder JSON
      fd.append('ajax','1');
      const r = await fetch(window.location.href, { method:'POST', body: fd, credentials:'same-origin' });
      let data=null; try{ data = await r.json(); }catch{}
      if(data && data.ok){
        mostrarModal('Solicitação enviada com sucesso!');
        // Badge vermelha imediata
        const badge = document.getElementById('global-chat-badge');
        if(badge){ badge.style.display='inline-block'; badge.classList.remove('new-chat'); badge.style.background='#dc2626'; }
        // Limpa marca visto para forçar futura notificação se vier mensagem
        localStorage.removeItem('chatLastSeenMaxId');
      } else {
        mostrarModal(data?.erro || 'Falha ao solicitar.');
      }
    } catch(e){ mostrarModal('Erro de rede ao solicitar.'); }
    finally { if(btn){ btn.disabled=false; btn.textContent='Solicitar Serviço'; } }
  });
})();
</script>
<script src="..\js\cookies.js"></script>
<!-- Header nav behavior comes from header_nav.php; no extra fallbacks needed. -->
</html>
