<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../php/conexao.php";

//print_r($_SESSION);
//exit;


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
// ==========================
// 0) LOGIN + TIPO (aceita novo e antigo formato)
// ==========================
$id_usuario = null; $tipoSess = null;
if (!empty($_SESSION['prestadora']['id_usuario'])) { $id_usuario = (int)$_SESSION['prestadora']['id_usuario']; $tipoSess = 'profissional'; }
elseif (!empty($_SESSION['id_usuario']) && (($_SESSION['tipo'] ?? null) === 'profissional')) { $id_usuario = (int)$_SESSION['id_usuario']; $tipoSess = 'profissional'; }
if (!$id_usuario) { header("Location: ../html/login.php"); exit; }

// ==========================
// 1) DADOS DA PRESTADORA (header)
// ==========================
$stmt = $conexao->prepare("SELECT nome, imgperfil FROM prestadora WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resP = $stmt->get_result();
$prest = $resP->fetch_assoc();
$stmt->close();

// ==========================
// 2) TRATAMENTO DE POSTS
// Usamos um campo hidden 'form_type' para distinguir ações
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';

    // ---- aceitar / recusar solicitação ----
    if ($form_type === 'sol_action' && isset($_POST['id']) && isset($_POST['acao'])) {
        $id = intval($_POST['id']);
        $acao = $_POST['acao'];
        $status = ($acao === 'aceitar') ? 'aceito' : 'recusado';

        $upd = $conexao->prepare("UPDATE solicitacoes SET status = ? WHERE id = ?");
        $upd->bind_param("si", $status, $id);
        $upd->execute();
        $upd->close();

        // pegar id_contratante para notificação
        $q = $conexao->prepare("SELECT id_contratante FROM solicitacoes WHERE id = ?");
        $q->bind_param("i", $id);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();

        if ($r && !empty($r['id_contratante'])) {
            $id_cliente = intval($r['id_contratante']);
            // Inserir notificação (usando prepared stmt)
            $ins = $conexao->prepare("INSERT INTO notificacoes (id_usuario, id_solicitacao, mensagem) VALUES (?, ?, ?)");
            $msg = "Seu pedido foi $status pela prestadora.";
            $ins->bind_param("iis", $id_cliente, $id, $msg);
            $ins->execute();
            $ins->close();
        }

        header("Location: agendaPrestadora.php?success=$status");
        exit;
    }

    // ---- inserir anotação na agenda da prestadora ----
    if ($form_type === 'agenda_insert' && isset($_POST['data']) && isset($_POST['texto'])) {
        $data_ev = $_POST['data'];
        $texto = trim($_POST['texto']);
        $tipo_usuario = 'prestadora';

        $ins = $conexao->prepare("INSERT INTO agenda (id_usuario, tipo_usuario, data_evento, anotacao) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $id_usuario, $tipo_usuario, $data_ev, $texto);
        $ins->execute();
        $ins->close();

        header("Location: agendaPrestadora.php?ok=1");
        exit;
    }

    // ---- excluir anotação ----
    if ($form_type === 'agenda_delete' && isset($_POST['deletar'])) {
        $idDel = intval($_POST['deletar']);
        $del = $conexao->prepare("DELETE FROM agenda WHERE id = ? AND id_usuario = ? AND tipo_usuario = 'prestadora'");
        $del->bind_param("ii", $idDel, $id_usuario);
        $del->execute();
        $del->close();
        header("Location: agendaPrestadora.php");
        exit;
    }
}

// ==========================
// 3) LISTAR SOLICITAÇÕES (para a aba solicitações)
// ==========================
$stmt = $conexao->prepare("
    SELECT s.id, s.status, c.nome AS cliente_nome, c.imgperfil AS cliente_img
    FROM solicitacoes s
    INNER JOIN cliente c ON c.id_usuario = s.id_contratante
    WHERE s.id_prestadora = ?
    ORDER BY s.id DESC
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$solicitacoes = $stmt->get_result();
$stmt->close();

// ==========================
// 4) LISTAR ANOTAÇÕES (para aba agenda + calendário)
// ==========================
$tipo_usuario = 'prestadora';
$stmt = $conexao->prepare("SELECT id, data_evento, anotacao FROM agenda WHERE id_usuario = ? AND tipo_usuario = ? ORDER BY data_evento ASC");
$stmt->bind_param("is", $id_usuario, $tipo_usuario);
$stmt->execute();
$result = $stmt->get_result();
$anotacoes_all = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// para o calendário (mês/ano selecionados)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date("m"));
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : intval(date("Y"));
$stmt = $conexao->prepare("SELECT id, data_evento, anotacao FROM agenda WHERE id_usuario = ? AND tipo_usuario = ? AND MONTH(data_evento) = ? AND YEAR(data_evento) = ?");
$stmt->bind_param("isii", $id_usuario, $tipo_usuario, $mes, $ano);
$stmt->execute();
$anotacoesMes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// montar array indexado por dia
$marcados = [];
foreach ($anotacoesMes as $ev) {
    $dia = intval(date("d", strtotime($ev['data_evento'])));
    $marcados[$dia][] = $ev;
}

// função mês por extenso (pt-BR)
function mesPorExtenso($m) {
    $nomes = [1=>"Janeiro",2=>"Fevereiro",3=>"Março",4=>"Abril",5=>"Maio",6=>"Junho",7=>"Julho",8=>"Agosto",9=>"Setembro",10=>"Outubro",11=>"Novembro",12=>"Dezembro"];
    return $nomes[(int)$m] ?? "";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agenda | Avena</title>

    <link rel="stylesheet" href="../css/agenda.css">
</head>
<body>

<!-- ========================= COOKIE BANNER ========================= -->
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

<!-- ========================= MODAL ========================= -->
<div id="modalErro" class="modal">
    <div class="modal-content">
        <p id="mensagemErro">...</p>
        <button onclick="fecharModal()">OK</button>
    </div>
</div>

<header class="header">
    <div class="logo">
        <a href="Pagina_Inicial.html">
            <img src="../img/logoAvena.png">
        </a>
    </div>

    <div class="perfil-area" id="perfil-area">
        <span class="nome"><?php echo htmlspecialchars($prest['nome']); ?></span>
        <img src="<?php echo htmlspecialchars($prest['imgperfil']); ?>" class="perfil-foto">
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

<div class="tabs-container">

    <div class="tabs-header">
        <button class="tab-btn active" data-tab="0">Agenda</button>
        <button class="tab-btn" data-tab="1">Solicitações</button>
    </div>

    <div class="tabs-wrapper">
        <div class="tabs-content">

            <!-- ===== TAB AGENDA (index 0) ===== -->
            <section class="tab-page" id="tab-agenda">
                <h1 class="titulo-agenda">Minha Agenda</h1>

                <!-- FORMULARIO AGENDA -->
                <form method="POST" class="form-anotacao">
                    <input type="hidden" name="form_type" value="agenda_insert">
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
                            // calculos do mês
                            $primeiroDia = new DateTime("$ano-$mes-01");
                            $ultimoDia = intval($primeiroDia->format("t"));
                            $diaSemana = intval($primeiroDia->format("w")); // 0 dom

                            // células vazias antes do 1° dia
                            for ($i=0; $i < $diaSemana; $i++) echo "<td></td>";

                            $dia = 1;
                            $col = $diaSemana;
                            while ($dia <= $ultimoDia) {

                                if ($col % 7 == 0 && $dia != 1) echo "</tr><tr>";

                                $temEvento = isset($marcados[$dia]);
                                echo "<td class='".($temEvento ? "dia-evento" : "")."'>";
                                echo "<div class='dia-numero'>$dia</div>";
                                if ($temEvento) {
                                    foreach ($marcados[$dia] as $ev) {
                                        echo "<div class='evento-mini'>".htmlspecialchars($ev['anotacao'])."</div>";
                                    }
                                }
                                echo "</td>";

                                $dia++; $col++;
                            }

                            // completa linha final
                            while ($col % 7 != 0) { echo "<td></td>"; $col++; }
                        ?>
                        </tr>
                    </table>
                </div>

                <!-- LISTA DE ANOTAÇÕES -->
                <h2 class="titulo-lista">Minhas Anotações</h2>
                <div class="lista-anotacoes">
                    <?php foreach ($anotacoes_all as $a): ?>
                        <?php
                            $dataEvento = new DateTime($a['data_evento'] . " 00:00:00");
                            $hoje = new DateTime("today 00:00:00");
                            $diff = $hoje->diff($dataEvento);
                            $diasRestantes = (int)$diff->format("%r%a");
                        ?>
                        <div class="anotacao-card">
                            <div class="anotacao-data"><?= date("d/m/Y", strtotime($a['data_evento'])) ?></div>
                            <div class="anotacao-texto"><?= nl2br(htmlspecialchars($a['anotacao'])) ?></div>
                            <div class="dias-restantes">
                                <?php
                                    if ($diasRestantes > 1) echo "Faltam $diasRestantes dias";
                                    elseif ($diasRestantes === 1) echo "Falta 1 dia";
                                    elseif ($diasRestantes === 0) echo "É hoje!";
                                    else echo "Evento passou há " . abs($diasRestantes) . " dias";
                                ?>
                            </div>

                            <form method="POST" style="margin-left:10px;">
                                <input type="hidden" name="form_type" value="agenda_delete">
                                <input type="hidden" name="deletar" value="<?= $a['id'] ?>">
                                <button class="btn-delete" type="submit">Excluir</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

            </section>

            <!-- ===== TAB SOLICITAÇÕES (index 1) ===== -->
            <section class="tab-page" id="tab-solicitacoes">
                <h1 class="titulo-agenda">Minhas Solicitações</h1>

                <?php if (isset($_GET['success'])): ?>
                    <script>mostrarModal("Solicitação <?= htmlspecialchars($_GET['success']) ?> com sucesso!");</script>
                <?php endif; ?>

                <div class="lista-solicitacoes">
                    <?php while($s = mysqli_fetch_assoc($solicitacoes)): ?>
                        <div class="sol-card">
                            <div class="sol-info">
                                <img src="<?= htmlspecialchars($s['cliente_img']) ?>" class="cliente-img">
                                <div>
                                    <h2><?= htmlspecialchars($s['cliente_nome']) ?></h2>
                                    <p>Status: <strong class="status <?= $s['status'] ?>"><?= htmlspecialchars($s['status']) ?></strong></p>
                                </div>
                            </div>

                            <?php if ($s['status'] === "pendente"): ?>
                                <form method="POST" class="acoes">
                                    <input type="hidden" name="form_type" value="sol_action">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button name="acao" value="aceitar" class="btn aceitar">Aceitar</button>
                                    <button name="acao" value="recusar" class="btn recusar">Recusar</button>
                                </form>
                            <?php endif; ?>

                        </div>
                    <?php endwhile; ?>
                </div>
            </section>

        </div> <!-- .tabs-content -->
    </div> <!-- .tabs-wrapper -->

</div> <!-- .tabs-container -->

<script src="../js/login.js"></script>
<script src="..\js\cookies.js"></script>
<script>
// TABS: translate by 100% per tab (each tab-page = 100%)
document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        this.classList.add("active");

        const tabIndex = this.getAttribute("data-tab");
        document.querySelector(".tabs-content").style.transform = `translateX(-${tabIndex * 100}%)`;
    });
});
</script>
</body>
</html>
