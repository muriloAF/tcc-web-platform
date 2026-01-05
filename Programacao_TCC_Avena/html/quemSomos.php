<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quem Somos - Avena</title>
  <link rel="stylesheet" href="../css/quemSomos.css">
</head>
<body>

  <!-- Banner de Cookies -->
  <div id="cookie-banner" class="cookie-banner">
    <div class="cookie-content">
      <h4>Privacidade e Cookies</h4>
      <p>
        A Singularity Solutions utiliza cookies para oferecer uma experiência mais personalizada,
        melhorar o desempenho da plataforma e garantir o funcionamento seguro dos serviços.
        Ao aceitar, você concorda com o uso de cookies conforme nossa
        <a href="../img/AVENA - Termos de Uso e Política de Privacidade.pdf" target="_blank">Política de Privacidade</a>.
      </p>
      <div class="cookie-buttons">
        <button id="accept-cookies" class="cookie-btn accept">Aceitar</button>
        <button id="decline-cookies" class="cookie-btn decline">Recusar</button>
      </div>
    </div>
  </div>

  <!-- ===============================
       Menu Lateral
       =============================== -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php"><span class="quemSomos">Quem somos</span></a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href="..\html\Pagina_Inicial.html">Home</a></li>
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

  <!-- Conteúdo -->
  <main class="conteudo">
    <div class="breadcrumb">
      <a href="Pagina_Inicial.html" class="home-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-house-fill" viewBox="0 0 16 16">
          <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
          <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293z"/>
        </svg>
      </a>
      <span>/ Quem Somos</span>
    </div>

    <section class="quem-somos">
      <div class= "titulo-img">
      <h1>Quem Somos</h1>

      <div class="banner">
        <img src="../img\banner_qm_somos.jpg" alt="Imagem decorativa">
      </div>
    </div>
<div class="sobre-container">
      <div class="texto">
        <p>
          A <strong>Avena</strong> é uma plataforma criada pela Singularity Solutions para
          <strong>valorizar o trabalho feminino e promover a autonomia de mulheres prestadoras de serviços.</strong>
        </p>

        <p>
          Nosso propósito é conectar profissionais e clientes em um ambiente
          <strong>seguro, acessível e inclusivo</strong>, oferecendo também
          <strong>cursos e oportunidades de crescimento.</strong>
        </p>

        <p>
          Mais do que uma plataforma, somos <strong>uma rede de apoio e empoderamento</strong>,
          onde cada conexão fortalece a independência e o protagonismo das mulheres.
        </p>
      </div>

      <div class="imagem-final">
        <img src="../img\Singularity -logo.jpeg" alt="Imagem ilustrativa" class="img-lado">
      </div>
</div>
    </section>
  </main>

  <script src="../js/cookies.js"></script>
  <script src="../js/cadastro.js"></script>
</body>
</html>
