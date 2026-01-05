<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('../php/conexao.php');
//print_r($_SESSION);
// Definições padrão para evitar undefined variable warnings
$nome = 'Usuário';
$img = '../img/SemFoto.jpg';


if (!isset($conexao)) {
  die("Erro: conexão com o banco não encontrada. Verifique ../php/conexao.php");
}

// Normaliza sessão: suporta formato antigo (flat) e novo (aninhado em 'cliente' ou 'prestadora')
$id_usuario = null;
$tipoSess = null; // 'cliente' ou 'profissional'

if (!empty($_SESSION['cliente']['id_usuario'])) {
  $id_usuario = (int)$_SESSION['cliente']['id_usuario'];
  $tipoSess = 'cliente';
} elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
  $id_usuario = (int)$_SESSION['prestadora']['id_usuario'];
  // se a sessão flat diz 'cliente' preferimos cliente, caso contrário assume profissional
  $tipoSess = ($_SESSION['tipo'] ?? 'profissional');
} elseif (!empty($_SESSION['id_usuario'])) {
  $id_usuario = (int)$_SESSION['id_usuario'];
  $tipoSess = $_SESSION['tipo'] ?? null;
}

// Fallbacks para nome direto na sessão (quando disponível)
if (!empty($_SESSION['nome'])) {
  $nome = $_SESSION['nome'];
} elseif (!empty($_SESSION['cliente']['nome'])) {
  $nome = $_SESSION['cliente']['nome'];
} elseif (!empty($_SESSION['prestadora']['nome'])) {
  $nome = $_SESSION['prestadora']['nome'];
}

// Se tivermos id + tipo, busca no banco o nome e a imagem (prioriza banco para garantir atualidade)
if ($id_usuario && $tipoSess === 'cliente') {
  $stmt = $conexao->prepare('SELECT nome, imgperfil FROM cliente WHERE id_usuario = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $id_usuario);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        if (!empty($row['nome'])) $nome = $row['nome'];
        if (!empty($row['imgperfil'])) $img = $row['imgperfil'];
      }
    }
    $stmt->close();
  }
} elseif ($id_usuario && $tipoSess === 'profissional') {
  $stmt = $conexao->prepare('SELECT nome, imgperfil FROM prestadora WHERE id_usuario = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $id_usuario);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        if (!empty($row['nome'])) $nome = $row['nome'];
        if (!empty($row['imgperfil'])) $img = $row['imgperfil'];
      }
    }
    $stmt->close();
  }
}


// =============================================
// BUSCAR NOTIFICAÇÕES DO CLIENTE
// =============================================
$notificacoes = [];
$stmt = $conexao->prepare("
    SELECT id, mensagem, visualizado, data
    FROM notificacoes
    WHERE id_usuario = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notificacoes[] = $row;
}
$stmt->close();

// Contar notificações não lidas
$notif_nao_lidas = 0;
foreach ($notificacoes as $n) {
    if ($n['visualizado'] == 0) $notif_nao_lidas++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel - Avena</title>

 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

 
  <link rel="stylesheet" href="../css/Login.css">
  <link rel="stylesheet" href="../css/bemVindoCliente.css"> 
  <link rel="stylesheet" href="../css/header_nav.css">
  <link rel="stylesheet" href="../public/chat-badge.css">
</head>
<body class="fixed-header-page">

  <?php $renderNotifications = true; include_once(__DIR__ . '/../php/header_nav.php'); ?>

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

  


  




  <main class="conteudo">
    <div class="container">
      <h2>Bem-vindo de volta, <?php echo $nome; ?>!</h2>
      <p>Encontre prestadoras de serviços qualificadas para as suas necessidades.</p>

      <div class="botoes">
        <a href="busca.php" class="btn buscar">🔍 Buscar Serviços</a>
        <a href="agendaCliente.php" class="btn agenda">📅 Minha Agenda</a>
        <a href="chat.php" class="btn mensagens" id="bv-cliente-mensagens">💬 Mensagens<span class="badge-dot" id="bv-cliente-chat-badge" style="display:none; width:12px; height:12px; background:#dc2626; border-radius:50%; margin-left:6px;"></span></a>
        <a href="avaliarLista.php" class="btn avaliacoes">⭐ Minhas Avaliações</a>
      </div>
    </div>
  </main>

 
  <script>
    // Inicialização de áudio única
    (function(){
      if(!window.__SND__){
        const a=document.createElement('audio');
        a.src='../sounds/NovaMensagem.wav';
        a.preload='auto';
        a.style.display='none';
        document.body.appendChild(a);
        window.__SND__={playNew:()=>{try{a.currentTime=0;a.play();}catch(e){}}};
      }
    })();

    const badgeMain = document.getElementById('bv-cliente-chat-badge');

    let prevVisibleAny = false; let prevPulseAny = false;
    let prevStats = { unread:0, maxId:0, newChats:0, initialized:false };
    async function pollChats(){
      try {
        const r = await fetch('../php/getChatList.php', { credentials:'same-origin', cache:'no-store' });
        if(!r.ok) return; const data = await r.json(); if(!data.ok) return;
        const chats = Array.isArray(data.chats) ? data.chats : [];
        const anyNew = chats.some(c=>c.newChat);
        const anyUnread = chats.some(c=>c.unread>0);
        const show = anyNew || anyUnread;
        const maxId = chats.reduce((m,c)=>Math.max(m,c.lastMessageId||0),0);
        window.__BV_LAST_MAX_ID = maxId;
        const lastSeen = parseInt(localStorage.getItem('chatLastSeenMaxId')||'0',10);
        const shouldShow = (anyNew || maxId > lastSeen || (anyUnread && maxId > lastSeen));
        if(badgeMain){
          if(shouldShow){
            badgeMain.style.display='inline-block';
            if(anyNew){ badgeMain.classList.add('new-chat'); } else { badgeMain.classList.remove('new-chat'); }
          } else {
            badgeMain.style.display='none'; badgeMain.classList.remove('new-chat');
          }
        }
        // Estatísticas para detecção de evento novo (tocar som)
        const totalUnread = chats.reduce((s,c)=>s + (c.unread||0), 0);
        const maxMsgId = chats.reduce((m,c)=> Math.max(m, c.lastMessageId||0), 0);
        const newChatsCount = chats.reduce((s,c)=> s + (c.newChat?1:0), 0);
        if(prevStats.initialized){
          if(totalUnread > prevStats.unread || maxMsgId > prevStats.maxId || newChatsCount > prevStats.newChats){
            window.__SND__?.playNew();
          }
        }
        prevStats = { unread: totalUnread, maxId: maxMsgId, newChats: newChatsCount, initialized: true };
        prevVisibleAny = show; prevPulseAny = anyNew;
      } catch(e){ }
    }
    pollChats();
    setInterval(pollChats, 1500); // poll mais rápido
  </script>
  <script>
    // Marca visto e oculta badge ao clicar no botão Mensagens
    (function(){
      const btn = document.getElementById('bv-cliente-mensagens');
      const badge = document.getElementById('bv-cliente-chat-badge');
      if(btn && !btn.__clearBound){
        btn.__clearBound = true;
        btn.addEventListener('click', function(){
          try {
            const maxId = window.__BV_LAST_MAX_ID || 0;
            localStorage.setItem('chatLastSeenMaxId', String(maxId));
            if(badge){ badge.style.display='none'; }
          } catch(e){}
        });
      }
    })();
  </script>
  
</body>
  <script src="../js/login.js"></script> 
  
  <script src="..\js\cookies.js"></script>
</html>
