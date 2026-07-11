<?php
// index.php ― ルーム入口。?r=<ルームトークン> で部屋を開く。
// ?k=<会員トークン> が付いていればマジックリンク入場。
require __DIR__ . '/lib.php';
boot_session();

$err = null; $applied = null;

$token = $_GET['r'] ?? null;
$room  = (is_string($token)) ? get_room($token) : null;

// ---- ルーム未指定 or 存在しない ----
if (!$room) {
    render_head('掲示板', null);
    echo '<div class="card"><h1>ご案内された掲示板はこちら</h1>'
       . '<p class="lead">お手元に届いた専用URLからアクセスしてください。'
       . 'URLをスマホで直接入力された場合は、入力内容にお間違いがないかご確認ください。</p></div>';
    render_foot();
    exit;
}

// ---- マジックリンク入場 ----
if (isset($_GET['k']) && is_string($_GET['k'])) {
    $m = find_member_by_ptoken($room['token'], $_GET['k']);
    if ($m && $m['status'] === 'approved') login_member($room, $m);
    header('Location: ' . room_url($room['token'])); // k を落として綺麗なURLへ
    exit;
}

$member = current_member($room);

// ---- 投稿処理（会員のみ）----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post') {
    check_csrf();
    if (!$member) { http_response_code(403); exit('入室していません。'); }
    $body = trim((string)($_POST['body'] ?? ''));
    $pdf  = handle_pdf_upload($room['token'], $err);
    if ($err === null) {
        if ($body === '' && $pdf === null) {
            $err = '本文かPDFの、どちらかは入れてください。';
        } else {
            $post = add_post($room['token'], $member, $body, $pdf);
            notify_new_post($room, $member, $post);
            header('Location: ' . room_url($room['token']));
            exit;
        }
    }
}

// ---- 参加申請処理（未入室の人）----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    check_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'メールアドレスの形式をご確認ください。';
    } else {
        $existing = find_member_by_email($room['token'], $email);
        if ($existing) {
            if ($existing['status'] === 'approved') {
                send_magic_link($room, $existing);   // 既に承認済み → 入室リンク再送
                $applied = 'resent';
            } else {
                $applied = 'pending';                // 承認待ち
            }
        } else {
            $m = add_member($room['token'], $email, 'pending', 'guest');
            send_approval_request($room, $m);        // 原さんへ承認依頼
            $applied = 'new';
        }
    }
}

// ================= 描画 =================
render_head($room['name'], $room['name']);

if ($err) echo '<div class="err">' . h($err) . '</div>';

