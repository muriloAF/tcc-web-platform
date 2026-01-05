<?php
$servidor = "sql306.infinityfree.com";
$usuario = "if0_40826943";
$senha = "wOA62zEqs9e3Hcx";
$dbname = "if0_40826943_db_avena";

$conexao = new mysqli($servidor, $usuario, $senha, $dbname);
$conexao->set_charset('utf8mb4');

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

date_default_timezone_set('America/Sao_Paulo');

?>
