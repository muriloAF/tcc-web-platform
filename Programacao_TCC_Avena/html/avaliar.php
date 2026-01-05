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
  echo '<script> window.location.href = "..\\html\\login.php"</script>';
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
// Exige login, mas aceita novo/antigo formato de sessão; prioriza $_SESSION['tipo']
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

// fallback: inferir por chaves existentes
if ($session_user_id === null) {
  if (!empty($_SESSION['cliente']['id_usuario'])) { $session_user_id = (int)$_SESSION['cliente']['id_usuario']; $session_tipo = 'cliente'; }
  elseif (!empty($_SESSION['prestadora']['id_usuario'])) { $session_user_id = (int)$_SESSION['prestadora']['id_usuario']; $session_tipo = 'profissional'; }
  elseif (!empty($_SESSION['id_usuario'])) { $session_user_id = (int)$_SESSION['id_usuario']; $session_tipo = $_SESSION['tipo'] ?? null; }
}

if (!$session_user_id) { echo '<script> window.location.href = "..\\html\\login.php"</script>'; exit; }

$avaliador_id = $session_user_id;
$avaliador_tipo = $session_tipo; // cliente ou profissional
if ($avaliador_tipo == 'profissional') {
  $avaliador_tipo = 'prestadora';
}
    

$avaliado_id = $_GET["id"] ?? null;
if (!$avaliado_id) {
    echo "Erro: ID não informado";
    exit;
}


// DEFINIR TIPO DO AVALIADO
if ($avaliador_tipo == "cliente") {
    // cliente avalia prestadora
    $avaliado_tipo = "prestadora";
    $sql = "SELECT nome FROM prestadora WHERE id_usuario = $avaliado_id";
    $sqlUser = "SELECT nome, imgperfil FROM cliente WHERE id_usuario = $avaliador_id";
} else {
    // prestadora avalia cliente
    $avaliado_tipo = "cliente";
    $sql = "SELECT nome FROM cliente WHERE id_usuario = $avaliado_id";
    $sqlUser = "SELECT nome, imgperfil FROM prestadora WHERE id_usuario = $avaliador_id";
}

$query = mysqli_query($conexao, $sql);
$avaliado = mysqli_fetch_assoc($query);

$resultUser = mysqli_query($conexao, $sqlUser);
$info = mysqli_fetch_assoc($resultUser);

// Garantir valores padrão para evitar acessar offsets em null
if (!is_array($avaliado)) {
  $avaliado = ['nome' => 'Usuário'];
} else {
  $avaliado['nome'] = $avaliado['nome'] ?? 'Usuário';
}

if (!is_array($info)) {
  $info = ['nome' => 'Usuário', 'imgperfil' => '../img/SemFoto.jpg'];
} else {
  $info['nome'] = $info['nome'] ?? 'Usuário';
  $info['imgperfil'] = !empty($info['imgperfil']) ? $info['imgperfil'] : '../img/SemFoto.jpg';
}

// Normalizar barras do caminho da imagem
$info['imgperfil'] = str_replace('\\', '/', $info['imgperfil']);


// SALVAR AVALIAÇÃO

