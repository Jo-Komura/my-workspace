<?php
// config.production.php ― 本番(board.neroandjoe.com)用の設定。
// デプロイ時にこれを config.php として配置する。
// __SET_ON_DEPLOY__ の2つ（管理パスのハッシュ・オーナーのメール）は設置時に差し込む。
// ※オーナーの実アドレスは他人の個人情報なので、gitに置かない（config.phpは.gitignore）。
return [
    'admin_pass_hash' => '__SET_ON_DEPLOY__', // tools/make-admin-hash.php で生成して差し込む
    'base_url'  => 'https://board.neroandjoe.com',
    'pretty_url'=> true,
    'mail_from'  => 'noreply@neroandjoe.com',
    'owner_email'=> '__SET_ON_DEPLOY__',
    'brand_name' => 'ランドデザイン',
    'max_members' => 5,
    'max_pdf_bytes' => 15 * 1024 * 1024,
    'data_dir'    => __DIR__ . '/data',
    'storage_dir' => __DIR__ . '/storage',
    'dev_mail_log' => null, // 本番は実送信
];
