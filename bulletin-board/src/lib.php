<?php
// lib.php ― ルーム型掲示板の共通裏方（データIO・トークン・メール・認証・CSRF）
// 非公開ロジック。index.php / admin/index.php / download.php から読み込む。

declare(strict_types=1);
mb_internal_encoding('UTF-8');
mb_language('Japanese');

// ---- 設定読み込み ----
$GLOBALS['__CFG'] = require __DIR__ . '/config.php';
function cfg(string $key, $default = null) {
    return $GLOBALS['__CFG'][$key] ?? $default;
}

// ---- セッション（安全なcookie設定）----
function boot_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('bbsid');
    session_start();
}

// ---- 出力エスケープ ----
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- トークン生成（URLセーフ・総当たり不可の長さ）----
function gen_token(int $bytes = 18): string {
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}
function valid_token($t): bool {
    return is_string($t) && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $t) === 1;
}

// ---- CSRF ----
function csrf_token(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = gen_token(18);
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}
function check_csrf(): void {
    boot_session();
    $ok = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['_csrf']);
    if (!$ok) { http_response_code(400); exit('不正なリクエストです（CSRF）。'); }
}

// ---- JSON データIO（アトミック書き込み）----
function read_json(string $path, $default) {
    if (!file_exists($path)) return $default;
    $s = file_get_contents($path);
    if ($s === false || $s === '') return $default;
    $d = json_decode($s, true);
    return ($d === null) ? $default : $d;
}
function write_json(string $path, $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $path);
}

// ---- パス組み立て ----
function room_dir(string $token): string     { return cfg('data_dir') . '/rooms/' . $token; }
function members_path(string $token): string { return room_dir($token) . '/members.json'; }
function posts_path(string $token): string   { return room_dir($token) . '/posts.json'; }
function storage_for(string $token): string  { return cfg('storage_dir') . '/' . $token; }

// ---- URL組み立て（pretty / query 両対応）----
function base_url(): string { return rtrim((string)cfg('base_url'), '/'); }
function room_url(string $token, ?string $k = null): string {
    if (cfg('pretty_url')) {
        $u = base_url() . '/r/' . rawurlencode($token);
        if ($k !== null) $u .= '?k=' . rawurlencode($k);
    } else {
        $u = base_url() . '/index.php?r=' . rawurlencode($token);
        if ($k !== null) $u .= '&k=' . rawurlencode($k);
    }
    return $u;
}
function admin_url(): string { return base_url() . '/admin/'; }

// ---- ルーム ----
function rooms_index_path(): string { return cfg('data_dir') . '/rooms.json'; }
function all_rooms(): array { return read_json(rooms_index_path(), []); }
function get_room($token): ?array {
    if (!valid_token($token)) return null;
    $rooms = all_rooms();
    return $rooms[$token] ?? null;
}
function create_room(string $name): array {
    $token = gen_token(12); // ~16文字のURLセーフ
    $rooms = all_rooms();
    $rooms[$token] = ['token' => $token, 'name' => $name, 'created_at' => time()];
    write_json(rooms_index_path(), $rooms);
    // 空のメンバー・投稿ファイルを用意
    write_json(members_path($token), []);
    write_json(posts_path($token), []);
    // 原さん（オーナー）を承認済み会員として自動追加（表示名＝ブランド名）
    add_member($token, (string)cfg('owner_email'), (string)cfg('brand_name'), 'approved', 'owner');
    return $rooms[$token];
}

// ルームを破棄（一覧から除去＋データ・投稿・アップPDFを完全削除）。取り消し不可。
function delete_room($token): bool {
    if (!valid_token($token)) return false;
    $rooms = all_rooms();
    if (!isset($rooms[$token])) return false;
    unset($rooms[$token]);
    write_json(rooms_index_path(), $rooms);
    rrmdir(room_dir($token));    // data/rooms/<token>（members.json・posts.json）
    rrmdir(storage_for($token)); // storage/<token>（アップされたPDF）
    return true;
}
// 再帰的にディレクトリを削除（既知の data/ storage 配下のみを渡す）。
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

// ---- 会員 ----
function get_members(string $token): array { return read_json(members_path($token), []); }
function save_members(string $token, array $m): void { write_json(members_path($token), $m); }
function count_approved(string $token): int {
    return count(array_filter(get_members($token), fn($m) => ($m['status'] ?? '') === 'approved'));
}
function find_member_by_email(string $token, string $email): ?array {
    foreach (get_members($token) as $m) if (strcasecmp($m['email'], $email) === 0) return $m;
    return null;
}
function find_member_by_id(string $token, string $id): ?array {
    foreach (get_members($token) as $m) if ($m['id'] === $id) return $m;
    return null;
}
function find_member_by_ptoken(string $token, string $k): ?array {
    foreach (get_members($token) as $m) if (hash_equals($m['ptoken'], $k)) return $m;
    return null;
}
function add_member(string $token, string $email, string $name = '', string $status = 'pending', string $role = 'guest'): array {
    $members = get_members($token);
    $m = [
        'id'         => gen_token(6),
        'email'      => $email,
        'name'       => $name,   // 表示名（管理画面・板でメールの代わりに出す。メールは連絡用に裏で保持）
        'ptoken'     => gen_token(18),
        'status'     => $status,
        'role'       => $role,
        'created_at' => time(),
        'approved_at'=> $status === 'approved' ? time() : null,
    ];
    $members[] = $m;
    save_members($token, $members);
    return $m;
}
function approve_member(string $token, string $memberId): ?array {
    $members = get_members($token);
    foreach ($members as &$m) {
        if ($m['id'] === $memberId && $m['status'] !== 'approved') {
            $m['status'] = 'approved';
            $m['approved_at'] = time();
            save_members($token, $members);
            return $m;
        }
    }
    return null;
}

