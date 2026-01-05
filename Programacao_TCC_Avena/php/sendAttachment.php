<?php
// Endpoint para envio de anexos (imagem, vídeo, áudio, documentos)
// Requer colunas adicionais na tabela `mensagem`: tipo VARCHAR(16) DEFAULT 'text', arquivo VARCHAR(255) NULL
// Caso ainda não tenha feito: ALTER TABLE mensagem ADD COLUMN tipo VARCHAR(16) NOT NULL DEFAULT 'text', ADD COLUMN arquivo VARCHAR(255) NULL AFTER tipo;

session_start();
include_once(__DIR__ . '/conexao.php');
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexao->set_charset('utf8mb4');

function resolveUsuarioAtual() {
    $tipo = $_SESSION['tipo'] ?? null;

    $idCli = 0;
    if (!empty($_SESSION['id_cliente'])) {
        $idCli = (int) $_SESSION['id_cliente'];
    } elseif (!empty($_SESSION['cliente']['id_usuario'])) {
        $idCli = (int) $_SESSION['cliente']['id_usuario'];
    } elseif (!empty($_SESSION['cliente']['id_cliente'])) {
        $idCli = (int) $_SESSION['cliente']['id_cliente'];
    }

    $idPrest = 0;
    if (!empty($_SESSION['id_prestadora'])) {
        $idPrest = (int) $_SESSION['id_prestadora'];
    } elseif (!empty($_SESSION['prestadora']['id_usuario'])) {
        $idPrest = (int) $_SESSION['prestadora']['id_usuario'];
    } elseif (!empty($_SESSION['prestadora']['id_prestadora'])) {
        $idPrest = (int) $_SESSION['prestadora']['id_prestadora'];
    }

    if ($tipo === 'cliente' && $idCli > 0) return ['role'=>'cliente','id_cliente'=>$idCli,'id_prestadora'=>0];
    if ($tipo === 'profissional' && $idPrest > 0) return ['role'=>'prestadora','id_cliente'=>0,'id_prestadora'=>$idPrest];
    if ($idCli > 0 && $idPrest > 0) return ['role'=>'cliente','id_cliente'=>$idCli,'id_prestadora'=>0];
    if ($idCli > 0) return ['role'=>'cliente','id_cliente'=>$idCli,'id_prestadora'=>0];
    if ($idPrest > 0) return ['role'=>'prestadora','id_cliente'=>0,'id_prestadora'=>$idPrest];
    return ['role'=>null,'id_cliente'=>0,'id_prestadora'=>0];
}

