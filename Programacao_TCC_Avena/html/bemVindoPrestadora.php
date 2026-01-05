<?php
session_start();
include("../php/conexao.php");
if (!isset($conexao)) { die("Erro: conexão com o banco não encontrada. Verifique ../php/conexao.php"); }
//print_r($_SESSION);
// Definições seguras iniciais
$nome = 'Usuário';
$img  = '../img/SemFoto.jpg';

// Detecta tipo de sessão (novo padrão: $_SESSION['prestadora']['id_usuario'] ou $_SESSION['cliente']['id_usuario'])
$id = null; $tabela = null;
if (!empty($_SESSION['prestadora']['id_usuario'])) { $id = (int)$_SESSION['prestadora']['id_usuario']; $tabela = 'prestadora'; }
elseif (!empty($_SESSION['cliente']['id_usuario'])) { $id = (int)$_SESSION['cliente']['id_usuario']; $tabela = 'cliente'; }

if ($id && $tabela) {
  // Verifica se coluna imgperfil existe
  $hasImg = false;
  $colRes = $conexao->query("SHOW COLUMNS FROM $tabela LIKE 'imgperfil'");
  if ($colRes && $colRes->num_rows > 0) { $hasImg = true; }
  $sql = $hasImg ? "SELECT nome, imgperfil FROM $tabela WHERE id_usuario = ? LIMIT 1" : "SELECT nome FROM $tabela WHERE id_usuario = ? LIMIT 1";
  $stmt = $conexao->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (!empty($row['nome'])) { $nome = $row['nome']; }
        if ($hasImg && !empty($row['imgperfil'])) { $img = $row['imgperfil']; }
      }
    }
    $stmt->close();
  }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel da Prestadora - Avena</title>
  <link rel="stylesheet" href="../css/bemVindoPrestadora.css">
  <link rel="stylesheet" href="../public/chat-badge.css">
</head>

<body>
<!-- ===============================
       Menu Lateral
       =============================== -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php">Quem somos</a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href=".\Pagina_Inicial.html"><span class="Home">Home</span></a></li>
    </ul>
  </nav>

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

  <header>
    <nav>
      <div class="logo">
        <a href="Pagina_Inicial.html">
          <img src="../img/logoAvena.png" alt="Logo Avena">
        </a>
      </div>

      <div class="perfil-area">
        <span class="nome"><?php echo htmlspecialchars($nome); ?></span>
        <img src="<?php echo htmlspecialchars($img); ?>" alt="Foto de perfil" class="perfil-foto">
        
      </div>
    </nav>
  </header>

  <main class="conteudo">
    <h2>Bem vinda de volta, <?php echo htmlspecialchars($nome); ?>!</h2>
    <p>Gerencie seus serviços e encontre novas oportunidades hoje.</p>

    <div class="botoes">
      <a href="..\html\EdicaoPerfilGeral.php" class="btn editar-perfil">⚙️ Editar Perfil</a>
      <a href="..\html\EditarServico.php" class="btn editar-servicos">🖋️ Editar Serviços</a>
      <a href="chat.php" class="btn mensagens" id="bv-prestadora-mensagens">💬 Mensagens<span class="badge-dot" id="bv-prestadora-chat-badge" style="display:none; width:12px; height:12px; background:#dc2626; border-radius:50%; margin-left:6px;"></span></a>
      <a href="avaliarLista.php" class="btn avaliacoes">⭐ Avaliações</a>
      <a href="..\html\cursos.php" class="btn cursos">🎓 Cursos</a>
      <a href="agendaPrestadora.php" class="btn agenda">📅 Minha Agenda</a>
    </div>
  </main>

  
</body>

  <script src="../js/login.js"></script>
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

    const badgeMainP = document.getElementById('bv-prestadora-chat-badge');

    let prevVisibleAnyP = false; let prevPulseAnyP = false;
    let prevStatsP = { unread:0, maxId:0, newChats:0, initialized:false };
    async function pollChatsPrest(){
      try {
        const r = await fetch('../php/getChatList.php', { credentials:'same-origin', cache:'no-store' });
        if(!r.ok) return; const data = await r.json(); if(!data.ok) return;
        const chats = Array.isArray(data.chats) ? data.chats : [];
        const anyNew = chats.some(c=>c.newChat);
        const anyUnread = chats.some(c=>c.unread>0);
        const show = anyNew || anyUnread;
        const maxId = chats.reduce((m,c)=>Math.max(m,c.lastMessageId||0),0);
        window.__BV_P_LAST_MAX_ID = maxId;
        const lastSeen = parseInt(localStorage.getItem('chatLastSeenMaxId')||'0',10);
        const shouldShow = (anyNew || maxId > lastSeen || (anyUnread && maxId > lastSeen));
        if(badgeMainP){
          if(shouldShow){
            badgeMainP.style.display='inline-block';
            if(anyNew){ badgeMainP.classList.add('new-chat'); } else { badgeMainP.classList.remove('new-chat'); }
          } else {
            badgeMainP.style.display='none'; badgeMainP.classList.remove('new-chat');
          }
        }
        // Estatísticas para evento novo (som)
        const totalUnread = chats.reduce((s,c)=>s + (c.unread||0), 0);
        const maxMsgId = chats.reduce((m,c)=> Math.max(m, c.lastMessageId||0), 0);
        const newChatsCount = chats.reduce((s,c)=> s + (c.newChat?1:0), 0);
        if(prevStatsP.initialized){
          if(totalUnread > prevStatsP.unread || maxMsgId > prevStatsP.maxId || newChatsCount > prevStatsP.newChats){
            window.__SND__?.playNew();
          }
        }
        prevStatsP = { unread: totalUnread, maxId: maxMsgId, newChats: newChatsCount, initialized: true };
        prevVisibleAnyP = show; prevPulseAnyP = anyNew;
      } catch(e){ }
    }
    pollChatsPrest();
    setInterval(pollChatsPrest, 1500);
  </script>
  <script>
    // Marca visto e oculta badge ao clicar no botão Mensagens (prestadora)
    (function(){
      const btn = document.getElementById('bv-prestadora-mensagens');
      const badge = document.getElementById('bv-prestadora-chat-badge');
      if(btn && !btn.__clearBound){
        btn.__clearBound = true;
        btn.addEventListener('click', function(){
          try {
            const maxId = window.__BV_P_LAST_MAX_ID || 0;
            localStorage.setItem('chatLastSeenMaxId', String(maxId));
            if(badge){ badge.style.display='none'; }
          } catch(e){}
        });
      }
    })();
  </script>
  <script src="..\js\cookies.js"></script>
</html>
