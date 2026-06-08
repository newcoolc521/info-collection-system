<?php
/**
 * 信息采集系统 - 入口
 * PC → 管理登录 | 手机 → 通过 url_key 访问表单
 */
require_once __DIR__ . '/lib.php';

if (is_mobile()) {
    if (isset($_GET['k'])) {
        redirect('form.php?k=' . urlencode($_GET['k']));
    }
    redirect('login.php');
} else {
    redirect('login.php');
}