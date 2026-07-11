<?php
// download.php ― 投稿に添付されたPDFを渡す。会員（入室済み）だけがアクセスできる。
require __DIR__ . '/lib.php';
boot_session();

$token = $_GET['r'] ?? null;
$room  = is_string($token) ? get_room($token) : null;
if (!$room) { http_response_code(404); exit('見つかりません。'); }

$member = current_member($room);
if (!$member) { http_response_code(403); exit('入室していません。メールのリンクから入室してください。'); }

$postId = (string)($_GET['p'] ?? '');
$post = null;
foreach (get_posts($room['token']) as $pp) { if ($pp['id'] === $postId) { $post = $pp; break; } }
if (!$post || empty($post['pdf'])) { http_response_code(404); exit('見つかりません。'); }

$stored = $post['pdf']['stored'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+\.pdf$/', $stored)) { http_response_code(404); exit('見つかりません。'); }
$path = storage_for($room['token']) . '/' . $stored;
if (!is_file($path)) { http_response_code(404); exit('見つかりません。'); }

$orig = $post['pdf']['orig'] ?: 'file.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="file.pdf"; filename*=UTF-8\'\'' . rawurlencode($orig));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
