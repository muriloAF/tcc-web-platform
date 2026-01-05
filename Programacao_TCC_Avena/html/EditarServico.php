
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once(__DIR__ . '/../php/conexao.php');
//print_r($_SESSION);
//echo  $_SESSION["email"];
//echo  $_SESSION["tipo"];
$email = $_SESSION['email'] ?? ($_SESSION['prestadora']['email'] ?? ($_SESSION['cliente']['email'] ?? null));
$sql = "SELECT * FROM prestadora WHERE email = '$email'";
$result = $conexao->query($sql);
$row = $result->fetch_assoc();

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
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edição de Perfil</title>
    <link rel="stylesheet" href="..\css\EditarServico.css">

</head>



<header>
    <nav>
        <div class="logo">
            <a href="..\html\Pagina_Inicial.html"><img src="..\img\logoAvena.png" alt="Logo Avena"></a>
        </div>
        <div class="menu">

            <button class="menu-icon" id="menu-btn">&#9776;</button>
        </div>
    </nav>
</header>




<body>

    <!-- 
========================
// BOTÃO VOLTAR
========================
-->
<style>

 .arrow-animated {
    position: relative;
    top: 80px;
    left: 15px;
    color: #917ba4;
    width: 30px;  
    height:30px; 
    animation: floatLeft 1.6s ease-in-out infinite;
    top: 120px;

  }



  @keyframes floatLeft {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(-2px); }
    100% { transform: translateX(0); }
  }

</style>
<a href= "..\html\bemVindoPrestadora.php">
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



    <div class="headerPerfil">



        <div class="meuPerfil">
            <img src="..\img\meuPerfil.png" alt="Meu Perfil">
        </div>

    <form method="POST" enctype="multipart/form-data" action="EditarServico.php">



        <div class="adicionarFoto">
            <!-- Input escondido -->
            <input type="file" id="fotoPerfil" name="fotoPerfil" accept="image/*" hidden >

            <!-- Círculo clicável -->
            <label for="fotoPerfil" class="circuloUpload">
                <img id="previewFoto" src="../img/adicionarFoto.png" alt="Adicionar Foto">
            </label>

            <div class="linha"></div>
        </div>



    </div>
    <!-- Container principal do formulário -->
    <div class="perfil-wrapper">
    <div class="Formulario">

        <!-- Início do formulário -->
        

            <!-- Duas colunas: esquerda e direita -->
            <div class="form-container">

                <!-- Coluna da esquerda -->
                <div class="colunaForm1">

                    <div class="campo">
                        <label for="nome">Nome da empresa</label>
                        <input type="text" id="nome" name="nome" placeholder="Nome de sua empresa" >
                    </div>

                    <div class="campo">
                        <label for="telefone">Telefone da empresa</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="Contato da empresa" >
                    </div>

                    <div class="campo">
                        <label for="email">E-mail da empresa</label>
                        <input type="email" id="email" name="email" placeholder="Email da empresa" >
                    </div>

                </div>

                <!-- Coluna da direita -->
                <div class="colunaForm2">

                    <div class="campo">
                        <label for="localizacao">Localização</label>
                        <input type="text" id="localizacao" name="localizacao" placeholder="Sua região de atuação" >
                    </div>

                    <div class="campo">
                        <label for="facebook">Facebook</label>
                        <input type="url" id="facebook" name="facebook" placeholder="Coloque a url da conta">
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
        </div>

        <!-- Coluna da direitaa -->
        <div class="coluna-direita">
            <h4>Suas Fotos</h4>
                <!-- Banner 1 -->
                
  <div class="fotos-container">  
    
    <label for="Banner1" class="foto">
      <input type="file" id="Banner1" name="Banner1" accept="image/*"  hidden>
      <img id="previewBanner1" src="" alt="Banner 1" style="display:none;">
      <span class="lixeira">🗑</span>
    </label>
    <!-- Banner 2 -->
    <label for="Banner2" class="foto">
      <input type="file" id="Banner2" name="Banner2" accept="image/*"  hidden>
      <img id="previewBanner2" src="" alt="Banner 2" style="display:none;">
      <span class="lixeira">🗑</span>
    </label>
    
    <!-- Banner 3 -->
    
        <label for="Banner3" class="foto" id="banner3">
        <input type="file" id="Banner3" name="Banner3" accept="image/*"  hidden>
        <img id="previewBanner3" src="" alt="Banner 3" style="display:none;">
        <span class="lixeira">🗑</span>
    </label>
    </form>
    
    
  </div>
</div>
        </div>

    </div><!-- fim container -->

    <div class="botoes">
        <button class="btn-salvar" name="salvar" id="salvar">SALVAR ALTERAÇÕES</button>
        <a href="..\php\sair.php" class="btn-deslogar">DESLOGAR</a>
    </div>

    </div><!-- fim perfil-wrapper -->


    <br>
    <br>
    <br>
    <br>
    

</body>
<script rel="preload" src="..\js\EdicaoPerfil.js"></script>
<script src="../js/cadastro.js"></script>
<script src="..\js\cookies.js"></script> 
</html>

<?php
    
error_reporting(E_ALL);
ini_set('display_errors', 1);



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
// Puxa os dados atuais do banco
$sqlAtual = "SELECT * FROM prestadora WHERE id_usuario = '$id_usuario'";
$resultAtual = mysqli_query($conexao, $sqlAtual);
$dadosAtuais = mysqli_fetch_assoc($resultAtual);

// Mantém os antigos se o campo estiver vazio
$empresa_nome = !empty($_POST['nome']) ? $_POST['nome'] : $dadosAtuais['empresa_nome'];
$empresa_telefone = !empty($_POST['telefone']) ? $_POST['telefone'] : $dadosAtuais['empresa_telefone'];
$empresa_email = !empty($_POST['email']) ? $_POST['email'] : $dadosAtuais['empresa_email'];
$empresa_localizacao = !empty($_POST['localizacao']) ? $_POST['localizacao'] : $dadosAtuais['empresa_localizacao'];
$empresa_facebook = !empty($_POST['facebook']) ? $_POST['facebook'] : $dadosAtuais['empresa_facebook'];
$empresa_instagram = !empty($_POST['instagram']) ? $_POST['instagram'] : $dadosAtuais['empresa_instagram'];
$empresa_biografia = !empty($_POST['biografia']) ? $_POST['biografia'] : $dadosAtuais['empresa_biografia'];
$empresa_servicos = !empty($_POST['servicos']) ? $_POST['servicos'] : $dadosAtuais['empresa_servicos'];




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