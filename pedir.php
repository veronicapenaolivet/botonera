<?php
/*
 * Formulario "Pedir un audio" de la botonera.
 * Recibe el pedido, guarda el archivo (si hay) FUERA de la carpeta pública
 * y manda un mail a DESTINO con todo ordenado y el audio adjunto.
 */

const DESTINO      = 'botonera@veronicapenaolivet.com';
const REMITENTE    = 'botonera@veronicapenaolivet.com';   // mismo dominio: llega mejor
const MAX_BYTES    = 15 * 1024 * 1024;                     // 15 MB por archivo
const MAX_ADJUNTO  = 10 * 1024 * 1024;                     // más grande que esto no se adjunta (queda guardado)
const MAX_POR_HORA = 20;                                   // pedidos por hora desde una misma IP (la oficina comparte IP)
const EXTENSIONES  = ['mp3', 'm4a', 'ogg', 'oga', 'opus', 'wav', 'aac', 'amr', 'flac', 'mp4', 'mov', 'webm', '3gp'];

date_default_timezone_set('America/Montevideo');   // el servidor de Hostinger está en otra zona horaria

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function responder(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function texto(string $k, int $max): string {
    $v = trim((string)($_POST[$k] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
function una_linea(string $v): string { return trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

// Si el archivo supera post_max_size, PHP vacía $_POST y $_FILES.
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    responder(413, ['ok' => false, 'error' => 'El archivo es demasiado grande (máximo 15 MB).']);
}

// Antispam: campo trampa invisible y tiempo mínimo para completar el formulario.
if (texto('web', 200) !== '') {
    responder(200, ['ok' => true]);   // a los bots les decimos que sí y no hacemos nada
}
$dt = (int)($_POST['dt'] ?? 0);   // milisegundos que pasaron desde que se abrió el formulario
if ($dt < 2500) {
    responder(200, ['ok' => true]);
}

$que    = texto('que', 500);
$link   = una_linea(texto('link', 500));
$nombre = una_linea(texto('nombre', 80));

if ($que === '') {
    responder(422, ['ok' => false, 'error' => 'Contanos qué audio querés.']);
}
// El link es texto libre: la gente suele agregar "minuto 1:20" o similar.

// Carpeta de pedidos: fuera de public_html si se puede.
$dir = dirname(__DIR__, 2) . '/botonera_pedidos';
if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
    $dir = __DIR__ . '/pedidos';
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
}
if (!is_dir($dir) || !is_writable($dir)) {
    responder(500, ['ok' => false, 'error' => 'No se pudo guardar el pedido. Probá de nuevo más tarde.']);
}

// Límite de pedidos por IP.
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
$limFile = $dir . '/.limite.json';
$lim     = is_file($limFile) ? (json_decode((string)file_get_contents($limFile), true) ?: []) : [];
$ahora   = time();
foreach ($lim as $k => $ts) { $lim[$k] = array_values(array_filter($ts, fn($x) => $x > $ahora - 3600)); if (!$lim[$k]) unset($lim[$k]); }
$clave = hash('sha256', $ip);
if (count($lim[$clave] ?? []) >= MAX_POR_HORA) {
    responder(429, ['ok' => false, 'error' => 'Mandaste muchos pedidos seguidos. Probá en un rato.']);
}

// Archivo (opcional).
$guardado = null; $original = null; $tamano = 0; $mime = null;
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['archivo'];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAX_BYTES) {
        responder(413, ['ok' => false, 'error' => 'El archivo es demasiado grande (máximo 15 MB).']);
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        responder(400, ['ok' => false, 'error' => 'No se pudo subir el archivo. Probá de nuevo.']);
    }
    $original = una_linea(mb_substr(basename((string)$f['name']), 0, 120));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
    $esMedia = str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/') || $mime === 'application/ogg';
    if (!in_array($ext, EXTENSIONES, true) || !$esMedia) {
        responder(415, ['ok' => false, 'error' => 'El archivo tiene que ser un audio o un video (mp3, m4a, ogg, wav, mp4…).']);
    }
    $guardado = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $guardado)) {
        responder(500, ['ok' => false, 'error' => 'No se pudo guardar el archivo. Probá de nuevo.']);
    }
    @chmod($dir . '/' . $guardado, 0640);
    $tamano = (int)$f['size'];
}

// Registro en CSV (por si algún mail no llega).
$fila = [date('Y-m-d H:i:s'), $nombre, $que, $link, $guardado ?? '', $original ?? ''];
$csv = fopen($dir . '/pedidos.csv', 'a');
if ($csv) {
    if (filesize($dir . '/pedidos.csv') === 0) { fputcsv($csv, ['fecha', 'nombre', 'que', 'link', 'archivo_guardado', 'archivo_original']); }
    fputcsv($csv, array_map(fn($v) => preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $fila));
    fclose($csv);
}
$lim[$clave][] = $ahora;
@file_put_contents($limFile, json_encode($lim));

// Mail.
$asunto = 'Pedido para la botonera: ' . mb_substr(una_linea($que), 0, 60);
$cuerpo = "Nuevo pedido de audio para la botonera\n"
        . "======================================\n\n"
        . "Qué audio: " . $que . "\n"
        . "Link: " . ($link !== '' ? $link : '—') . "\n"
        . "Pidió: " . ($nombre !== '' ? $nombre : 'anónimo') . "\n"
        . "Archivo: " . ($guardado ? "$original (" . ($tamano < 1048576 ? max(1, round($tamano / 1024)) . ' KB' : round($tamano / 1048576, 1) . ' MB') . "), guardado como $guardado en $dir" : '—') . "\n\n"
        . "Fecha: " . date('d/m/Y H:i') . "\n";

$borde   = 'b_' . bin2hex(random_bytes(12));
$headers = [
    'From: Botonera <' . REMITENTE . '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/mixed; boundary="' . $borde . '"',
];
$msg  = "--$borde\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
      . chunk_split(base64_encode($cuerpo)) . "\r\n";
if ($guardado && $tamano <= MAX_ADJUNTO) {
    $nombreAdj = preg_replace('/[^\w.\-]+/u', '_', $original) ?: $guardado;
    $msg .= "--$borde\r\nContent-Type: $mime; name=\"$nombreAdj\"\r\nContent-Transfer-Encoding: base64\r\n"
          . "Content-Disposition: attachment; filename=\"$nombreAdj\"\r\n\r\n"
          . chunk_split(base64_encode((string)file_get_contents($dir . '/' . $guardado))) . "\r\n";
}
$msg .= "--$borde--\r\n";

$enviado = @mail(DESTINO, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $msg, implode("\r\n", $headers), '-f' . REMITENTE);

// El pedido ya quedó guardado; aunque el mail falle, para quien pide salió bien.
responder(200, ['ok' => true, 'mail' => (bool)$enviado]);
