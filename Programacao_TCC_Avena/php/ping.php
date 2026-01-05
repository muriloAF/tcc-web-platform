<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$info = [
  'ok' => true,
  'time' => date('c'),
  'session_tipo' => isset($_SESSION['tipo']) ? $_SESSION['tipo'] : null,
  'session_keys' => array_keys($_SESSION)
];
echo json_encode($info, JSON_UNESCAPED_UNICODE);
