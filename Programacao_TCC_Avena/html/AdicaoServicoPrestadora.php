<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once(__DIR__ . '/../php/conexao.php');

if (!isset($_SESSION['email']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'profissional'){
    echo '<script> window.location.href = "\login.php"</script>';
    session_destroy();
    exit;
}

$email = $_SESSION['email'];
$sql = "SELECT * FROM prestadora WHERE email = '$email'";
$result = $conexao->query($sql);
$row = $result->fetch_assoc();

// Mover a verificação do status para antes do redirecionamento
$showModal = false;
if (isset($_GET['status']) && $_GET['status'] == 1) {
    $showModal = true;
}

// Só redireciona se não for para mostrar o modal
if($row['passou_cadastro'] == 1 && !$showModal){
    header('Location: ..\html\bemVindoPrestadora.php');
    exit;
}
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edição de Perfil</title>
    <link rel="stylesheet" href="..\css\EdicaoPerfil.css">

    <?php if ($showModal): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        mostrarModal("Complete seu cadastro primeiro");
    });
</script>
<?php endif; ?>
</head>



<header>
    <nav>
        <div class="logo">
            <a href="..\html\bemVindoPrestadora.php"><img src="..\img\logoAvena.png" alt="Logo Avena"></a>
        </div>
        <div class="menu">

            <button class="menu-icon" id="menu-btn">&#9776;</button>
        </div>
    </nav>
</header>


<body>

     <!-- Mensagem -->
    <div id="modalErro" class="modal">
        <div class="modal-content">
            <p id="mensagemErro">...</p>
            <button onclick="fecharModal()">OK</button>
        </div>
    </div>


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

  <!-- Menu -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php">Quem somos</a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href=".\Pagina_Inicial.html"><span class="Home">Home</span></a></li>
    </ul>
  </nav>



    <div class="headerPerfil">



        <div class="meuPerfil">
            <img src="..\img\meuPerfil.png" alt="Meu Perfil">
        </div>

    <form method="POST" enctype="multipart/form-data" action="AdicaoServicoPrestadora.php">



        <div class="adicionarFoto">
            <!-- Input escondido -->
            <input type="file" id="fotoPerfil" name="fotoPerfil" accept="image/*" hidden required>

            <!-- Círculo clicável -->
            <label for="fotoPerfil" class="circuloUpload">
                <img id="previewFoto" src="../img/adicionarFoto.png" alt="Adicionar Foto">
            </label>

            <div class="linha"></div>
        </div>



    </div>
    <!-- Container principal do formulário -->
    <div class="Formulario">

        <!-- Início do formulário -->
        

            <!-- Duas colunas: esquerda e direita -->
            <div class="form-container" style="display: flex; gap: 40px;">

                <!-- Coluna da esquerda -->
                <div class="colunaForm1">

                    <div class="campo">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" placeholder="Nome de sua empresa" required>
                    </div>

                    <div class="campo">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="Contato da empresa" required>
                    </div>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="Email da empresa" required>
                    </div>

                </div>

                <!-- Coluna da direita -->
                <div class="colunaForm2">

                    <div class="campo">
                        <label for="localizacao">Localização</label>
                        <input type="text" id="localizacao" name="localizacao" placeholder="Sua região de atuação" required>
                    </div>

                    <div class="campo">
                        <label for="facebook">Facebook</label>
                        <input type="text" id="facebook" name="facebook" placeholder="Coloque a url da conta">
                    </div>

                    <div class="campo">
                        <label for="instagram">Instagram</label>
                        <input type="text" id="instagram" name="instagram" placeholder="Coloque se @ ou a url">
                    </div>

                </div>

            </div> <!-- Fim das colunas -->

        

    </div>
    <div class="container">

        <!-- Coluna da esquerda -->
        <div class="coluna-esquerda">
            <label for="biografia">Biografia</label>
            <textarea id="biografia" name="biografia" placeholder="Descrição do seu serviço, local de atuação, observações, etc..."></textarea>

            <label for="servicos">Serviços e Valores</label>
            <textarea id="servicos" name="servicos" placeholder="Pequeno detalhamento dos serviços prestados e valores a serem cobrados"></textarea>

<div class="botoes">
    <button class="btn-salvar" name="salvar" id="salvar">SALVAR ALTERAÇÕES</button>
</div>

