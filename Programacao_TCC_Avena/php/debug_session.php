<?php
session_start();
include_once(__DIR__ . '/conexao.php');
header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conexao, 'utf8');

// info útil para debug
echo json_encode([
  'session' => $_SESSION,
  'cookies' => $_COOKIE,
  'php_version' => PHP_VERSION,
  'time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>