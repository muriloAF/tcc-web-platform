<?php
session_start();

if(isset($_POST['submit']) && !empty($_POST['email']) && !empty($_POST['senha'])) {
    
    include_once('conexao.php');
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    if ($_POST['tipo'] == 'profissional'){  
        $sql = "SELECT * FROM prestadora WHERE email = '$email' and senha = '$senha'";
        $result = $conexao->query($sql);

        if(mysqli_num_rows($result) < 1){
            unset($_SESSION['email']);
            unset($_SESSION['senha']);
            header('Location: ../html/login.php?erro=1');
            exit;
        } else {
            $dados = $result->fetch_assoc();
            // limpa qualquer traço de sessão de 'cliente' para evitar conflito de nomes/ids
            unset($_SESSION['cliente']);
            unset($_SESSION['id_cliente']);
            
            $_SESSION['prestadora'] = [
                'nome' => $dados['nome'],
                'id_usuario' => isset($dados['id_usuario']) ? $dados['id_usuario'] : (isset($dados['id_prestadora']) ? $dados['id_prestadora'] : null)
            ];
            $_SESSION['id_prestadora'] = $_SESSION['prestadora']['id_usuario'];
            // regenerar id da sessão por segurança / evitar mistura de sessão antiga
            session_regenerate_id(true);
            $_SESSION['tipo'] = 'profissional';
            $_SESSION['email'] = $email;
            $_SESSION['senha'] = $senha;
            header('Location: ../html/bemVindoPrestadora.php');
            exit;
        }
    } else {
        $sql = "SELECT * FROM cliente WHERE email = '$email' and senha = '$senha'";
        $result = $conexao->query($sql);

        if(mysqli_num_rows($result) < 1){
            unset($_SESSION['email']);
            unset($_SESSION['senha']);
            header('Location: ../html/login.php?erro=1');
            exit;
        } else {
            // pega os dados do cliente e popula a sessão do mesmo modo que faz para prestadora
            $dados = $result->fetch_assoc();
            // limpa sessão de 'prestadora' para evitar conflito
            unset($_SESSION['prestadora']);
            unset($_SESSION['id_prestadora']);
            
            $_SESSION['cliente'] = [
                'nome' => isset($dados['nome']) ? $dados['nome'] : null,
                'id_usuario' => isset($dados['id_usuario']) ? $dados['id_usuario'] : (isset($dados['id_cliente']) ? $dados['id_cliente'] : null)
            ];
            $_SESSION['id_cliente'] = $_SESSION['cliente']['id_usuario'];
            session_regenerate_id(true);
            $_SESSION['tipo'] = 'cliente';
            $_SESSION['email'] = $email;
            $_SESSION['senha'] = $senha;
            header('Location: ../html/bemVindoCliente.php');
            exit;
        }
    }

} else {
    header('Location: ../html/login.php?erro=2');
    exit;
}
?>
