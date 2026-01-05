<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esse é o login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
  integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  <link rel="stylesheet" href="../css/Login.css">
</head>
<body>

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
        <img src="..\img\logoAvena.png" alt="Logo Avena" href="Pagina_Inicial.html">
        </a>
      </div>
      <div class="menu">
        <button class="menu-icon" id="menu-btn">&#9776;</button>
      </div>  
  </nav>
</header>
 
  <nav id="menulogin" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php">Quem somos</a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href="..\html\Pagina_Inicial.html">Home</a></li>
    </ul>
  </nav>


<main class="login-container">
  <div class="login-card">
        <div class="login-form">



      <form action="../php/validarLogin.php" method="POST">
        <div class="mb-3">
          <label for="email">E-mail</label>
          <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="senha">Senha</label>
          <div class="input-group">
          <input type="password" name="senha" id="senha" class="form-control" required>
          
         </div>
         
        <label for="tipo">Entrar como</label>
        <select id="tipo" name="tipo" required>
          <option value="">Selecione...</option>
          <option value="profissional">Profissional</option>
          <option value="contratante">Contratante</option>
        </select>

        </div>
        <a href="..\html\recuperaSenha.php" class="forgot">Esqueceu sua senha? </a>
        <button type="submit" class="btn-login" name="submit" >ENTRAR</button>
        <p class="signup">Ainda não está no Avena? <a href="cadastro.php">Crie uma Conta.</a></p>
      </form>
    </div>
     <div class="login-image">
        <img src="..\img\mulher cabelo preto.jpeg" alt="Ilustração login">
      </div>
    </div>
</main>

 

</body>


<script src="../js/login.js"></script>
<script>
    // Captura o parâmetro "erro" da URL
    const urlParams = new URLSearchParams(window.location.search);
    const erro = urlParams.get('erro');

    if (erro === '1') {
        // Login inválido
        mostrarModal("Email ou senha inválidos!");
    }
    if (erro === '2') {
        // Tentou acessar sem enviar formulário
        mostrarModal("Acesso inválido, preencha os campos.");
    }
</script>
<script src="..\js\cookies.js"></script>



</html>

