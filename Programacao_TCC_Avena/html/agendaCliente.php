<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../php/conexao.php";
// Garante cabeçalho UTF-8 para acentuação correta
header('Content-Type: text/html; charset=utf-8');

// Normalize session values: support both flat and nested session formats
$email = $_SESSION['email'] ?? ($_SESSION['prestadora']['email'] ?? ($_SESSION['cliente']['email'] ?? null));
$tipo  = $_SESSION['tipo']  ?? ($_SESSION['prestadora']['tipo']  ?? ($_SESSION['cliente']['tipo']  ?? null));

// If we don't have a logged user, redirect to login
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

// Detecta sessão do cliente (compatível com formato novo e antigo)
$idUsuario = null;
if (!empty($_SESSION['cliente']['id_usuario'])) {
    $idUsuario = (int)$_SESSION['cliente']['id_usuario'];
} elseif (!empty($_SESSION['id_usuario']) && (($_SESSION['tipo'] ?? null) === 'cliente')) {
    $idUsuario = (int)$_SESSION['id_usuario'];
}

// Se não houver sessão válida de cliente, redireciona para login
if (!$idUsuario) {
       header("Location: ../html/login.php");
    exit;
}

// ======================= BUSCAR DADOS DO CLIENTE =======================
$sqlCliente = "SELECT nome, email, imgperfil FROM cliente WHERE id_usuario = ?";
$stmt = $conexao->prepare($sqlCliente);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultCliente = $stmt->get_result();

if ($resultCliente->num_rows === 0) {
       header("Location: ../html/login.php");
    exit();
}

$cliente = $resultCliente->fetch_assoc();


// ======================= INSERIR ANOTAÇÃO =======================
$id_usuario = $idUsuario;
$tipo_usuario = "cliente";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["texto"])) {

    $data = $_POST["data"];
    $texto = $_POST["texto"];

    // SUA TABELA TEM: id, id_usuario, tipo_usuario, data_evento, anotacao, criado_em
    $sqlInsert = "INSERT INTO agenda (id_usuario, tipo_usuario, data_evento, anotacao)
                  VALUES (?, ?, ?, ?)";

    $stmt = $conexao->prepare($sqlInsert);
    $stmt->bind_param("isss", $id_usuario, $tipo_usuario, $data, $texto);
    $stmt->execute();

    header("Location: agendaCliente.php?ok=1");
    exit();
}


// ======================= BUSCAR ANOTAÇÕES =======================
$sqlLista = "SELECT id, data_evento, anotacao 
             FROM agenda
             WHERE id_usuario = ? AND tipo_usuario = ?
             ORDER BY data_evento ASC";

$stmt = $conexao->prepare($sqlLista);
$stmt->bind_param("is", $id_usuario, $tipo_usuario);
$stmt->execute();
$result = $stmt->get_result();

if(isset($_POST['deletar'])) {
    $idDeletar = intval($_POST['deletar']);
    $sqlDeletar = "DELETE FROM agenda WHERE id = ? AND id_usuario = ? AND tipo_usuario = ?";
    $stmtDel = $conexao->prepare($sqlDeletar);
    $stmtDel->bind_param("iis", $idDeletar, $id_usuario, $tipo_usuario);
    $stmtDel->execute();
    header("Location: agendaCliente.php");
    exit();
}


// ======================= GERAR CALENDÁRIO =======================

// Pega mês e ano atual OU do GET (?mes=11&ano=2025)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date("m");
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date("Y");

// Primeiro e último dia do mês
$primeiroDia = new DateTime("$ano-$mes-01");
$ultimoDia = new DateTime("$ano-$mes-" . $primeiroDia->format("t"));

// Dia da semana do primeiro dia (0=domingo)
$diaSemana = intval($primeiroDia->format("w"));

// Carregar todas as anotações desse mês
$sqlCalendario = "SELECT id, data_evento, anotacao 
                  FROM agenda 
                  WHERE id_usuario = ? AND tipo_usuario = ? 
                  AND MONTH(data_evento) = ? AND YEAR(data_evento) = ?";
$stmtCal = $conexao->prepare($sqlCalendario);
$stmtCal->bind_param("isii", $id_usuario, $tipo_usuario, $mes, $ano);
$stmtCal->execute();
$anotacoesMes = $stmtCal->get_result()->fetch_all(MYSQLI_ASSOC);

// Converter para array indexado por dia
$marcados = [];
foreach ($anotacoesMes as $evento) {
    $dia = intval(date("d", strtotime($evento["data_evento"])));
    $marcados[$dia][] = $evento;
}

setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf8', 'portuguese');

