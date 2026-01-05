<?php
session_start();
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexao->set_charset('utf8mb4');
date_default_timezone_set('America/Sao_Paulo');

function resolveCurrentUser()
{
    $tipo = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : null; // 'cliente' ou 'profissional'
    $idPrest = (int) (isset($_SESSION['id_prestadora']) ? $_SESSION['id_prestadora'] : (isset($_SESSION['prestadora']['id_usuario']) ? $_SESSION['prestadora']['id_usuario'] : 0));
    $idCli   = (int) (isset($_SESSION['id_cliente']) ? $_SESSION['id_cliente'] : (isset($_SESSION['cliente']['id_usuario']) ? $_SESSION['cliente']['id_usuario'] : 0));

    if ($tipo === 'cliente') {
        return ['role' => 'cliente', 'id' => $idCli];
    }
    if ($tipo === 'profissional') {
        return ['role' => 'prestadora', 'id' => $idPrest];
    }
    if ($idCli && !$idPrest) return ['role' => 'cliente', 'id' => $idCli];
    if ($idPrest && !$idCli) return ['role' => 'prestadora', 'id' => $idPrest];
    return ['role' => null, 'id' => 0];
}

try {
    $current = resolveCurrentUser();
    if (!$current['id'] || !$current['role']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Usuário não autenticado']);
        exit;
    }

    $role = $current['role'];
    $userId = $current['id'];

    // Cria tabela de presença se ainda não existir (agora com typing_target)
    $conexao->query("CREATE TABLE IF NOT EXISTS presence (
        user_id INT NOT NULL,
        role ENUM('cliente','prestadora') NOT NULL,
        last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
        typing_until DATETIME DEFAULT NULL,
        typing_target INT NULL,
        PRIMARY KEY(user_id, role),
        KEY(typing_target)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Garante coluna typing_target caso tabela antiga exista
    $col = $conexao->query("SHOW COLUMNS FROM presence LIKE 'typing_target'");
    if ($col->num_rows === 0) {
        $conexao->query("ALTER TABLE presence ADD COLUMN typing_target INT NULL AFTER typing_until, ADD KEY typing_target (typing_target)");
    }

    $typing = isset($_POST['typing']) ? (int) $_POST['typing'] : null; // 1 liga, 0 desliga, null mantém

    $target = isset($_POST['other_id']) ? (int)$_POST['other_id'] : null;
    if ($typing === 1) {
        // estende TTL e salva alvo do chat
        $stmt = $conexao->prepare("INSERT INTO presence (user_id, role, last_active, typing_until, typing_target)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 6 SECOND), ?)
            ON DUPLICATE KEY UPDATE last_active = VALUES(last_active), typing_until = VALUES(typing_until), typing_target = VALUES(typing_target)");
        $stmt->bind_param('isi', $userId, $role, $target);
    } elseif ($typing === 0) {
        // desliga typing mantendo online
        $stmt = $conexao->prepare("INSERT INTO presence (user_id, role, last_active, typing_until, typing_target)
            VALUES (?, ?, NOW(), NULL, NULL)
            ON DUPLICATE KEY UPDATE last_active = VALUES(last_active), typing_until = NULL, typing_target = NULL");
        $stmt->bind_param('is', $userId, $role);
    } else {
        // apenas online ping
        $stmt = $conexao->prepare("INSERT INTO presence (user_id, role, last_active)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_active = VALUES(last_active)");
        $stmt->bind_param('is', $userId, $role);
    }
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    exit;
}
