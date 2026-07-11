<?php
// config.sample.php ― 設定の見本。これを config.php にコピーして使う。
// config.php は .gitignore で非公開（秘密＝管理パスのハッシュ等を含むため）。
return [
    // 管理者（原さん）のログインパスワードのハッシュ。
    // tools/make-admin-hash.php で生成して貼る（生パスは絶対に保存しない）。
    'admin_pass_hash' => 'ここに password_hash の文字列を貼る',

    // サイトの基点URL（末尾スラッシュなし）。
    'base_url'  => 'https://board.neroandjoe.com',
    'pretty_url'=> true,   // 本番Apache=true（/r/xxx）。ローカル内蔵サーバー=false。

    // メール
    'mail_from'  => 'noreply@neroandjoe.com', // 差出人（SPFがneroandjoe.comで整う）
    'owner_email'=> 'owner@example.com',  // オーナー＝オーナー会員＋参加申請の通知先（設置時に実アドレスへ）
    'brand_name' => 'ランドデザイン',

    // 制限
    'max_members' => 5,                 // 原さん＋4人
    'max_pdf_bytes' => 15 * 1024 * 1024, // 15MB（物件情報PDFは軽い前提）

    // 保存場所（Web直アクセス不可の場所。.htaccessでも二重に拒否）
    'data_dir'    => __DIR__ . '/data',
    'storage_dir' => __DIR__ . '/storage',

    // 開発モード：値を入れるとメールを実送信せずこのファイルに書き出す（本番は null）
    'dev_mail_log' => null,
];
