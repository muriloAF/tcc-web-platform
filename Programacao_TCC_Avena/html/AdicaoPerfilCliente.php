<?php
session_start();
error_reporting(E_ALL);
require_once "../php/conexao.php";
ini_set('display_errors', 1);
if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['email'])){
    echo '<script> window.location.href = "\login.php"</script>';
}

//print_r($status);
//echo  $_SESSION["email"];
//echo  $_SESSION["tipo"];
$email = $_SESSION['email'];
$sql = "SELECT * FROM cliente WHERE email = '$email'";
$result = $conexao->query($sql);
$row = $result->fetch_assoc();

if($row['passou_cadastro'] == 1){
    header('Location: ..\html\bemVindoCliente.php');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edição de Perfil</title>
    <link rel="stylesheet" href="..\css\EdicaoPerfil.css">

</head>



<header>
    <nav>
        <div class="logo">
            <a href="..\html\bemVindoCliente.php"><img src="..\img\logoAvena.png" alt="Logo Avena"></a>
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

    <form method="POST" enctype="multipart/form-data" action="AdicaoPerfilCliente.php">



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
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="Contato da empresa" required>
                    </div>

                    <div class="campo">
                        <label for="localizacao">Localização</label>
                        <input type="text" id="localizacao" name="localizacao" placeholder="Sua região de atuação" required>
                    </div>
            </div>
                <!-- Coluna da direita -->
                <div class="colunaForm2">
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
<div class="botoes">
    <button class="btn-salvar" name="salvar" id="salvar">SALVAR ALTERAÇÕES</button>
</div>
<a href="..\php\sair.php" class="btn-deslogar">DESLOGAR</a>
</div>


</body>
<script rel="preload" src="..\js\EdicaoPerfilCliente.js"></script>
<script  src="../js/cadastro.js"></script>
<script src="..\js\cookies.js"></script> 
</html>



<?php


// Prevent mysqli from throwing uncaught exceptions and show errors instead
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once(__DIR__ . '/../php/conexao.php');

if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status == 1){
        echo '<script>mostrarModal("Complete seu cadastro primeiro")</script>'; 
    }
}

if (!isset($conexao) || !($conexao instanceof mysqli)) {
    die("❌ Erro: variável \$conexao não é uma instância válida de mysqli.<br>");
} else {
    //echo "✅ Conexão MySQLi válida.<br>";
}

//print_r($_SESSION);

