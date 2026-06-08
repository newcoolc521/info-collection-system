<?php
/**
 * 信息采集系统 - 表单提交API
 */
require_once __DIR__ . '/../lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['code' => 405, 'msg' => '仅支持POST']);
}

$url_key = trim($_POST['k'] ?? '');
if (!$url_key) {
    json_exit(['code' => 400, 'msg' => '缺少表单标识']);
}

// ── 验证码校验 ──
$captcha_key = trim($_POST['captcha_key'] ?? '');
$captcha_answer = trim($_POST['captcha_answer'] ?? '');
if (!verify_captcha($captcha_key, $captcha_answer)) {
    json_exit(['code' => 400, 'msg' => '验证码错误，请重新填写']);
}

// ── 浏览器UUID校验 ──
$uuid = trim($_POST['uuid'] ?? '');
if (strlen($uuid) < 8) {
    json_exit(['code' => 400, 'msg' => '浏览器标识无效，请刷新页面后重试']);
}

// ── IP限流：60秒内同IP同表单最多提交3次 ──
$client_ip = get_client_ip();
$rl_key = 'submit_' . $url_key . '_' . $client_ip;
if (!rate_limit_check($rl_key, 60, 3)) {
    json_exit(['code' => 429, 'msg' => '提交过于频繁，请稍后再试'], 429);
}

// ── IP每日限制：同一IP每天最多提交30次（防止恶意刷数据） ──
$form = get_form_by_url_key($url_key);
if (!$form) {
    json_exit(['code' => 404, 'msg' => '表单不存在'], 404);
}
if (!ip_limit_check($client_ip, $form['id'], 30)) {
    json_exit(['code' => 429, 'msg' => '今日提交次数已达上限，请明天再试'], 429);
}

$now = date('Y-m-d H:i');
$in_time = true;
if ($form['start_time'] && $now < $form['start_time']) $in_time = false;
if ($form['end_time'] && $now > $form['end_time']) $in_time = false;
$count = get_form_submissions_count($form['id']);

if (!$form['active']) {
    json_exit(['code' => 403, 'msg' => '采集已关闭']);
}
if (!$in_time) {
    json_exit(['code' => 403, 'msg' => '不在采集时间范围内']);
}
if ($form['max_count'] > 0 && $count >= $form['max_count']) {
    json_exit(['code' => 403, 'msg' => '已达到采集人数上限']);
}

$fields_config = $form['fields_config'] ?? [];
if (empty($fields_config)) {
    json_exit(['code' => 500, 'msg' => '表单配置为空，请先在后台配置'],500);
}

$form_data = [];

// 预置字段：从后台配置读取
$PRESET_KEYS = ['省','市','区','学校','筛查项目'];
foreach ($PRESET_KEYS as $pk) {
    if (isset($fields_config[$pk])) {
        $form_data[$pk] = $fields_config[$pk]['value'] ?? '';
    }
}

// 终端客户填写字段
foreach ($fields_config as $field_key => $conf) {
    if (in_array($field_key, $PRESET_KEYS)) continue;
    $val = $_POST[$field_key] ?? '';
    $form_data[$field_key] = is_array($val) ? implode(',', $val) : trim($val);
}

// 必填验证
foreach ($fields_config as $field_key => $conf) {
    if (!empty($conf['required']) && empty($form_data[$field_key])) {
        json_exit(['code' => 400, 'msg' => "「{$field_key}」为必填项"]);
    }
}

// 电话校验（11位手机号）
if (!empty($form_data['电话']) && !preg_match('/^1[3-9]\d{9}$/', $form_data['电话'])) {
    json_exit(['code' => 400, 'msg' => '请输入有效的11位手机号']);
}

// 身份证号校验（18位）
if (!empty($form_data['身份证号']) && !preg_match('/^[0-9Xx]{18}$/', $form_data['身份证号'])) {
    json_exit(['code' => 400, 'msg' => '请输入有效的18位身份证号']);
}

// 获取或创建家庭修改码（同一手机号共用一个码）
$phone_for_code = $form_data['电话'] ?? '';
$edit_code = '';
if ($phone_for_code) {
    $edit_code = get_or_create_family_edit_code($form['id'], $phone_for_code);
}
$ok = add_form_submission($form['id'], $form_data, $client_ip, $edit_code);
if ($ok) {
    json_exit(['code' => 0, 'msg' => '提交成功', 'edit_code' => $edit_code]);
} else {
    json_exit(['code' => 500, 'msg' => '提交失败，请重试'], 500);
}