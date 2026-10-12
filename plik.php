<?php
// Bezpieczne pobieranie dokumentu z pokoju danych: sprawdza uprawnienia i zapisuje pobranie.
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
$u = require_login();

$d = one('SELECT d.*, p.published, p.stage, p.owner_user_id FROM project_docs d JOIN projects p ON p.id=d.project_id WHERE d.id=?', [(int)($_GET['id'] ?? 0)]);
$ok = false;
if ($d && $d['stored_name']) {
    if (is_staff()) $ok = true;
    elseif ($u['role'] === 'wlasciciel' && (int)$d['owner_user_id'] === (int)$u['id']) $ok = true;
    elseif ($u['role'] === 'czlonek' && $d['visible'] && $d['published'] && in_array($d['stage'], ['prezentacja', 'realizacja'], true)) $ok = true;
}
$path = $d ? STORAGE_DIR . '/' . basename((string)$d['stored_name']) : '';
if (!$ok || !is_file($path)) { http_response_code(404); exit('Dokument niedostępny.'); }

q('INSERT INTO downloads (doc_id, user_id, ip) VALUES (?,?,?)', [$d['id'], $u['id'], substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);

$name = preg_replace('/[^\p{L}\p{N}\.\-_ ]/u', '_', (string)$d['file_name']) ?: 'dokument';
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
header('Cache-Control: private, no-store');
readfile($path);
