<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Garante cabeçalho UTF-8 para acentuação correta
header('Content-Type: text/html; charset=utf-8');
include_once(__DIR__ . '/../php/conexao.php');
// Detecta sessão (novo e antigo formato). Esta agenda é para cliente.
$idUsuario = null; $tipoUsuario = null;
if (!empty($_SESSION['cliente']['id_usuario'])) {
  $idUsuario = (int)$_SESSION['cliente']['id_usuario'];
  $tipoUsuario = 'cliente';
} elseif (!empty($_SESSION['id_usuario']) && (($_SESSION['tipo'] ?? null) === 'cliente')) {
  $idUsuario = (int)$_SESSION['id_usuario'];
  $tipoUsuario = 'cliente';
} elseif (!empty($_SESSION['prestadora']['id_usuario']) || (!empty($_SESSION['tipo']) && $_SESSION['tipo'] !== 'cliente')) {
  // Se estiver logado como prestadora, redireciona para a agenda da prestadora
  header('Location: ../html/agendaPrestadora.php');
  exit;
}
if (!$idUsuario) {
  header('Location: ../html/login.php');
  exit;
}
$msg = '';
// Adicionar evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_evento'])) {
  $data = trim($_POST['data_evento']);
  $anotacao = trim($_POST['anotacao'] ?? '');
  if ($data !== '') {
    $stmt = $conexao->prepare('INSERT INTO agenda (id_usuario, tipo_usuario, data_evento, anotacao) VALUES (?,?,?,?)');
    if ($stmt) {
      $stmt->bind_param('isss', $idUsuario, $tipoUsuario, $data, $anotacao);
      if ($stmt->execute()) { $msg = 'Evento adicionado.'; } else { $msg = 'Erro ao adicionar.'; }
      $stmt->close();
    } else { $msg = 'Erro interno (prepare).'; }
  } else { $msg = 'Data obrigatória.'; }
}
// Excluir evento
if (isset($_GET['delete'])) {
  $del = (int)$_GET['delete'];
  if ($del > 0) {
    $stmt = $conexao->prepare('DELETE FROM agenda WHERE id = ? AND id_usuario = ? AND tipo_usuario = ?');
    if ($stmt) {
      $stmt->bind_param('iis', $del, $idUsuario, $tipoUsuario);
      $stmt->execute();
      $stmt->close();
      header('Location: agenda.php');
      exit;
    }
  }
}
// Listar eventos
$eventos = [];
$stmt = $conexao->prepare('SELECT id, data_evento, anotacao, criado_em FROM agenda WHERE id_usuario = ? AND tipo_usuario = ? ORDER BY data_evento DESC, id DESC');
if ($stmt) {
  $stmt->bind_param('is', $idUsuario, $tipoUsuario);
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $eventos[] = $row; }
  }
  $stmt->close();
}
// Normalize session (supports both flat and nested formats)
$email = $_SESSION['email'] ?? ($_SESSION['prestadora']['email'] ?? ($_SESSION['cliente']['email'] ?? null));
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Agenda - Cliente</title>
  <link rel="stylesheet" href="../css/bemVindoCliente.css" />
  <link rel="stylesheet" href="../css/header_nav.css" />
  <style>
    body{font-family:system-ui, Arial; margin:0; background:#f8fafc;}
    .wrap{max-width:880px; margin:0 auto; padding:20px;}
    h1{margin:0 0 16px; font-size:28px;}
    form.agenda-form{display:flex; gap:12px; flex-wrap:wrap; background:#fff; padding:12px 16px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,.06); margin-bottom:20px;}
    form.agenda-form input, form.agenda-form textarea{padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical;}
    form.agenda-form button{background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:600;}
    table{width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 5px rgba(0,0,0,.06);}
    th,td{padding:10px 12px; font-size:14px; text-align:left; border-bottom:1px solid #f1f5f9;}
    th{background:#eff6ff; font-weight:600;}
    tr:last-child td{border-bottom:none;}
    .actions a{color:#dc2626; text-decoration:none; font-weight:600;}
    .msg{margin:0 0 12px; font-size:14px; color:#0d9488;}
    .back{display:inline-block; margin-bottom:14px; text-decoration:none; font-size:13px; color:#374151; background:#e5e7eb; padding:6px 10px; border-radius:6px;}
    .empty{padding:20px; text-align:center; font-style:italic; color:#64748b;}
  </style>
</head>
<body class="fixed-header-page">
<?php $renderNotifications = true; include_once(__DIR__ . '/../php/header_nav.php'); ?>
<div class="wrap">
  <a class="back" href="bemVindoCliente.php">← Voltar</a>
  <h1>Minha Agenda</h1>
  <?php if($msg){ echo '<p class="msg">'.htmlspecialchars($msg).'</p>'; } ?>
  <form method="post" class="agenda-form" autocomplete="off">
    <div style="flex:1; min-width:160px; display:flex; flex-direction:column; gap:4px;">
      <label for="data_evento">Data:</label>
      <input type="date" name="data_evento" id="data_evento" required />
    </div>
    <div style="flex:2; min-width:240px; display:flex; flex-direction:column; gap:4px;">
      <label for="anotacao">Anotação (opcional):</label>
      <textarea name="anotacao" id="anotacao" rows="2" placeholder="Descrição ou detalhes"></textarea>
    </div>
    <div style="display:flex; align-items:flex-end;">
      <button type="submit">Adicionar</button>
    </div>
  </form>
  <?php if(!$eventos){ echo '<div class="empty">Nenhum evento cadastrado.</div>'; } else { ?>
  <table aria-label="Eventos cadastrados">
    <thead><tr><th>Data</th><th>Anotação</th><th>Criado</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach($eventos as $ev){ ?>
      <tr>
        <td><?= htmlspecialchars($ev['data_evento']) ?></td>
        <td><?= htmlspecialchars($ev['anotacao'] ?: '-') ?></td>
        <td><?= htmlspecialchars($ev['criado_em']) ?></td>
        <td class="actions"><a href="agenda.php?delete=<?= (int)$ev['id'] ?>" onclick="return confirm('Remover este evento?')">Remover</a></td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
  <?php } ?>
</div>
</body>
</html>
