<?php
// Simple Open Graph / basic metadata fetcher for link previews.
// Usage: ../php/linkPreview.php?url=https://example.com
// Returns: { ok:true, url:"...", title:"...", description:"...", image:"..." }
// Security: Only allows http/https, timeout & size limits.

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
    out(['ok'=>false,'erro'=>'URL inválida'],400);
}
// Basic sanitize (prevent CRLF injection)
$url = preg_replace('/[\r\n]+/','', $url);

// Fetch with curl
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (PreviewBot) PHP-LinkPreview/1.0',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$html = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($html === false || $html === '' || $err) {
    out(['ok'=>false,'erro'=>'Falha ao buscar','detalhe'=>$err],502);
}
// Limit parse length to avoid huge memory (first 150KB)
$html = substr($html, 0, 153600);

// Extract head section quickly
$head = '';
if (preg_match('/<head[^>]*>([\s\S]*?)<\/head>/i', $html, $m)) {
    $head = $m[1];
} else {
    $head = $html; // fallback
}

$title = '';
$description = '';
$image = '';

// Title priority: og:title then <title>
if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i', $head, $m)) {
    $title = trim($m[1]);
} elseif (preg_match('/<title>([^<]{1,200})<\/title>/i', $head, $m)) {
    $title = trim($m[1]);
}
// Description: og:description or name=description
if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\']/i', $head, $m)) {
    $description = trim($m[1]);
} elseif (preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']+)["\']/i', $head, $m)) {
    $description = trim($m[1]);
}
// Image: og:image
if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $head, $m)) {
    $image = trim($m[1]);
}
// Normalize image absolute
if ($image !== '' && !preg_match('/^https?:\/\//i', $image)) {
    // Build absolute from base URL
    $parsed = parse_url($url);
    if ($parsed && isset($parsed['scheme'],$parsed['host'])) {
        $scheme = $parsed['scheme']; $host = $parsed['host'];
        $base = $scheme.'://'.$host.(isset($parsed['port'])?' :'.$parsed['port']:'');
        if (strpos($image,'/')===0) $image = $base.$image; else $image = $base.'/'.ltrim($image,'/');
    }
}

out([
  'ok'=>true,
  'url'=>$url,
  'title'=>$title,
  'description'=>$description,
  'image'=>$image
]);
