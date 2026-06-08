<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$uid = trim($_GET['id'] ?? '');
if ($uid && $uid !== 'admin') {
    delete_user($uid);
    // 同时清除该用户目录（保留目录本身，只清内容）
    $user_dir = DATA_DIR . DS . $uid;
    // 删除目录下所有文件
    if (is_dir($user_dir)) {
        array_map('unlink', glob("$user_dir/*.*"));
    }
}
redirect('index.php');