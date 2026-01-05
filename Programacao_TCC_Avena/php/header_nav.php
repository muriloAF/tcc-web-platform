<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/conexao.php');

function obterPerfilHeader($conexao) {
  $perfil = ['nome'=>null,'imgperfil'=>null];
  $id = null; $tabela = null;
  // Prioriza tipo explícito na sessão (cliente/profissional)
  $tipoSess = $_SESSION['tipo'] ?? null;
  if ($tipoSess === 'cliente') {
    if (!empty($_SESSION['cliente']['id_usuario'])) { $id = (int)$_SESSION['cliente']['id_usuario']; }
    elseif (!empty($_SESSION['id_usuario'])) { $id = (int)$_SESSION['id_usuario']; }
    $tabela = 'cliente';
  } elseif ($tipoSess === 'profissional') {
    if (!empty($_SESSION['prestadora']['id_usuario'])) { $id = (int)$_SESSION['prestadora']['id_usuario']; }
    elseif (!empty($_SESSION['id_usuario'])) { $id = (int)$_SESSION['id_usuario']; }
    $tabela = 'prestadora';
  } else {
    // sem tipo explícito: tenta detectar pelo formato aninhado (novo) ou flat (antigo)
    if (!empty($_SESSION['cliente']['id_usuario'])) { $id = (int)$_SESSION['cliente']['id_usuario']; $tabela = 'cliente'; }
    elseif (!empty($_SESSION['prestadora']['id_usuario'])) { $id = (int)$_SESSION['prestadora']['id_usuario']; $tabela = 'prestadora'; }
    elseif (!empty($_SESSION['id_usuario'])) {
      $id = (int)$_SESSION['id_usuario'];
      // tenta inferir pelo campo tipo se existir
      $t = $_SESSION['tipo'] ?? null;
      $tabela = ($t === 'profissional') ? 'prestadora' : 'cliente';
    }
  }
  if (!$tabela || !$id) { return $perfil; }
  $hasImg = false;
  $colRes = $conexao->query("SHOW COLUMNS FROM $tabela LIKE 'imgperfil'");
  if ($colRes && $colRes->num_rows) { $hasImg = true; }
  $select = $hasImg ? "SELECT nome, imgperfil FROM $tabela WHERE id_usuario = ? LIMIT 1" : "SELECT nome FROM $tabela WHERE id_usuario = ? LIMIT 1";
  $stmt = $conexao->prepare($select);
  if ($stmt) {
    $stmt->bind_param('i',$id);
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
$perfilHeader = obterPerfilHeader($conexao);
// Permite injetar barra de busca dentro do header se a página definir $renderSearchBar=true
if(!isset($renderSearchBar)) { $renderSearchBar = false; }
?>
<header class="global-header">
  <link rel="stylesheet" href="../public/chat-badge.css" />
  <div class="logo">
    <a href="../html/Pagina_Inicial.html"><img src="../img/logoAvena.png" alt="Logo Avena"></a>
  </div>
  <?php if($renderSearchBar){ ?>
    <div class="search-bar-inline">
      <input type="text" placeholder="Manicure" id="search_servico" name="search_servico" value="<?= isset($_GET['search_servico']) ? htmlspecialchars($_GET['search_servico']) : '' ?>">
      <span class="divider-inline">/</span>
      <input type="text" placeholder="Cidade ou Estado" id="search_localizacao" name="search_localizacao" value="<?= isset($_GET['search_localizacao']) ? htmlspecialchars($_GET['search_localizacao']) : '' ?>">
      <button type="button" class="pesquisa-btn-inline" id="pesquisa-btn-inline" aria-label="Buscar">🔍</button>
    </div>
  <?php } ?>
  <div class="menu-cluster">
    <?php if(!empty($perfilHeader['nome'])) { ?>
          <div class="perfil-area">
        <span class="nome"><?= htmlspecialchars($perfilHeader['nome']) ?></span>
        <?php $srcImg = (!empty($perfilHeader['imgperfil'])) ? $perfilHeader['imgperfil'] : '../img/SemFoto.jpg'; ?>
        <a href="../html/EdicaoPerfilGeral.php" class="perfil-link" title="Editar Perfil">
          <img src="<?= htmlspecialchars($srcImg) ?>" alt="Perfil" class="perfil-foto" style="cursor:pointer;">
        </a>
      </div>
    <?php } else { ?>
      <a href="../html/login.php" class="btn-entrar" id="btn-entrar">ENTRAR</a>
    <?php } ?>
    <?php
      // Renderiza botão de notificações dentro do header quando solicitado
      if (!isset($renderNotifications)) { $renderNotifications = false; }
      if ($renderNotifications) {
        $uid = null; $notif = []; $notif_nao_lidas = 0;
        if (!empty($_SESSION['cliente']['id_usuario'])) { $uid = (int)$_SESSION['cliente']['id_usuario']; }
        elseif (!empty($_SESSION['prestadora']['id_usuario'])) { $uid = (int)$_SESSION['prestadora']['id_usuario']; }
        if ($uid && isset($conexao) && $conexao instanceof mysqli) {
          $stmt = $conexao->prepare("SELECT id, mensagem, visualizado, data FROM notificacoes WHERE id_usuario = ? ORDER BY id DESC");
          if ($stmt) { $stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result(); while($row=$r->fetch_assoc()){ $notif[]=$row; if((int)$row['visualizado']===0) $notif_nao_lidas++; } $stmt->close(); }
        }
    ?>
      <div class="notificacoes-container" style="position:relative; margin-right:8px;">
        <button id="btn-notificacoes-global" class="notif-btn" title="Notificações">🔔
          <?php if ($notif_nao_lidas > 0): ?>
            <span class="notif-count"><?= $notif_nao_lidas ?></span>
          <?php endif; ?>
        </button>
        <div id="notif-dropdown-global" class="notif-dropdown hidden" style="position:absolute; right:0; margin-top:6px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.1); min-width:280px; max-width:360px; max-height:300px; overflow:auto;">
          <?php
            $naoLidas = array_filter($notif, function($n){ return !$n['visualizado']; });
            if (count($naoLidas) === 0): ?>
            <p class="vazio" style="padding:12px 14px; color:#64748b;">Não há notificações não lidas.</p>
          <?php else:
            foreach ($naoLidas as $n): ?>
            <div class="notif-item nao-lida" style="padding:10px 12px; border-bottom:1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <p style="margin:0 0 4px; font-size:14px; color:#111827;\"><?= htmlspecialchars($n['mensagem']) ?></p>
                <small style="color:#6b7280; font-size:12px;\"><?= htmlspecialchars($n['data']) ?></small>
              </div>
              <form method="post" style="margin:0;">
                <input type="hidden" name="marcar_lida" value="<?= (int)$n['id'] ?>">
                <button type="submit" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:13px;">OK</button>
              </form>
            </div>
          <?php endforeach; endif; ?>
        <?php
        // Marcar notificação como lida ao clicar em OK
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_lida'])) {
          $idNotif = (int)$_POST['marcar_lida'];
          if ($idNotif > 0 && isset($conexao) && $conexao instanceof mysqli) {
            $uid = null;
            if (!empty($_SESSION['cliente']['id_usuario'])) { $uid = (int)$_SESSION['cliente']['id_usuario']; }
            elseif (!empty($_SESSION['prestadora']['id_usuario'])) { $uid = (int)$_SESSION['prestadora']['id_usuario']; }
            if ($uid) {
              $stmt = $conexao->prepare("UPDATE notificacoes SET visualizado = 1 WHERE id = ? AND id_usuario = ?");
              $stmt->bind_param('ii', $idNotif, $uid);
              $stmt->execute();
              $stmt->close();
              // Redireciona para evitar reenvio do formulário
              header('Location: ' . $_SERVER['REQUEST_URI']);
              exit;
            }
          }
        }
        ?>
        </div>
      </div>
    <?php } ?>
    <button class="menu-icon" id="menu-btn">&#9776;</button>
  </div>
</header>
<nav id="menu" class="hidden">
  <ul>
    <li><a href="../html/quemSomos.php">Quem somos</a></li>
    <?php
      // Link dinâmico para página de bem-vindo
      $bemVindoHref = null;
      if (!empty($_SESSION['cliente']['id_usuario']) || ($_SESSION['tipo'] ?? null) === 'cliente') {
        $bemVindoHref = '../html/bemVindoCliente.php';
      } elseif (!empty($_SESSION['prestadora']['id_usuario']) || ($_SESSION['tipo'] ?? null) === 'profissional') {
        $bemVindoHref = '../html/bemVindoPrestadora.php';
      }
    ?>
    <?php if ($bemVindoHref): ?>
      <li><a href="<?= $bemVindoHref ?>">Principal</a></li>
    <?php else: ?>
      <li><a href="../html/cadastro.php">Cadastrar-se</a></li>
    <?php endif; ?>
    <hr>
    <li><a href="../html/sejaParceiro.php">Seja um Parceiro</a></li>
    <li><a href="../html/Pagina_Inicial.html"><span class="Home">Home</span></a></li>
  </ul>
</nav>
<script>
(function(){
  const src = '../sounds/NovaMensagem.wav';
  const a = document.createElement('audio');
  a.src = src; a.preload = 'auto'; a.style.display='none';
  document.body.appendChild(a);
  const playFx = ()=>{
    try { a.currentTime = 0; a.play(); } catch(e) {}
  };
  // Não sobrescreve objeto existente; apenas garante playNew disponível.
    (function(){
      function bindMenu(){
        var btn = document.getElementById('menu-btn');
        var menu = document.getElementById('menu');
        if(!btn || !menu) return;
        if(btn.__bound) return; btn.__bound = true;
        btn.setAttribute('aria-controls','menu');
        btn.setAttribute('aria-expanded','false');
        btn.addEventListener('click', function(ev){
          ev.preventDefault(); ev.stopPropagation();
          if(menu.classList.contains('show')){
            menu.classList.remove('show');
            menu.classList.add('hidden');
            btn.setAttribute('aria-expanded','false');
          } else {
            menu.classList.remove('hidden');
            menu.classList.add('show');
            btn.setAttribute('aria-expanded','true');
          }
        });
        // Click outside closes menu
        document.addEventListener('click', function(e){
          if(!e.target.closest('#menu') && !e.target.closest('#menu-btn')){
            if(menu.classList.contains('show')){
              menu.classList.remove('show');
              menu.classList.add('hidden');
              btn.setAttribute('aria-expanded','false');
            }
          }
        });
      }
      if(document.readyState !== 'loading') bindMenu(); else document.addEventListener('DOMContentLoaded', bindMenu);
    })();
  if (!window.__SND__) window.__SND__ = {};
  window.__SND__.playNew = window.__SND__.playNew || playFx;
})();

// Bind menu button to toggle nav visibility
(function(){
  function bindMenu(){
    var btn = document.getElementById('menu-btn');
    var menu = document.getElementById('menu');
    if(!btn || !menu) return;
    if(btn.__bound) return; btn.__bound = true;
    btn.setAttribute('aria-controls','menu');
    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click', function(ev){
      ev.preventDefault(); ev.stopPropagation();
      if(menu.classList.contains('show')){
        menu.classList.remove('show');
        menu.classList.add('hidden');
        btn.setAttribute('aria-expanded','false');
      } else {
        menu.classList.remove('hidden');
        menu.classList.add('show');
        btn.setAttribute('aria-expanded','true');
      }
    });
    // Click outside closes menu
    document.addEventListener('click', function(e){
      if(!e.target.closest('#menu') && !e.target.closest('#menu-btn')){
        if(menu.classList.contains('show')){
          menu.classList.remove('show');
          menu.classList.add('hidden');
          btn.setAttribute('aria-expanded','false');
        }
      }
    });
  }
  if(document.readyState !== 'loading') bindMenu(); else document.addEventListener('DOMContentLoaded', bindMenu);
  // Toggle notificações
  function bindNotif(){
    var btn = document.getElementById('btn-notificacoes-global');
    var dd  = document.getElementById('notif-dropdown-global');
    if(!btn || !dd || btn.__bound) return;
    btn.__bound = true;
    btn.addEventListener('click', function(){
      dd.classList.toggle('hidden');
      // Marcar todas notificações como lidas ao abrir o dropdown
      if (!dd.classList.contains('hidden')) {
        fetch('../php/marcar_notificacoes.php', { method: 'POST', credentials: 'same-origin' })
          .then(function(){
            // Opcional: atualizar badge/contagem sem reload
            var notifCount = document.querySelector('.notif-count');
            if (notifCount) notifCount.style.display = 'none';
            // Esconde itens não lidos
            var naoLidas = dd.querySelectorAll('.notif-item.nao-lida');
            naoLidas.forEach(function(el){ el.classList.remove('nao-lida'); });
            // Se não houver mais, mostra mensagem de vazio
            if (!dd.querySelector('.notif-item.nao-lida')) {
              var vazio = dd.querySelector('.vazio');
              if (!vazio) {
                var p = document.createElement('p');
                p.className = 'vazio';
                p.style.padding = '12px 14px';
                p.style.color = '#64748b';
                p.textContent = 'Não há notificações não lidas.';
                dd.appendChild(p);
              }
            }
          });
      }
    });
    document.addEventListener('click', function(ev){ if(!ev.target.closest('.notificacoes-container')){ dd.classList.add('hidden'); } });
  }
  if(document.readyState !== 'loading') bindNotif(); else document.addEventListener('DOMContentLoaded', bindNotif);
})();
// Optional: observe global chat badge for sound (if present)
(function(){
  let prevVisible = false;
  let prevPulse = false;
  const badge = document.getElementById('global-chat-badge');
  if(!badge) return;
  const observer = new MutationObserver(()=>{
    const visible = badge.style.display !== 'none';
    const pulsing = badge.classList.contains('new-chat');
    if ((visible && !prevVisible) || (visible && pulsing && !prevPulse)) {
      if (!localStorage.getItem('chatNotifSeen')) {
        if (!(Date.now() < (window.__SUPPRESS_SOUND_TS||0))) window.__SND__?.playNew();
      }
    }
    prevVisible = visible; prevPulse = pulsing;
  });
  observer.observe(badge, { attributes:true, attributeFilter:['style','class'] });
})();
</script>
