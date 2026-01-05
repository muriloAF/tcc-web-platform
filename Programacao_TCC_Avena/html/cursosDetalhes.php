<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once(__DIR__ . '/../php/conexao.php');
mysqli_set_charset($conexao, "utf8mb4");

// Normaliza nome e imagem de perfil para evitar avisos quando não definidos
$nome = 'Visitante';
$img = '../img/SemFoto.jpg';
// Preferência: valores armazenados na sessão (suporta formatos antigos e aninhados)
if (!empty($_SESSION['prestadora']['nome'])) {
  $nome = htmlspecialchars($_SESSION['prestadora']['nome']);
} elseif (!empty($_SESSION['nome'])) {
  $nome = htmlspecialchars($_SESSION['nome']);
}
if (!empty($_SESSION['prestadora']['imgperfil'])) {
  $img = $_SESSION['prestadora']['imgperfil'];
} elseif (!empty($_SESSION['imgperfil'])) {
  $img = $_SESSION['imgperfil'];
}

if((!isset($_SESSION['email']) == true) || (!isset($_SESSION['senha']) == true || $_SESSION['tipo'] == 'cliente')){
        unset($_SESSION['email']);
        unset($_SESSION['senha']);
        header('Location: ..\html\login.php');
        }else{
            $logado = $_SESSION['email'];
        }

 //Verifica se foi passado um ID na URL
if (!isset($_GET['id_curso'])) {
    header("Location: cursos.php");
    exit;
}

$id_curso = intval($_GET['id_curso']);
$sqlcurso = "SELECT * FROM curso WHERE id_curso = $id_curso";
$resultadocurso = mysqli_query($conexao, $sqlcurso);

$prof = mysqli_fetch_assoc($resultadocurso);
$logado = isset($_SESSION['email']);


// Normaliza possível id do usuário (suporta sessão plana ou aninhada)
$id_usuario = $_SESSION['id_usuario'] ?? $_SESSION['prestadora']['id_usuario'] ?? $_SESSION['prestadora']['id_prestadora'] ?? null;
if (!empty($id_usuario)) {
  $id_usuario = (int) $id_usuario;

  // Buscar nome e imagem numa única query para economizar roundtrips
  $stmt = $conexao->prepare("SELECT nome, COALESCE(imgperfil, ?) AS imgperfil FROM prestadora WHERE id_usuario = ? LIMIT 1");
  if ($stmt) {
    $defaultImg = '../img/SemFoto.jpg';
    $stmt->bind_param('si', $defaultImg, $id_usuario);
    if ($stmt->execute()) {
      $resultado = $stmt->get_result();
      if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        if (!empty($row['nome'])) {
          $nome = htmlspecialchars($row['nome']);
        }
        if (!empty($row['imgperfil'])) {
          $img = $row['imgperfil'];
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($prof['Nome']) ?> - Avena</title>
    <link rel="stylesheet" href="../css/cursosDetalhes.css">
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
        <span class="nome"><?php echo $nome ?></span>
        <img src="<?php echo $img?>" alt="Foto de perfil" class="perfil-foto">
        <button class="menu-icon" id="menu-btn">&#9776;</button>
      </div>
    </nav>
    </header>




    <main class="container">
        <h2><?= htmlspecialchars($prof['Nome']) ?></h2>

        <section class="conteudo">
            <div class="caixa">
                <h3>Descrição Geral</h3>
                <p><?= nl2br(htmlspecialchars($prof['DescricaoGeral'])) ?></p>
            </div>

            <div class="caixa">
                <h3>Você vai Aprender</h3>
                <ul>
                    <?php
                        // Exemplo: você salva os tópicos separados por ponto e vírgula no banco
                        $itens = explode(';', $prof['Aprender']);
                        foreach ($itens as $item) {
                            echo "<li>" . htmlspecialchars(trim($item)) . "</li>";
                        }
                    ?>
                </ul>
            </div>
        </section>

        <div class="info">
            <p><strong>Carga Horária:</strong> <?= htmlspecialchars($prof['TempoTotal']) ?> horas</p>
            <p><strong>Nível:</strong> <?= htmlspecialchars($prof['Nivel']) ?></p>
        </div>

        <div class="acoes">
            <a href="<?= htmlspecialchars($prof['video'])?>" target="_blank"><button class="btn">Iniciar Curso</button><a>
        </div>
    </main>
</body>
<script src="../js/login.js"></script>
  <script src="..\js\cookies.js"></script>
</html>