function jsonOut($arr, $status=200){ http_response_code($status); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

try {
    $usuario = resolveUsuarioAtual();
    if (!$usuario['role']) jsonOut(['ok'=>false,'erro'=>'Usuário não autenticado'],401);

    $id_para = isset($_POST['id_para']) ? (int)$_POST['id_para'] : 0;
    $texto   = trim((string)($_POST['texto'] ?? ''));
    if ($id_para <= 0) jsonOut(['ok'=>false,'erro'=>'id_para inválido'],400);
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) jsonOut(['ok'=>false,'erro'=>'Arquivo ausente ou erro no upload'],400);

    $file = $_FILES['arquivo'];
    $size = (int)$file['size'];
    $tmp  = $file['tmp_name'];
    $orig = $file['name'];
    if ($size <= 0) jsonOut(['ok'=>false,'erro'=>'Arquivo vazio'],400);
    $maxBytes = 15 * 1024 * 1024; // 15MB
    if ($size > $maxBytes) jsonOut(['ok'=>false,'erro'=>'Arquivo excede limite de 15MB'],400);

    // Whitelist MIME (fallback se fileinfo indisponível)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo) finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp) ?: '';
    } else {
        // Fallback por extensão
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $map = [
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
            'mp4'=>'video/mp4','webm'=>'video/webm','ogv'=>'video/ogg',
            'mp3'=>'audio/mpeg','wav'=>'audio/wav','oga'=>'audio/ogg','ogg'=>'audio/ogg',
            'pdf'=>'application/pdf','txt'=>'text/plain','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        $mime = $map[$ext] ?? '';
    }

    $allowed = [
        // imagens
        'image/jpeg'=>'image','image/png'=>'image','image/gif'=>'image','image/webp'=>'image',
        // vídeo
        'video/mp4'=>'video','video/webm'=>'video','video/ogg'=>'video',
        // áudio
        'audio/mpeg'=>'audio','audio/ogg'=>'audio','audio/wav'=>'audio',
        // documentos
        'application/pdf'=>'file','text/plain'=>'file',
        'application/msword'=>'file','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'file',
        'application/vnd.ms-excel'=>'file','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'file',
        'application/vnd.ms-powerpoint'=>'file','application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'file'
    ];

    if (!isset($allowed[$mime])) {
        // Bloqueia tipos obscuros 'video/x-*' 'audio/x-*'
        if (preg_match('/^(video|audio)\/x-/', $mime)) {
            jsonOut(['ok'=>false,'erro'=>'Tipo de mídia não suportado (x-*)'],400);
        }
        jsonOut(['ok'=>false,'erro'=>'MIME não permitido: '.$mime],400);
    }
    $tipoArmazenado = $allowed[$mime];

    // Descobrir usuário destino (similar a sendMessage.php)
    if ($usuario['role'] === 'cliente') {
        $id_de = (int)$usuario['id_cliente'];
        $id_para_user = $id_para; // prestadora
        $id_cliente = $id_de; $id_prestadora = (int)$id_para_user;
        $chk = $conexao->prepare('SELECT 1 FROM prestadora WHERE id_usuario = ? LIMIT 1');
        $chk->bind_param('i',$id_para_user); $chk->execute(); $r = $chk->get_result(); $chk->close();
        if (!$r || !$r->num_rows) jsonOut(['ok'=>false,'erro'=>'Prestadora não encontrada'],400);
    } else { // prestadora
        $id_de = (int)$usuario['id_prestadora'];
        $id_para_user = $id_para; // cliente
        $id_prestadora = $id_de; $id_cliente = (int)$id_para_user;
        $chk = $conexao->prepare('SELECT 1 FROM cliente WHERE id_usuario = ? LIMIT 1');
        $chk->bind_param('i',$id_para_user); $chk->execute(); $r = $chk->get_result(); $chk->close();
        if (!$r || !$r->num_rows) jsonOut(['ok'=>false,'erro'=>'Cliente não encontrado'],400);
    }

    // Encontrar ou criar chat
    $stmt = $conexao->prepare('SELECT id_chat FROM chat WHERE id_cliente = ? AND id_prestadora = ? LIMIT 1');
    $stmt->bind_param('ii', $id_cliente, $id_prestadora); $stmt->execute(); $res = $stmt->get_result();
    if ($res && $res->num_rows) { $id_chat = (int)$res->fetch_assoc()['id_chat']; $stmt->close(); }
    else {
        $stmt->close();
        $ins = $conexao->prepare('INSERT INTO chat (id_cliente, id_prestadora, criado_em) VALUES (?, ?, NOW())');
        $ins->bind_param('ii',$id_cliente,$id_prestadora); $ins->execute(); $id_chat = (int)$conexao->insert_id; $ins->close();
    }

    // Sanitiza nome e gera caminho
    $safeBase = preg_replace('/[^A-Za-z0-9._-]/','_', $orig);
    $unique = time().'_'.bin2hex(random_bytes(4)).'_'.$safeBase;
    $relDir = '../uploads/messages'; // relativo ao script que insere (mantém convenção existente)
    $absDir = realpath(__DIR__.'/../uploads/messages');
    if (!$absDir) { // tenta criar
        $try = __DIR__.'/../uploads/messages';
        if (!is_dir($try)) @mkdir($try,0775,true);
        $absDir = realpath($try);
    }
    if (!$absDir) jsonOut(['ok'=>false,'erro'=>'Diretório de upload indisponível'],500);
    $destAbs = $absDir.DIRECTORY_SEPARATOR.$unique;
    // Caminho público relativo usado pelo frontend (acessível a partir de páginas que incluem chat.js)
    $publicBase = '../uploads/messages/';
    $destRel = $publicBase.$unique;

    if (!move_uploaded_file($tmp, $destAbs)) jsonOut(['ok'=>false,'erro'=>'Falha ao mover arquivo'],500);

    // Descobre colunas existentes para construir INSERT dinâmico (evita erro 1054 se migração parcial)
    $hasLido = false; $hasTipo = false; $hasArquivo = false;
    try {
        $c1 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'lido'"); if ($c1 && $c1->num_rows) $hasLido = true;
        $c2 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'tipo'"); if ($c2 && $c2->num_rows) $hasTipo = true;
        $c3 = $conexao->query("SHOW COLUMNS FROM mensagem LIKE 'arquivo'"); if ($c3 && $c3->num_rows) $hasArquivo = true;
    } catch (Exception $e) { /* ignora */ }

    $fields = ['id_chat','id_de','id_para','conteudo','enviado_em'];
    $values = ['?','?','?','?','NOW()'];
    $types = 'iiis'; // para id_chat, id_de, id_para, conteudo
    $params = [$id_chat, $id_de, $id_para_user, ($texto !== '' ? $texto : '')];

    if ($hasLido) { $fields[] = 'lido'; $values[] = '0'; }
    // Se não existir coluna tipo/arquivo vamos embutir um marcador especial no conteudo
    $marker = "[[ATTACH:type=$tipoArmazenado;file=$destRel]]";
    if ($hasTipo) { $fields[] = 'tipo'; $values[] = '?'; $types .= 's'; $params[] = $tipoArmazenado; }
    if ($hasArquivo) { $fields[] = 'arquivo'; $values[] = '?'; $types .= 's'; $params[] = $destRel; }
    else {
        // injeta marcador no próprio conteudo para ser parseado depois
        $params[3] = ($params[3] !== '' ? $params[3]."\n" : '') . $marker; // index 3 é conteudo
    }

    $sqlIns = 'INSERT INTO mensagem (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
    $ins2 = $conexao->prepare($sqlIns);
    if (!$ins2) jsonOut(['ok'=>false,'erro'=>'Falha preparar INSERT','sql'=>$sqlIns],500);

    // bind dinâmico
    $bindParams = [];
    $bindParams[] = & $types;
    foreach ($params as $k=> $v) { $bindParams[] = & $params[$k]; }
    call_user_func_array([$ins2, 'bind_param'], $bindParams);
    $ins2->execute();
    $id_mensagem = (int)$conexao->insert_id;
    $ins2->close();

    $row = $conexao->query('SELECT enviado_em FROM mensagem WHERE id_mensagem = '.$id_mensagem.' LIMIT 1')->fetch_assoc();
    $enviado_em = $row['enviado_em'] ?? null;

    // Sempre retorna tipo/arquivo para render imediato no frontend, mesmo se colunas não existirem
    // Em caso de migração incompleta, envia também um fallback_marker que poderá ser usado para reconstrução
    jsonOut([
        'ok' => true,
        'id_mensagem' => $id_mensagem,
        'enviado_em' => $enviado_em,
        'tipo' => $tipoArmazenado,
        'arquivo' => $destRel,
        'texto' => ($texto !== '' ? $texto : ''),
        'caption' => ($texto !== '' ? $texto : ''),
        'mime' => $mime,
        'tamanho' => $size,
        'fallback_marker' => (!$hasTipo || !$hasArquivo) ? $marker : null,
        'colunas' => ['lido' => $hasLido, 'tipo' => $hasTipo, 'arquivo' => $hasArquivo]
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('sendAttachment mysqli: '.$e->getMessage());
    jsonOut(['ok'=>false,'erro'=>'Erro banco','detalhe'=>$e->getMessage()],500);
} catch (Exception $e) {
    error_log('sendAttachment: '.$e->getMessage());
    jsonOut(['ok'=>false,'erro'=>$e->getMessage()],500);
}
?>