function mesPorExtenso($mes) {
    $nomes = [
        1 => "Janeiro",
        2 => "Fevereiro",
        3 => "Março",
        4 => "Abril",
        5 => "Maio",
        6 => "Junho",
        7 => "Julho",
        8 => "Agosto",
        9 => "Setembro",
        10 => "Outubro",
        11 => "Novembro",
        12 => "Dezembro"
    ];

    return $nomes[(int)$mes] ?? "";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agenda | Avena</title>

    <link rel="stylesheet" href="../css/agendaCliente.css">
</head>
<body>

<!-- COOKIE BANNER -->
<div id="cookie-banner" class="cookie-banner">
  <div class="cookie-content">
    <h4>Privacidade e Cookies</h4>
    <p>
      A Singularity Solutions utiliza cookies para melhorar sua experiência.
    <a href="..\img\AVENA - Termos de Uso e Política de Privacidade.pdf" target="_blank">
        Política de Privacidade
      </a>
    </p>
    <div class="cookie-buttons">
      <button id="accept-cookies" class="cookie-btn accept">Aceitar</button>
      <button id="decline-cookies" class="cookie-btn decline">Recusar</button>
    </div>
  </div>
</div>

<!-- MODAL -->
<div id="modalErro" class="modal">
    <div class="modal-content">
        <p id="mensagemErro">...</p>
        <button onclick="fecharModal()">OK</button>
    </div>
</div>

<header class="header">
    <div class="logo">
        <a href="Pagina_Inicial.html">
            <img src="../img/logoAvena.png" >
        </a>
    </div>

    <div class="perfil-area" id="perfil-area">
        <span class="nome"><?php echo htmlspecialchars($cliente['nome']); ?></span>
        <img src="<?php echo htmlspecialchars($cliente['imgperfil']); ?>" class="perfil-foto">
    </div>
</header>

<!-- 
========================
// BOTÃO VOLTAR
========================
-->
<style>
  .arrow-animated {
    color: #917ba4;
    padding: 20px 40px;
    width: 30px;  
    height: 30px; 
    animation: floatLeft 1.6s ease-in-out infinite;
  }

  @keyframes floatLeft {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(-2px); }
    100% { transform: translateX(0); }
  }
</style>
<a href="bemVindoCliente.php" >
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


<main class="agenda-container">





    <h1 class="titulo-agenda">Minha Agenda</h1>

    <!-- FORMULÁRIO DE NOVA ANOTAÇÃO -->
    <form method="POST" class="form-anotacao">
        <div class="form-group">
            <label>Data:</label>
            <input type="date" name="data" required>
        </div>

        <div class="form-group">
            <label>Anotação:</label>
            <textarea name="texto" maxlength="255" required></textarea>
        </div>

        <button type="submit" class="btn-salvar">Salvar Anotação</button>
    </form>

    <h2 class="titulo-lista">Minhas Anotações</h2>



    <!-- CALENDÁRIO -->
<div class="calendario-container">

    <div class="calendario-header">
        <a href="?mes=<?= ($mes == 1 ? 12 : $mes - 1) ?>&ano=<?= ($mes == 1 ? $ano - 1 : $ano) ?>" class="cal-btn">◀</a>

        <h2><?= mesPorExtenso($mes) ?> de <?= $ano ?></h2>

        <a href="?mes=<?= ($mes == 12 ? 1 : $mes + 1) ?>&ano=<?= ($mes == 12 ? $ano + 1 : $ano) ?>" class="cal-btn">▶</a>
    </div>

    <table class="calendario">
        <tr>
            <th>Dom</th><th>Seg</th><th>Ter</th><th>Qua</th>
            <th>Qui</th><th>Sex</th><th>Sáb</th>
        </tr>
        <tr>
        <?php
            // células vazias antes do 1° dia
            for ($i=0; $i < $diaSemana; $i++) echo "<td></td>";

            $dia = 1;
            while ($dia <= intval($primeiroDia->format("t"))) {

                // Quebra linha a cada sábado
                if (($diaSemana % 7) == 0 && $dia != 1) echo "</tr><tr>";

                $temEvento = isset($marcados[$dia]);

                echo "<td class='".($temEvento ? "dia-evento" : "")."'>";

                echo "<div class='dia-numero'>$dia</div>";

                if ($temEvento) {
                    foreach ($marcados[$dia] as $ev) {
                        echo "<div class='evento-mini'>".htmlspecialchars($ev['anotacao'])."</div>";
                    }
                }

                echo "</td>";

                $dia++;
                $diaSemana++;
            }

            // completa linha final
            while ($diaSemana % 7 != 0) {
                echo "<td></td>";
                $diaSemana++;
            }
        ?>
        </tr>
    </table>
</div>






    <div class="lista-anotacoes">
        <?php while($a = mysqli_fetch_assoc($result)): ?>


        <?php
            $dataEvento = new DateTime($a['data_evento'] . " 00:00:00");
            $hoje = new DateTime("today 00:00:00");

            $diff = $hoje->diff($dataEvento);
            $diasRestantes = (int)$diff->format("%r%a");
        ?>


<div class="anotacao-card">

    <div class="anotacao-data">
        <?= date("d/m/Y", strtotime($a['data_evento'])) ?>
    </div>

    <div class="anotacao-texto">
        <?= nl2br(htmlspecialchars($a['anotacao'])) ?>
    </div>

    <div class="dias-restantes">
        <?php
            if ($diasRestantes > 1) {
                echo "Faltam $diasRestantes dias";
            } elseif ($diasRestantes === 1) {
                echo "Falta 1 dia";
            } elseif ($diasRestantes === 0) {
                echo "É hoje!";
            } else {
                echo "Evento passou há " . abs($diasRestantes) . " dias";
            }
        ?>
    </div>

    <form method="POST">
        <input type="hidden" name="deletar" value="<?= $a['id'] ?>">
        <button class="btn-delete" type="submit">Excluir</button>
    </form>

</div>

<?php endwhile; ?>
    </div>

</main>

<script src="../js/login.js"></script>
<script src="..\js\cookies.js"></script>

</body>
</html>