// Função para buscar uma solicitação elegível para avaliação
function buscarSolicitacaoElegivel($conexao, $avaliador_id, $avaliador_tipo, $avaliado_id, $avaliado_tipo) {
  // cliente avalia prestadora
  if ($avaliador_tipo == 'cliente') {
    $sql = "SELECT s.id FROM solicitacoes s
        LEFT JOIN avaliacoes a ON a.avaliador_id = s.id_contratante AND a.avaliado_id = s.id_prestadora AND a.avaliador_tipo = 'cliente' AND a.avaliado_tipo = 'prestadora'
        WHERE s.id_contratante = ? AND s.id_prestadora = ?
          AND s.status IN ('aceito','concluido')
          AND a.id IS NULL
        ORDER BY s.data_solicitacao ASC LIMIT 1";
  } else {
    // prestadora avalia cliente
    $sql = "SELECT s.id FROM solicitacoes s
        LEFT JOIN avaliacoes a ON a.avaliador_id = s.id_prestadora AND a.avaliado_id = s.id_contratante AND a.avaliador_tipo = 'prestadora' AND a.avaliado_tipo = 'cliente'
        WHERE s.id_prestadora = ? AND s.id_contratante = ?
          AND s.status IN ('aceito','concluido')
          AND a.id IS NULL
        ORDER BY s.data_solicitacao ASC LIMIT 1";
  }
  $stmt = $conexao->prepare($sql);
  if ($avaliador_tipo == 'cliente') {
    $stmt->bind_param('ii', $avaliador_id, $avaliado_id);
  } else {
    $stmt->bind_param('ii', $avaliador_id, $avaliado_id);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ? $row['id'] : null;
}

$solicitacaoElegivelId = buscarSolicitacaoElegivel($conexao, $avaliador_id, $avaliador_tipo, $avaliado_id, $avaliado_tipo);

// SALVAR AVALIAÇÃO
if (isset($_POST['submit'])) {
  if (!$solicitacaoElegivelId) {
    echo '<div style="color:red; text-align:center; margin-top:20px;">Você só pode avaliar após solicitar e concluir um serviço com este usuário, e apenas uma vez por solicitação.</div>';
  } else {
    $avaliado_id = mysqli_real_escape_string($conexao, $_POST["avaliado_id"]);
    $nota        = mysqli_real_escape_string($conexao, $_POST["nota"]);
    $comentario  = mysqli_real_escape_string($conexao, $_POST["comentario"]);

    // Insere avaliação
    $sqlInsert = "
      INSERT INTO avaliacoes 
      (avaliador_id, avaliador_tipo, avaliado_id, avaliado_tipo, nota, comentario)
      VALUES 
      ('$avaliador_id', '$avaliador_tipo', '$avaliado_id', '$avaliado_tipo', '$nota', '$comentario')
    ";
    // print_r($sqlInsert);
    echo resultado($conexao, $sqlInsert);
    // Opcional: poderia marcar a solicitação como avaliada se quiser rastrear (requer campo novo)
  }
}

//print_r($_SESSION);
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel - Avena</title>

 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

 
  

  <link rel="stylesheet" href="../css/avaliar.css"> 
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
        <span class="nome"><?php echo $info['nome'] ?></span>

       
        <img src="<?php  echo $info['imgperfil']?>" alt="Foto de perfil" class="perfil-foto">

       
        
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
<a href= "..\html\avaliarLista.php">
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



  <main class="container">



   
   <h2>Avaliando: <?= htmlspecialchars($avaliado["nome"]) ?></h2>


<?php if ($solicitacaoElegivelId): ?>
<form action="avaliar.php?id=<?= $avaliado_id ?>" method="POST">
  <input type="hidden" name="avaliado_id" value="<?= $avaliado_id ?>">
  <div class="stars" required>
    <i class="star" data-value="1" onclick="estrelaUm()" id="1" required>★</i>
    <i class="star" data-value="2" onclick="estrelaDois()" id="2" required>★</i>
    <i class="star" data-value="3"  onclick="estrelaTres()" id="3" required>★</i>
    <i class="star" data-value="4"  onclick="estrelaQuatro()" id="4" required>★</i>
    <i class="star" data-value="5"  onclick="estrelaCinco()" id="5" required>★</i>
  </div>
  <input type="hidden" id="nota" name="nota">
  <textarea name="comentario" placeholder="Escreva um comentário..." required></textarea>
  <button type="submit" class="btn-enviar" name="submit">Enviar Avaliação</button>
</form>
<?php else: ?>
  <div style="color:red; text-align:center; margin-top:20px;">Você só pode avaliar após solicitar e concluir um serviço com este usuário, e apenas uma vez por solicitação.</div>
<?php endif; ?>

  </main>

 

</body>
   <script src="../js/login"></script> 
  <script src="..\js\cookies.js"></script>
  <script>
const stars = document.querySelectorAll(".star");
const nota = document.querySelector("#nota");

stars.forEach((star) => {
    star.addEventListener("click", () => {
        let value = star.dataset.value;
        nota.value = value;

        stars.forEach(s => s.classList.remove("selected"));
        for (let i = 0; i < value; i++) stars[i].classList.add("selected");
    });
});


let rating = 0;

stars.forEach(star => {
    star.addEventListener('click', function() {

        const value = parseInt(this.dataset.value);

        // Se clicar na mesma estrela -> limpa tudo
        if (value === rating) {
            rating = 0;
        } else {
            rating = value;
        }

        // Atualiza visual
        stars.forEach(s => {
            if (parseInt(s.dataset.value) <= rating) {
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });
    });
});
</script>
</html>

<?php
function resultado($conexao, $sqlInsert){
if (mysqli_query($conexao, $sqlInsert)) {
        header("Location: avaliarLista.php?ok=1");
    } else {
        echo "Erro ao salvar avaliação: " . mysqli_error($conexao);
    }
}
?>