if (isset($_POST['salvar'])) {

    // Recupera da sessão de forma segura
    $email = $_SESSION['email'] ?? null;
    $senha = $_SESSION['senha'] ?? null;

    if (!$email || !$senha) {
        //echo "Sessão inválida. Faça login novamente.";
        exit;
    }

    // Busca o ID do usuário logado usando prepared statement
    $sqlSel = "SELECT id_usuario FROM cliente WHERE email = ? AND senha = ?";
    if (!($stmtSel = $conexao->prepare($sqlSel))) {
        //echo "Erro no prepare SELECT: " . $conexao->error;
        exit;
    }
    $stmtSel->bind_param("ss", $email, $senha);
    if (!$stmtSel->execute()) {
        //echo "Erro no execute SELECT: " . $stmtSel->error;
        $stmtSel->close();
        exit;
    }

    // try get_result(), fallback to bind_result if not available
    $result = $stmtSel->get_result();
    if ($result !== false) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $id_usuario = (int)$row['id_usuario'];
        } else {
            //echo "Usuário não encontrado.";
            $stmtSel->close();
            exit;
        }
    } else {
        // fallback
        $stmtSel->store_result();
        if ($stmtSel->num_rows > 0) {
            $stmtSel->bind_result($id_usuario);
            $stmtSel->fetch();
            $id_usuario = (int)$id_usuario;
        } else {
            //echo "Usuário não encontrado (fallback).";
            $stmtSel->close();
            exit;
        }
    }
    $stmtSel->close();

    // Salvamento da imagem de perfil (se enviada)
    if (isset($_FILES['fotoPerfil']) && !empty($_FILES['fotoPerfil']['name'])) {
        $uploadDirRel = "../ImgPerfilCliente/";
        $uploadDirAbs = __DIR__ . "/../ImgPerfilCliente/";
        if (!is_dir($uploadDirAbs)) {
            mkdir($uploadDirAbs, 0755, true);
        }

        $extensao = pathinfo($_FILES['fotoPerfil']['name'], PATHINFO_EXTENSION);
        $nomeArquivo = "perfil_" . $id_usuario . "." . $extensao;
        $caminhoDestinoRel = $uploadDirRel . $nomeArquivo; // caminho relativo a salvar no banco
        $caminhoDestinoAbs = $uploadDirAbs . $nomeArquivo; // caminho físico para move_uploaded_file

        if (move_uploaded_file($_FILES['fotoPerfil']['tmp_name'], $caminhoDestinoAbs)) {
            $sqlUpdateImg = "UPDATE cliente SET imgperfil = ? WHERE id_usuario = ?";
            if ($stmtImg = $conexao->prepare($sqlUpdateImg)) {
                $stmtImg->bind_param("si", $caminhoDestinoRel, $id_usuario);
                if (!$stmtImg->execute()) {
                    //echo "Erro ao salvar imagem no banco: " . $stmtImg->error . "<br>";
                }
                $stmtImg->close();
            } else {
                //echo "Erro no prepare UPDATE imagem: " . $conexao->error . "<br>";
            }
        } else {
            //echo "Erro no upload da imagem.<br>";
        }
    }

    // Recupera campos do formulário com default seguro
    $cliente_telefone = $_POST['telefone'] ?? '';
    $cliente_localizacao = $_POST['localizacao'] ?? '';
    $cliente_facebook = $_POST['facebook'] ?? '';
    $cliente_instagram = $_POST['instagram'] ?? '';

    // Atualiza os dados do cliente com prepared statement
    $sql = "UPDATE cliente SET 
        cliente_telefone = ?,
        cliente_localizacao = ?,
        cliente_facebook = ?,
        cliente_instagram = ?
    WHERE id_usuario = ?";

    if (mysqli_ping($conexao)) {
        //echo "Conexão ativa com o banco de dados.<br>";
    } else {
        //echo "Erro na conexão: " . mysqli_connect_error() . "<br>";
    }

        //echo "<pre>";
//echo "SQL preparado: $sql\n";
//echo "Valores:\n";
//print_r([
    //'telefone' => $cliente_telefone,
    //'localizacao' => $cliente_localizacao,
    //'facebook' => $cliente_facebook,
    //'instagram' => $cliente_instagram,
    //'id_usuario' => $id_usuario
//]);
//echo "</pre>";


    if (!($stmt = $conexao->prepare($sql))) {
        //echo "Erro ao executar UPDATE: ";
        exit;
    }

    if (!$stmt->bind_param("ssssi", $cliente_telefone, $cliente_localizacao, $cliente_facebook, $cliente_instagram, $id_usuario)) {
        //echo "Erro no bind_param: " . $stmt->error;
        $stmt->close();
        exit;
    }

    if (!$stmt->execute()) {
        //echo "Erro ao executar UPDATE: " . $stmt->error . "<br>";
        //echo "Erro MySQL: " . $conexao->error . "<br>";
        //echo "Código do erro: " . $stmt->errno . "<br>";
        $stmt->close();
        exit;
    }

    // marca que passou cadastro
    $sql2 = "UPDATE cliente SET passou_cadastro = 1 WHERE id_usuario = ?";
    if ($stmt2 = $conexao->prepare($sql2)) {
        $stmt2->bind_param("i", $id_usuario);
        if (!$stmt2->execute()) {
            //echo "Erro ao atualizar passou_cadastro: " . $stmt2->error . "<br>";
        }else{
            echo "<script>window.location.href='../html/bemVindoCliente.php';</script>";
        }
        $stmt2->close();
    } else {
        //echo "Erro no prepare UPDATE passou_cadastro: " . $conexao->error . "<br>";
    }

    //echo "Dados atualizados com sucesso!<br>";

    $stmt->close();
}
?>