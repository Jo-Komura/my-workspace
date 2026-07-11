<?php
// admin/index.php ― 管理画面（原さん専用）。管理パスでログイン。
// ルーム作成／参加申請の承認（最大5名）を行う。
require __DIR__ . '/../lib.php';
boot_session();

$msg = null; $err = null; $created = null;

// ---- ログアウト ----
if (isset($_GET['logout'])) { $_SESSION['admin'] = false; header('Location: ' . admin_url()); exit; }

// ---- ログイン ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    check_csrf();
    $pass = (string)($_POST['password'] ?? '');
    if (password_verify($pass, (string)cfg('admin_pass_hash'))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: ' . admin_url()); exit;
    } else {
        $err = 'パスワードが違います。';
    }
}

// ---- 以降は管理者のみ ----
if (is_admin()) {
    // ルーム作成
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
        check_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { $err = 'ルーム名を入れてください。'; }
        else {
            $room = create_room(mb_substr($name, 0, 60));
            $created = $room['token'];
            $msg = 'ルーム「' . $name . '」を作成しました。';
        }
    }
    // 参加申請の承認
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
        check_csrf();
        $rt = (string)($_POST['room'] ?? '');
        $mid = (string)($_POST['member'] ?? '');
        $room = get_room($rt);
        if (!$room) { $err = 'ルームが見つかりません。'; }
        elseif (count_approved($rt) >= (int)cfg('max_members')) {
            $err = '定員（' . (int)cfg('max_members') . '名）に達しているため、これ以上承認できません。';
        } else {
            $m = approve_member($rt, $mid);
            if ($m) { send_magic_link($room, $m); $msg = $m['email'] . ' さんを承認し、入室リンクを送信しました。'; }
            else { $err = '承認できませんでした。'; }
        }
    }
}

admin_head();

if ($msg) echo '<div class="ok">' . h($msg) . '</div>';
if ($err) echo '<div class="err">' . h($err) . '</div>';

if (!is_admin()) {
    // ログインフォーム
    echo '<div class="card"><h1>管理ログイン</h1>'
       . '<form method="post">' . csrf_field()
       . '<input type="hidden" name="action" value="login">'
       . '<label>管理パスワード</label>'
       . '<input type="password" name="password" autofocus required>'
       . '<p style="margin-top:16px"><button class="btn" type="submit">ログイン</button></p>'
       . '</form></div>';
    admin_foot();
    exit;
}

// 作成直後：ルームURLと原さんの入室リンクを表示
if ($created) {
    $owner = find_member_by_email($created, (string)cfg('owner_email'));
    echo '<div class="card"><h2>お客様に渡すURL</h2>'
       . '<p class="muted">紙で渡す／スマホで入力してもらう用。相手はここで参加申請します。</p>'
       . '<span class="mono">' . h(room_url($created)) . '</span>'
       . '<div class="qr" data-url="' . h(room_url($created)) . '"></div>'
       . '<p class="muted qr-cap">スマホのカメラでこのQRを読み取ってもらえば、URLの入力は不要です。</p>';
    if ($owner) {
        echo '<h2>あなた（' . h((string)cfg('brand_name')) . '）の入室リンク</h2>'
           . '<p class="muted">この専用リンクからあなたも部屋に入って投稿できます。</p>'
           . '<span class="mono">' . h(room_url($created, $owner['ptoken'])) . '</span>'
           . '<p style="margin-top:12px"><a class="btn btn-gold btn-sm" href="' . h(room_url($created, $owner['ptoken'])) . '">部屋に入る</a></p>';
    }
    echo '</div>';
}

// ルーム作成フォーム
echo '<div class="card"><h1>ルームを作成</h1>'
   . '<form method="post">' . csrf_field()
   . '<input type="hidden" name="action" value="create">'
   . '<label>ルーム名（例：〇〇様 物件のご相談）</label>'
   . '<input type="text" name="name" maxlength="60" required>'
   . '<p style="margin-top:16px"><button class="btn" type="submit">作成する</button></p>'
   . '</form></div>';

