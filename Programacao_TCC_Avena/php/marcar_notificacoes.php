<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['id_usuario'])) exit;

$id = $_SESSION['id_usuario'];

mysqli_query($conexao, "
    UPDATE notificacoes 
    SET visualizado = 1 
    WHERE id_usuario = $id
");