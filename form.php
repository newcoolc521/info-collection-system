<?php
/**
 * 信息采集系统 - 终端客户表单页面
 * 预置字段（省/市/区/学校/筛查项目）由后台用户预先设定，终端客户不可修改
 * 其他字段终端客户自行填写，全部必填
 */
require_once __DIR__ . '/lib.php';

$url_key = trim($_GET['k'] ?? '');
if (!$url_key) { http_response_code(403); echo '缺少表单标识'; exit; }

$form = get_form_by_url_key($url_key);

// 生成验证码
$captcha = generate_captcha();
if (!$form) { http_response_code(404); echo '表单不存在'; exit; }

$fields_config = $form['fields_config'] ?? [];
if (empty($fields_config)) {
    echo '<div style="text-align:center;padding:48px;color:#999">该表单暂未开放</div>'; exit;
}

$now = date('Y-m-d H:i');
$in_time = true;
if ($form['start_time'] && $now < $form['start_time']) $in_time = false;
if ($form['end_time'] && $now > $form['end_time']) $in_time = false;
$count = get_form_submissions_count($form['id']);
$closed = !$form['active'] || !$in_time || ($form['max_count'] > 0 && $count >= $form['max_count']);

$region_data = load_region_data();
$provinces = array_keys($region_data);

// 预置字段列表
$PRESET_KEYS = ['省','市','区','学校','筛查项目'];

// ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($form['name']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
body { background: #f0f2f5; }
.form-header { background: #fff; padding: 20px 24px; text-align: center; border-bottom: 1px solid #e8e8e8; margin-bottom: 20px; }
.form-header h2 { font-size: 20px; color: #333; margin-bottom: 6px; }
.form-header p { color: #999; font-size: 13px; }
.form-wrap { max-width: 700px; margin: 0 auto; padding: 0 16px 40px; }
.form-row { display: grid; gap: 16px; margin-bottom: 16px; }
.form-row-2 { grid-template-columns: 1fr 1fr; }
.form-row-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-group-full { margin-bottom: 16px; }
.form-group-full label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #555; }
.form-group-full label span.required { color: #ff4d4f; margin-left: 3px; }
.form-group-full input, .form-group-full select {
    width: 100%; padding: 10px 12px;
    border: 1px solid #d9d9d9; border-radius: 6px;
    font-size: 14px; background: #fff;
    transition: border-color .2s;
}
.form-group-full input:focus, .form-group-full select:focus {
    outline: none; border-color: #1890ff;
    box-shadow: 0 0 0 2px rgba(24,144,255,.15);
}
/* 预置字段（只读展示） */
.preset-field {
    background: #f5f5f5 !important;
    color: #333 !important;
    border-color: #d9d9d9 !important;
    font-weight: 500;
}
.preset-label { color: #1890ff; font-size: 12px; display: inline-block; margin-bottom: 4px; }
.btn-submit {
    width: 100%; padding: 13px;
    background: #1890ff; color: #fff;
    border: none; border-radius: 6px;
    font-size: 16px; cursor: pointer;
    transition: background .2s;
}
.btn-submit:hover { background: #096dd9; }
.btn-submit:disabled { background: #d9d9d9; cursor: not-allowed; }
.msg-box { text-align: center; padding: 48px 24px; }
.msg-box .icon { font-size: 48px; margin-bottom: 16px; }
.msg-box.closed .icon { color: #ff4d4f; }
.msg-box.success .icon { color: #52c41a; }
.msg-box h3 { font-size: 18px; margin-bottom: 8px; }
.msg-box p { color: #666; font-size: 14px; }
@media (max-width: 600px) {
    .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
}
/* 弹窗 */
.popup-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.popup-box {
    background: #fff; border-radius: 12px; padding: 36px 32px;
    max-width: 380px; width: 90vw; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.popup-box .popup-icon { font-size: 52px; margin-bottom: 16px; }
.popup-box h3 { font-size: 18px; margin-bottom: 10px; }
.popup-box p { color: #666; font-size: 14px; margin-bottom: 20px; }
.popup-box .popup-btn {
    display: inline-block; padding: 10px 32px;
    background: #1890ff; color: #fff; border-radius: 6px;
    font-size: 15px; cursor: pointer; border: none;
}
.popup-box .popup-btn:hover { background: #096dd9; }
.popup-box .popup-btn.error { background: #ff4d4f; }
.captcha-row { background: #f0f7ff; border: 1px solid #91d5ff; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
</style>
</head>
<body>

<div class="form-header">
    <h2><?= htmlspecialchars($form['name']) ?></h2>
    <p>请如实填写以下信息，带<span style="color:#ff4d4f">*</span> 为必填项</p>
</div>

<div class="form-wrap" id="formWrap">
<?php if ($closed): ?>
    <div class="msg-box closed">
        <div class="icon">🔍</div>
        <h3>信息登记已结束</h3>
        <p style="margin-top:8px">本轮信息登记已结束，您可查询脊柱筛查结果</p>
        <div style="margin-top:20px">
            <a href="spine_verify.php?k=<?= htmlspecialchars($url_key) ?>" style="display:inline-block;padding:12px 28px;background:#1890ff;color:#fff;border-radius:8px;font-size:15px;font-weight:700;text-decoration:none">🦴 查看脊柱筛查结果</a>
        </div>
    </div>
<?php else: ?>
    <form id="mainForm" method="post">
        <input type="hidden" name="k" value="<?= htmlspecialchars($url_key) ?>">
        <input type="hidden" name="uuid" id="uuidField" value="">
        <input type="hidden" name="captcha_key" value="<?= htmlspecialchars($captcha['key']) ?>">

        <?php // ── 预置字段（后台用户已预设，终端客户只读展示）── ?>

        <?php if (isset($fields_config['省'])): ?>
        <div class="form-row form-row-3">
            <div class="form-group-full">
                <label class="preset-label">● 省（已预设）</label>
                <input type="text" value="<?= htmlspecialchars($fields_config['省']['value'] ?? '') ?>" class="preset-field" readonly>
            </div>
           <div class="form-group-full">
                <label class="preset-label">● 市（已预设）</label>
                <input type="text" value="<?= htmlspecialchars($fields_config['市']['value'] ?? '') ?>" class="preset-field" readonly>
            </div>
            <div class="form-group-full">
                <label class="preset-label">● 区（已预设）</label>
                <input type="text" value="<?= htmlspecialchars($fields_config['区']['value'] ?? '') ?>" class="preset-field" readonly>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['学校'])): ?>
        <div class="form-group-full">
            <label class="preset-label">● 学校名称（已预设）</label>
            <input type="text" value="<?= htmlspecialchars($fields_config['学校']['value'] ?? '') ?>" class="preset-field" readonly>
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['筛查项目'])): ?>
        <div class="form-group-full">
            <label class="preset-label">● 筛查项目（已预设）</label>
            <input type="text" value="<?= htmlspecialchars($fields_config['筛查项目']['value'] ?? '') ?>" class="preset-field" readonly>
        </div>
        <?php endif; ?>

        <?php // ──终端客户自行填写的字段 ── ?>

        <?php if (isset($fields_config['年级']) || isset($fields_config['班级'])): ?>
        <div class="form-row form-row-2">
            <?php if (isset($fields_config['年级'])): ?>
            <div class="form-group-full">
                <label>年级 <span class="required">*</span></label>
                <select name="年级" required>
                    <option value="">请选择年级</option>
                    <?php foreach (($fields_config['年级']['options'] ?? []) as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (isset($fields_config['班级'])): ?>
            <div class="form-group-full">
                <label>班级 <span class="required">*</span></label>
                <select name="班级" required>
                    <option value="">请选择班级</option>
                    <?php foreach (($fields_config['班级']['options'] ?? []) as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['姓名'])): ?>
        <div class="form-group-full">
            <label>学生姓名 <span class="required">*</span></label>
            <input type="text" name="姓名" required placeholder="请输入姓名" maxlength="30">
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['性别'])): ?>
        <div class="form-group-full">
            <label>学生性别 <span class="required">*</span></label>
            <select name="性别" required>
                <option value="">请选择性别</option>
                <?php foreach (($fields_config['性别']['options'] ?? ['男','女']) as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['电话'])): ?>
        <div class="form-group-full">
            <label>监护人电话 <span class="required">*</span></label>
            <input type="tel" name="电话" required placeholder="请输入11位手机号" maxlength="11" pattern="1[3-9]\d{9}"
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['出生日期'])): ?>
        <div class="form-group-full">
            <label>学生出生日期 <span class="required">*</span></label>
            <input type="date" name="出生日期" required max="<?= date('Y-m-d') ?>">
        </div>
        <?php endif; ?>

        <?php if (isset($fields_config['身份证号'])): ?>
        <div class="form-group-full">
            <label>学生身份证号 <span class="required">*</span></label>
            <input type="text" name="身份证号" required placeholder="请输入18位身份证号" maxlength="18" pattern="[0-9Xx]{18}"
        </div>
        <?php endif; ?>

        <div id="msgBox" class="msg-box" style="display:none">
            <div class="icon" id="msgIcon"></div>
            <h3 id="msgTitle"></h3>
            <p id="msgText"></p>
        </div>

        <div class="captcha-row">
          <label style="font-size:14px;font-weight:600;margin-bottom:6px;display:block">请回答：<span style="color:#1890ff"><?= htmlspecialchars($captcha['question']) ?></span></label>
          <input type="text" name="captcha_answer" id="captchaAnswer" style="padding:8px 12px;border:1px solid #d9d9d9;border-radius:6px;width:120px;font-size:14px" placeholder="请输入答案" autocomplete="off">
        </div>
        <button type="submit" class="btn-submit" id="btnSubmit">提交</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:13px;color:#999">
      想修改已提交的信息？<a href="form_edit.php?k=<?= htmlspecialchars($url_key) ?>" style="color:#1890ff">点此修改</a>
    </div>
<?php endif; ?>
</div>

<!-- 遮罩弹窗 -->
<div id="popupOverlay" class="popup-overlay" style="display:none">
  <div class="popup-box">
    <div class="popup-icon" id="popupIcon"></div>
    <h3 id="popupTitle"></h3>
    <p id="popupText"></p>
    <button class="popup-btn" id="popupBtn" onclick="closePopup()">确定</button>
  </div>
</div>

<script>
function showPopup(icon, title, text, isError) {
    document.getElementById('popupIcon').textContent = icon;
    document.getElementById('popupTitle').textContent = title;
    document.getElementById('popupText').innerHTML = text;
    if (isError) document.getElementById('popupBtn').classList.add('error');
    document.getElementById('popupOverlay').style.display = 'flex';
}
function closePopup() {
    document.getElementById('popupOverlay').style.display = 'none';
}

var submitted = false;
document.getElementById('mainForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (submitted) return;
    submitted = true;
    var btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.textContent = '提交中...';

    var formData = new FormData(this);
    var uuid = localStorage.getItem('mw_uuid');
    if (!uuid) { uuid = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem('mw_uuid', uuid); }
    formData.set('uuid', uuid);
    fetch('api/submit.php', {
        method: 'POST',
        body: formData
    }).then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 0) {
            var editCodeMsg = '';
            if (data.edit_code) {
                editCodeMsg = '<br><br><div style="background:#fff1f0;border:1px solid #ffccc7;border-radius:6px;padding:10px 14px;text-align:left;font-size:13px">'
                    + '<div style="color:#ff4d4f;font-weight:600;margin-bottom:4px">⚠️ 请截图保存您的修改码（全家通用）</div>'
                    + '修改码：<span style="font-size:18px;font-weight:bold;color:#ff4d4f;font-family:monospace">' + data.edit_code + '</span>'
                    + '<br><span style="color:#888;font-size:12px">凭此码可修改该手机号提交的学生信息</span>'
                    + '</div>';
            }
            showPopup('✅', '提交成功', '感谢您的填写，信息已成功提交！' + editCodeMsg, false);
            document.getElementById('mainForm').reset();
        } else {
            showPopup('⛔', '提交失败', data.msg || '请检查填写内容后重试', true);
            submitted = false;
            btn.disabled = false;
            btn.textContent = '提交';
        }
    })
    .catch(function() {
        showPopup('⛔', '网络错误', '请检查网络后重试', true);
        submitted = false;
        btn.disabled = false;
        btn.textContent = '提交';
    });
});
</script>
</body>
</html>