// 既存ルーム一覧
$rooms = all_rooms();
if ($rooms) {
    echo '<div class="card"><h1>ルーム一覧</h1>';
    // 新しい順
    uasort($rooms, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
    foreach ($rooms as $r) {
        $t = $r['token'];
        $members = get_members($t);
        $approved = count_approved($t);
        // 案件名クリックで、原さん（オーナー）として部屋を新窓で開く
        $ownerLink = room_url($t);
        foreach ($members as $mm) { if (($mm['role'] ?? '') === 'owner') { $ownerLink = room_url($t, $mm['ptoken']); break; } }
        echo '<h2><a class="roomlink" href="' . h($ownerLink) . '" target="_blank" rel="noopener">' . h($r['name']) . '</a> <span class="muted">（承認 ' . $approved . '/' . (int)cfg('max_members') . '名）</span></h2>';
        echo '<div class="url-row"><span class="mono">' . h(room_url($t)) . '</span>'
           . '<button type="button" class="btn btn-sm qr-btn" data-qr-toggle="qr-' . h($t) . '">QRを表示</button></div>';
        echo '<div class="qr" id="qr-' . h($t) . '" data-url="' . h(room_url($t)) . '" data-lazy></div>';
        foreach ($members as $m) {
            $status = $m['status'] ?? 'pending';
            $role = $m['role'] ?? 'guest';
            echo '<div class="member-row"><span>' . h($m['email']);
            if ($role === 'owner')      echo ' <span class="tag tag-owner">オーナー</span>';
            elseif ($status === 'approved') echo ' <span class="tag tag-approved">承認済み</span>';
            else                        echo ' <span class="tag tag-pending">承認待ち</span>';
            echo '</span>';
            if ($status !== 'approved') {
                echo '<form method="post" style="margin:0">' . csrf_field()
                   . '<input type="hidden" name="action" value="approve">'
                   . '<input type="hidden" name="room" value="' . h($t) . '">'
                   . '<input type="hidden" name="member" value="' . h($m['id']) . '">'
                   . '<button class="btn btn-gold btn-sm" type="submit">許可</button></form>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
}

admin_foot();

// ---- レイアウト ----
function admin_head(): void {
    ?><!doctype html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>管理 ｜ <?= h((string)cfg('brand_name')) ?>掲示板</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Shippori+Mincho+B1:wght@500;600&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(base_url()) ?>/style.css">
<!-- QRコード（自前生成・端末内で完結／秘密URLは外部に出さない） -->
<script defer src="<?= h(base_url()) ?>/qr.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function draw(el) {
    if (el.dataset.qrDone) return;
    var url = el.getAttribute('data-url');
    if (!url || typeof qrcode === 'undefined') return;
    var qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true });
    el.dataset.qrDone = '1';
  }
  // 最初から見えているQR（作成直後カード）は即描画
  document.querySelectorAll('.qr[data-url]:not([data-lazy])').forEach(draw);
  // 一覧の「QRを表示」トグル（初回オープン時だけ遅延描画）
  document.querySelectorAll('[data-qr-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = document.getElementById(btn.getAttribute('data-qr-toggle'));
      if (!box) return;
      var showing = box.classList.toggle('open');
      if (showing) draw(box);
      btn.textContent = showing ? 'QRを隠す' : 'QRを表示';
    });
  });
});
</script>
</head><body>
<header class="site-head"><div class="inner">
  <span class="brand"><?= h((string)cfg('brand_name')) ?></span>
  <span class="room-name">掲示板 管理</span>
  <?php if (is_admin()): ?><span class="tagline"><a href="?logout=1" style="color:rgba(255,255,255,.6)">ログアウト</a></span><?php endif; ?>
</div></header>
<div class="wrap">
<?php
}
function admin_foot(): void { echo '</div></body></html>'; }