<a href="..\php\sair.php" class="btn-deslogar">DESLOGAR</a>
        </div>

        <!-- Coluna da direitaa -->
        <div class="coluna-direita">
            <h4>Suas Fotos</h4>
                <!-- Banner 1 -->
                
  <div class="fotos-container">  
    
    <label for="Banner1" class="foto">
      <input type="file" id="Banner1" name="Banner1" accept="image/*" required hidden>
      <img id="previewBanner1" src="" alt="Banner 1" style="display:none;">
      <span class="lixeira">🗑</span>
    </label>
    <!-- Banner 2 -->
    <label for="Banner2" class="foto">
      <input type="file" id="Banner2" name="Banner2" accept="image/*" required hidden>
      <img id="previewBanner2" src="" alt="Banner 2" style="display:none;">
      <span class="lixeira">🗑</span>
    </label>
    
    <!-- Banner 3 -->
    
        <label for="Banner3" class="foto" id="banner3">
        <input type="file" id="Banner3" name="Banner3" accept="image/*" required hidden>
        <img id="previewBanner3" src="" alt="Banner 3" style="display:none;">
        <span class="lixeira">🗑</span>
    </label>
   
    
    
  </div>
</div>
        </div>

    </div>
 

    <br>
    <br>
    <br>
    <br>
    
</form>
</body>
<script>
// Funções do modal - coloque isso inline para garantir que existam
function mostrarModal(mensagem) {
    console.log("Mostrar modal chamado com:", mensagem); // Debug
    document.getElementById("mensagemErro").innerText = mensagem;
    document.getElementById("modalErro").style.display = "flex"; 
}

function fecharModal() {
    document.getElementById("modalErro").style.display = "none";
}
</script>

<!-- Seus outros scripts -->
<script rel="preload" src="..\js\EdicaoPerfil.js"></script>
<script src="../js/cadastro.js"></script>
<script src="..\js\cookies.js"></script>
</html>

<?php
    
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status == 1){
         echo '<script>mostrarModal("Complete seu cadastro primeiro")</script>';  
    }
}

if (isset($_POST['salvar'])) {
    include_once(__DIR__ . '/../php/conexao.php');

    // Recupera da sessão
    $email = $_SESSION['email'];

    // Busca o ID do usuário logado
    $sql = "SELECT id_usuario FROM prestadora WHERE email = '$email'";
    $result = $conexao->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id_usuario = $row['id_usuario'];

        echo "ID do usuário logado: " . $id_usuario;
        // Agora você pode usar esse $id_usuario pra salvar imagem, atualizar perfil etc.
    } else {
        echo "Usuário não encontrado.";
    }

    //Salvamento da imagem de perfil
    if (isset($_FILES['fotoPerfil']) && !empty($_FILES['fotoPerfil']['name'])) {

    $extensao = pathinfo($_FILES['fotoPerfil']['name'], PATHINFO_EXTENSION);
    $nomeArquivo = "perfil_" . $id_usuario . "." . $extensao;
    $caminhoDestino = "../ImgPerfilPrestadoras/" . $nomeArquivo;


    // Move o arquivo
    $resultado = move_uploaded_file($_FILES['fotoPerfil']['tmp_name'], $caminhoDestino);

    if ($resultado) {

        //Salva o arquivo no banco de dados
        echo "Upload realizado com sucesso!";
        // Caminho salvo no banco (ajuste conforme a estrutura do seu projeto)
        $caminhoBanco = $caminhoDestino;

        $sqlUpdate = "UPDATE prestadora SET imgperfil = '$caminhoBanco' WHERE id_usuario = '$id_usuario'";
        if ($conexao->query($sqlUpdate)) {
            echo "Caminho salvo no banco com sucesso!";
        } else {
            echo "Erro ao salvar no banco: " . $conexao->error;
        }
        // FIM -----------------------------------------


    }else {
            echo "Erro no upload";
    }
   }
   //Fim do salvamento da imagem de perfil


//-----------------------------------------------------------------------------------------------------------------



   //Salvamento do banner 1
    if (isset($_FILES['Banner1']) && !empty($_FILES['Banner1']['name'])) {

    $extensao = pathinfo($_FILES['Banner1']['name'], PATHINFO_EXTENSION);
    $nomeArquivo = "banner1_id_" . $id_usuario . "." . $extensao;
    $caminhoDestino = "../ImgBannersPrestadoras/" . $nomeArquivo;


    // Move o arquivo
    $resultado = move_uploaded_file($_FILES['Banner1']['tmp_name'], $caminhoDestino);

    if ($resultado) {
         //Salva o arquivo no banco de dados
        //echo "Upload realizado com sucesso!";
        // Caminho salvo no banco (ajuste conforme a estrutura do seu projeto)
        
        $caminhoBanco = $caminhoDestino;

        $sqlUpdate = "UPDATE prestadora SET banner1 = '$caminhoBanco' WHERE id_usuario = '$id_usuario'";
        if ($conexao->query($sqlUpdate)) {
            echo "Caminho salvo no banco com sucesso!";
        } else {
            echo "Erro ao salvar no banco: " . $conexao->error;
        }
        // FIM -----------------------------------------
    } else {
        echo "Erro no upload";
    }
   }
    //Fim do salvamento do banner 1