// ---- 投稿 ----
function get_posts(string $token): array { return read_json(posts_path($token), []); }
function add_post(string $token, array $member, string $body, ?array $pdf): array {
    $posts = get_posts($token);
    $p = [
        'id'          => gen_token(6),
        'member_id'   => $member['id'],
        'author_email'=> $member['email'],
        'author_name' => (string)($member['name'] ?? ''),
        'author_role' => $member['role'] ?? 'guest',
        'body'        => $body,
        'pdf'         => $pdf, // ['stored'=>..,'orig'=>..,'size'=>..] または null
        'created_at'  => time(),
    ];
    $posts[] = $p;
    write_json(posts_path($token), $posts);
    return $p;
}

// ---- メール送信（本番=mb_send_mail／ローカル=ログファイルに書くだけ）----
function send_mail(string $to, string $subject, string $body): bool {
    $log = cfg('dev_mail_log');
    if ($log) { // 開発モード：実送信せずファイルに残す（マジックリンク確認用）
        $line = str_repeat('=', 60) . "\n"
              . '[' . date('Y-m-d H:i:s') . "] TO: $to\n"
              . "SUBJECT: $subject\n\n$body\n\n";
        file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
        return true;
    }
    $from = (string)cfg('mail_from');
    $headers = 'From: ' . mb_encode_mimeheader((string)cfg('brand_name')) . " <$from>\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    return mb_send_mail($to, $subject, $body, $headers, "-f$from");
}

// ---- 認証：管理者（原さん）----
function is_admin(): bool { boot_session(); return !empty($_SESSION['admin']); }
function require_admin(): void { if (!is_admin()) { header('Location: ' . admin_url()); exit; } }

// ---- 認証：会員（ルームごとのセッション）----
function current_member(array $room): ?array {
    boot_session();
    $mid = $_SESSION['member'][$room['token']] ?? null;
    if (!$mid) return null;
    $m = find_member_by_id($room['token'], $mid);
    return ($m && $m['status'] === 'approved') ? $m : null;
}
function login_member(array $room, array $member): void {
    boot_session();
    $_SESSION['member'][$room['token']] = $member['id'];
}

// ---- 表示補助 ----
function mask_email(string $e): string {
    $at = strpos($e, '@');
    if ($at === false) return $e;
    $u = substr($e, 0, $at); $d = substr($e, $at);
    $keep = mb_substr($u, 0, 2);
    return $keep . str_repeat('*', max(1, mb_strlen($u) - 2)) . $d;
}
// 会員の表示名（メールの代わりに画面へ出す）。オーナー＝ブランド名／ゲスト＝登録名／未登録の旧データは「お客様」。
function display_name(array $m): string {
    if (($m['role'] ?? '') === 'owner') return (string)cfg('brand_name');
    $n = trim((string)($m['name'] ?? ''));
    return $n !== '' ? $n : 'お客様';
}
function author_label(array $post): string {
    if (($post['author_role'] ?? '') === 'owner') return (string)cfg('brand_name');
    $n = trim((string)($post['author_name'] ?? ''));
    if ($n !== '') return $n;
    // 旧データ（表示名なし）はメールを伏せて表示
    return 'お客様（' . mask_email((string)($post['author_email'] ?? '')) . '）';
}

// ---- 通知メール（組み立て）----
function send_approval_request(array $room, array $member): void {
    $subject = '【' . cfg('brand_name') . '掲示板】参加申請：' . $room['name'];
    $who = trim((string)($member['name'] ?? ''));
    $who = $who !== '' ? $who . '（' . $member['email'] . '）' : $member['email'];
    $body = $who . " さんが掲示板「" . $room['name'] . "」への参加を申請しました。\n\n"
          . "承認する場合は管理画面から「許可」を押してください：\n" . admin_url() . "\n";
    send_mail((string)cfg('owner_email'), $subject, $body);
}
function send_magic_link(array $room, array $member): void {
    $url = room_url($room['token'], $member['ptoken']);
    $subject = '【' . cfg('brand_name') . '】掲示板「' . $room['name'] . '」への入室リンク';
    $body = "掲示板「" . $room['name'] . "」にご招待します。\n\n"
          . "下記リンクをこの端末で開くと入室できます（パスワードは不要です）。\n"
          . "このリンクはあなた専用です。他の人に転送しないでください。\n\n"
          . $url . "\n";
    send_mail($member['email'], $subject, $body);
}
function notify_new_post(array $room, array $author, array $post): void {
    $subject = '【' . cfg('brand_name') . '】掲示板「' . $room['name'] . '」に新しい投稿';
    $preview = trim(mb_strimwidth($post['body'] ?? '', 0, 60, '…'));
    foreach (get_members($room['token']) as $m) {
        if (($m['status'] ?? '') !== 'approved') continue;
        if ($m['id'] === $author['id']) continue; // 投稿者本人には送らない
        $url = room_url($room['token'], $m['ptoken']);
        $body = "掲示板「" . $room['name'] . "」に新しい投稿がありました。\n\n"
              . (!empty($post['pdf']) ? "（PDF添付あり）\n" : "")
              . ($preview !== '' ? "「" . $preview . "」\n\n" : "\n")
              . "確認はこちら（あなた専用リンク）：\n" . $url . "\n";
        send_mail($m['email'], $subject, $body);
    }
}