if ($member) {
    // ---- 板ビュー（入室済み）----
    $posts = get_posts($room['token']);
    echo '<div class="card">';
    echo '<h1>' . h($room['name']) . '</h1>';
    echo '<p class="muted">あなた：' . h(($member['role'] === 'owner') ? cfg('brand_name') : mask_email($member['email'])) . ' として入室中</p>';
    echo '</div>';

    if (!$posts) {
        echo '<div class="empty">まだ投稿はありません。<br>下のフォームから最初の一言を送ってみましょう。</div>';
    } else {
        foreach ($posts as $p) {
            $isOwner = ($p['author_role'] ?? '') === 'owner';
            echo '<div class="post' . ($isOwner ? ' owner' : '') . '">';
            echo '<div class="meta"><span class="who">' . h(author_label($p)) . '</span>'
               . '<span class="when">' . h(date('n月j日 G:i', $p['created_at'])) . '</span></div>';
            if (trim((string)$p['body']) !== '') {
                echo '<div class="body">' . nl2br(h($p['body'])) . '</div>';
            }
            if (!empty($p['pdf'])) {
                $dl = base_url() . '/download.php?r=' . rawurlencode($room['token']) . '&p=' . rawurlencode($p['id']);
                echo '<a class="pdf-link" href="' . h($dl) . '">📄 ' . h($p['pdf']['orig']) . '</a>';
            }
            echo '</div>';
        }
    }

    // 投稿フォーム
    echo '<div class="card"><h2>投稿する</h2>'
       . '<form method="post" enctype="multipart/form-data">'
       . csrf_field()
       . '<input type="hidden" name="action" value="post">'
       . '<label>メッセージ</label>'
       . '<textarea name="body" placeholder="物件のご案内、内見のご希望など"></textarea>'
       . '<div class="file-row"><label>PDFを添付（任意・物件情報など／' . (int)(cfg('max_pdf_bytes') / 1048576) . 'MBまで）</label>'
       . '<input type="file" name="pdf" accept="application/pdf"></div>'
       . '<p style="margin-top:16px"><button class="btn btn-gold" type="submit">送信</button></p>'
       . '</form></div>';

} else {
    // ---- 未入室：申請フォーム or 状態表示 ----
    if ($applied === 'new') {
        echo '<div class="ok">参加申請を受け付けました。'
           . '担当者が確認し、承認されるとご入力のメールアドレスに<strong>入室用リンク</strong>をお送りします。'
           . 'しばらくお待ちください。</div>';
    } elseif ($applied === 'pending') {
        echo '<div class="notice">このメールアドレスは既に申請済みです。承認までしばらくお待ちください。</div>';
    } elseif ($applied === 'resent') {
        echo '<div class="ok">入室用リンクを再送しました。メールをご確認ください。</div>';
    } else {
        echo '<div class="card">'
           . '<h1>' . h($room['name']) . '</h1>'
           . '<p class="lead">この掲示板は、担当者が承認した方だけが閲覧・投稿できる非公開の場です。'
           . 'ご参加には、下記にメールアドレスをご登録ください。</p>'
           . '<form method="post">'
           . csrf_field()
           . '<input type="hidden" name="action" value="apply">'
           . '<label>メールアドレス</label>'
           . '<input type="email" name="email" placeholder="you@example.com" required>'
           . '<p class="muted" style="margin-top:8px">承認後、このアドレス宛に「あなた専用の入室リンク」が届きます。リンクをタップするだけで入室でき、パスワードは不要です。</p>'
           . '<p style="margin-top:16px"><button class="btn" type="submit">参加を申請する</button></p>'
           . '</form></div>';
    }
}

render_foot();

// ================= PDFアップロード処理 =================
function handle_pdf_upload(string $token, ?string &$err): ?array {
    if (empty($_FILES['pdf']) || ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES['pdf'];
    if ($f['error'] !== UPLOAD_ERR_OK) { $err = 'PDFのアップロードに失敗しました。'; return null; }
    if ($f['size'] > (int)cfg('max_pdf_bytes')) {
        $err = 'PDFが大きすぎます（上限' . (int)(cfg('max_pdf_bytes') / 1048576) . 'MB）。'; return null;
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    if ($mime !== 'application/pdf') { $err = 'PDFファイルのみアップロードできます。'; return null; }
    $dir = storage_for($token);
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $stored = gen_token(12) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) { $err = '保存に失敗しました。'; return null; }
    $orig = mb_substr(preg_replace('/[\r\n\/\\\\]/', '', (string)$f['name']), 0, 120);
    if ($orig === '') $orig = 'file.pdf';
    return ['stored' => $stored, 'orig' => $orig, 'size' => (int)$f['size']];
}

// ================= 共通レイアウト =================
function render_head(string $title, ?string $roomName): void {
    ?><!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> ｜ <?= h((string)cfg('brand_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Shippori+Mincho+B1:wght@500;600&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(base_url()) ?>/style.css">
</head>
<body>
<header class="site-head"><div class="inner">
  <span class="brand"><?= h((string)cfg('brand_name')) ?></span>
  <?php if ($roomName !== null): ?><span class="room-name"><?= h($roomName) ?></span><?php endif; ?>
  <span class="tagline">ご相談掲示板</span>
</div></header>
<div class="wrap">
<?php
}
function render_foot(): void {
    echo '<p class="foot">この掲示板は招待された方のみがご利用になれます。</p>';
    echo "</div></body></html>";
}