//-----------------------------------------------------------------------------------------------------------------

   //Salvamento do banner 2
    if (isset($_FILES['Banner2']) && !empty($_FILES['Banner2']['name'])) {

    $extensao = pathinfo($_FILES['Banner2']['name'], PATHINFO_EXTENSION);
    $nomeArquivo = "banner2_id_" . $id_usuario . "." . $extensao;
    $caminhoDestino = "../ImgBannersPrestadoras/" . $nomeArquivo;


    // Move o arquivo
    $resultado = move_uploaded_file($_FILES['Banner2']['tmp_name'], $caminhoDestino);
    if ($resultado) {
         //Salva o arquivo no banco de dados
        //echo "Upload realizado com sucesso!";
        // Caminho salvo no banco (ajuste conforme a estrutura do seu projeto)
        $caminhoBanco = $caminhoDestino;

        $sqlUpdate = "UPDATE prestadora SET banner2 = '$caminhoBanco' WHERE id_usuario = '$id_usuario'";
        if ($conexao->query($sqlUpdate)) {
            echo "Caminho salvo no banco com sucesso!";
        } else {
            echo "Erro ao salvar no banco: " . $conexao->error;
        }
        // FIM -----------------------------------------
    } else {
        echo "Erro no upload";
    }
   }
   //Fim do salvamento do banner 2

//-----------------------------------------------------------------------------------------------------------------


   //Salvamento do banner 3
    if (isset($_FILES['Banner3']) && !empty($_FILES['Banner3']['name'])) {

    $extensao = pathinfo($_FILES['Banner3']['name'], PATHINFO_EXTENSION);
    $nomeArquivo = "banner3_id_" . $id_usuario . "." . $extensao;
    $caminhoDestino = "../ImgBannersPrestadoras/" . $nomeArquivo;

    // Move o arquivo
    $resultado = move_uploaded_file($_FILES['Banner3']['tmp_name'], $caminhoDestino);

    if ($resultado) {
         //Salva o arquivo no banco de dados
        //echo "Upload realizado com sucesso!";
        // Caminho salvo no banco (ajuste conforme a estrutura do seu projeto)
        $caminhoBanco = $caminhoDestino;

        $sqlUpdate = "UPDATE prestadora SET banner3 = '$caminhoBanco' WHERE id_usuario = '$id_usuario'";
        if ($conexao->query($sqlUpdate)) {
            
            //echo "Caminho salvo no banco com sucesso!";
        } else {
            //echo "Erro ao salvar no banco: " . $conexao->error;
        }
        // FIM -----------------------------------------
        
    } else {
        echo "Erro no upload";
    }
}

   //Fim do salvamento do banner 3


//-----------------------------------------------------------------------------------------------------------------
$empresa_nome = $_POST['nome'];
$empresa_telefone = $_POST['telefone'];
$empresa_email = $_POST['email'];
$empresa_localizacao = $_POST['localizacao'];
$empresa_facebook = $_POST['facebook'];
$empresa_instagram = $_POST['instagram'];
$empresa_biografia = $_POST['biografia'];
$empresa_servicos = $_POST['servicos'];



$sql = "UPDATE prestadora SET 
            empresa_nome='$empresa_nome',
            empresa_telefone='$empresa_telefone',
            empresa_email='$empresa_email',
            empresa_localizacao='$empresa_localizacao',
            empresa_facebook='$empresa_facebook',
            empresa_instagram='$empresa_instagram',
            empresa_biografia='$empresa_biografia',
            empresa_servicos='$empresa_servicos'
        WHERE id_usuario='$id_usuario'";

if (mysqli_query($conexao, $sql)) {
    $sql = "UPDATE prestadora SET passou_cadastro = 1 WHERE id_usuario = '$id_usuario'";
    mysqli_query($conexao, $sql);
    echo "<script>window.location.href='../html/AdicaoServicoPrestadora.php';</script>";
} else {
    echo "Erro ao atualizar: " . mysqli_error($conexao);
}




}


?>