<?php
/**
 * 终端客户 - 查看脊柱筛查结果
 * V2.0 新增功能（支持多学生选择）
 * 访问方式：spine_result.php?k=表单url_key
 * 验证方式：手机号 + 修改码
 */
require_once __DIR__ . '/lib.php';
session_start();

$url_key = trim($_GET['k'] ?? '');
if (!$url_key) {
    http_response_code(403);
    echo '缺少表单标识';
    exit;
}

$form = get_form_by_url_key($url_key);
if (!$form) {
    http_response_code(404);
    echo '表单不存在';
    exit;
}

if (!$form['active']) {
    echo '<div style="text-align:center;padding:60px;font-size:15px;color:#ff4d4f">采集已关闭</div>';
    exit;
}

$msg = '';
$error = '';
$spine_db_path = $form['spine_db_path'] ?? '';

if (!$spine_db_path) {
    $no_bind_error = '该表单未绑定脊柱筛查库，无法查询';
}

// Step 1: 验证手机号+修改码 → 获取所有学生记录
$all_submissions = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $phone = trim($_POST['phone'] ?? '');
    $edit_code = trim($_POST['edit_code'] ?? '');
    if (!$phone || !$edit_code) {
        $error = '请填写手机号和修改码';
    } else {
        $all_submissions = get_all_submissions_by_phone_and_code($phone, $edit_code, $form['id']);
        if (empty($all_submissions)) {
            $error = '手机号或修改码错误';
        } else {
            // 保存到 session
            $_SESSION['spine_phone'] = $phone;
            $_SESSION['spine_edit_code'] = $edit_code;
            $_SESSION['spine_submissions'] = $all_submissions;
            $_SESSION['spine_form_id'] = $form['id'];
        }
    }
}

// Step 2: 学生选择后查询脊柱结果
$selected_id_card = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_student') {
    $selected_idx = intval($_POST['student_idx'] ?? -1);
    if (isset($_SESSION['spine_submissions'][$selected_idx])) {
        $selected_sub = $_SESSION['spine_submissions'][$selected_idx];
        $selected_id_card = $selected_sub['data']['学生身份证号'] ?? $selected_sub['data']['身份证号'] ?? '';
    }
}

// 如果已有 session，直接加载
if (empty($all_submissions) && isset($_SESSION['spine_submissions']) && $_SESSION['spine_form_id'] == $form['id']) {
    $all_submissions = $_SESSION['spine_submissions'];
}

$spine_results = null;
if ($selected_id_card && $spine_db_path) {
    $spine_results = get_spine_results_by_idcard($selected_id_card, $spine_db_path);
}

// 计算有多少学生有身份证号
$students_with_idcard = array_filter($all_submissions, function($s) {
    $id = $s['data']['学生身份证号'] ?? $s['data']['身份证号'] ?? '';
    return $id !== '';
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>脊柱筛查结果查询</title>
<link rel="stylesheet" href="assets/mobile_ui.css">
<style>
body { background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.container { max-width: 480px; margin: 0 auto; padding: 20px 16px; }
.header { text-align: center; padding: 24px 20px; }
.header h2 { margin: 0 0 4px; color: #262626; font-size: 20px; }
.header p { margin: 0; color: #8c8c8c; font-size: 13px; }
.card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 13px; color: #595959; margin-bottom: 6px; font-weight: 600; }
.form-group input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 15px; outline: none; transition: border-color 0.2s; }
.form-group input:focus { border-color: #1890ff; box-shadow: 0 0 0 2px rgba(24,144,255,0.1); }
.btn-block { width: 100%; padding: 12px; background: #1890ff; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; display: block; text-align: center; }
.btn-block:active { background: #096dd9; }
.btn-outline { width: 100%; padding: 10px; background: #fff; color: #1890ff; border: 1px solid #1890ff; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; display: block; margin-top: 10px; }
.tips-box { background: #f0f7ff; border: 1px solid #91d5ff; border-radius: 6px; padding: 12px; font-size: 13px; color: #333; line-height: 1.6; margin-bottom: 16px; }
.result-header { background: linear-gradient(135deg, #1890ff, #096dd9); color: #fff; border-radius: 10px; padding: 20px; margin-bottom: 16px; text-align: center; }
.result-header .student-name { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
.result-header .idcard { font-size: 13px; opacity: 0.8; font-family: monospace; }
.result-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.result-row:last-child { border-bottom: none; }
.result-label { color: #8c8c8c; width: 90px; flex-shrink: 0; }
.result-value { color: #262626; flex: 1; }
.tag-normal { background: #f6ffed; border: 1px solid #b7eb8f; color: #52c41a; padding: 12px 16px; border-radius: 6px; font-size: 14px; text-align: center; }
.tag-warn { background: #fff7e6; border: 1px solid #ffb74d; color: #fa8c16; padding: 12px 16px; border-radius: 6px; font-size: 14px; text-align: center; }
.error-msg { background: #fff1f0; border: 1px solid #ffccc7; color: #ff4d4f; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
.idcard-mask { font-family: monospace; font-size: 14px; letter-spacing: 1px; }
.student-item { background: #fafafa; border: 1px solid #e8e8e8; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
.student-item:hover { border-color: #1890ff; background: #f0f7ff; }
.student-item .s-name { font-weight: 700; font-size: 15px; color: #262626; margin-bottom: 4px; }
.student-item .s-info { font-size: 12px; color: #8c8c8c; }
.student-item .s-grade { font-size: 13px; color: #595959; margin-top: 4px; }
.student-item.selected { border-color: #1890ff; background: #e6f7ff; }
.evaluation-text { background: #fafafa; border-radius: 6px; padding: 12px; font-size: 13px; color: #595959; line-height: 1.7; margin-top: 10px; }
.report-card { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.report-date { font-size: 12px; color: #bfbfbf; margin-bottom: 8px; }
.back-link { display: inline-block; margin: 0 0 12px; font-size: 13px; color: #1890ff; }
.back-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">

<div class="header">
  <h2>🦴 脊柱筛查结果查询</h2>
  <p>请输入手机号和修改码进行验证</p>
</div>

<?php if (!empty($no_bind_error)): ?>
<div class="card">
  <div class="error-msg">✗ <?= htmlspecialchars($no_bind_error) ?></div>
</div>
<?php endif; ?>

<?php if ($selected_id_card && $spine_results !== null): ?>
<!-- ===== 已选择学生，显示脊柱结果 ===== -->

<a href="spine_result.php?k=<?= htmlspecialchars($url_key) ?>" class="back-link">← 重新选择学生</a>

<?php
$userInfo = $spine_results['user_info'] ?? null;
$printReports = $spine_results['print_reports'] ?? [];
$student_name = '';
$id_card = $selected_id_card;

// 获取学生姓名
if ($userInfo) {
    $student_name = $userInfo['UserName'] ?? '未知';
} else {
    // fallback: 从提交数据中取
    foreach ($all_submissions as $s) {
        $sid = $s['data']['学生身份证号'] ?? $s['data']['身份证号'] ?? '';
        if ($sid === $selected_id_card) {
            $student_name = $s['data']['学生姓名'] ?? $s['data']['姓名'] ?? '未知';
            break;
        }
    }
}
?>

<!-- 学生信息 -->
<div class="result-header">
  <div class="student-name"><?= htmlspecialchars($student_name) ?></div>
  <div class="idcard"><?= mask_idcard($selected_id_card) ?></div>
</div>

<?php if ($userInfo): ?>
<div class="card">
  <div class="result-row">
    <div class="result-label">筛查项目</div>
    <div class="result-value"><?= htmlspecialchars($userInfo['SType'] ?? '脊柱筛查') ?></div>
  </div>
  <div class="result-row">
    <div class="result-label">学校</div>
    <div class="result-value"><?= htmlspecialchars($userInfo['UnitSchool'] ?? '-') ?></div>
  </div>
  <div class="result-row">
    <div class="result-label">年级</div>
    <div class="result-value"><?= htmlspecialchars($userInfo['DeptGrade'] ?? '-') ?></div>
  </div>
  <div class="result-row">
    <div class="result-label">班级</div>
    <div class="result-value"><?= htmlspecialchars($userInfo['WorkGroupClass'] ?? '-') ?></div>
  </div>
</div>
<?php endif; ?>

<!-- 报告列表 -->
<?php if (!$spine_results['found']): ?>
<div class="card">
  <div class="tag-normal">✅ 暂无脊柱筛查记录</div>
  <div style="text-align:center;margin-top:12px;font-size:13px;color:#999">如有疑问，请联系学校或筛查机构确认。</div>
</div>
<?php else: ?>
<?php
$pr = $printReports[0] ?? null;
if ($pr):
?>
<div class="report-card">
  <div class="report-date">📅 <?= htmlspecialchars(substr($pr['PrintDate'] ?? '', 0, 16)) ?></div>

  <?php
    $suggested = $pr['SuggestedContent'] ?? '';
    $is_abnormal = $suggested !== '正常';
  ?>
  <div class="<?= $is_abnormal ? 'tag-warn' : 'tag-normal' ?>" style="margin-bottom:10px">
    筛查结论：<?= htmlspecialchars($suggested ?: '未知') ?>
  </div>

  <?php if (!empty($pr['ResultEvaluation'])): ?>
  <div class="evaluation-text"><?= htmlspecialchars($pr['ResultEvaluation']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div style="text-align:center;padding:12px 0;font-size:12px;color:#bfbfbf">
  脊柱筛查结果由检测设备自动生成，如有疑问请咨询专业医疗机构
</div>

<?php elseif (!empty($all_submissions)): ?>
<!-- ===== 已验证，显示学生选择列表 ===== -->

<div class="card">
  <div class="tips-box">
    <strong>选择学生：</strong>请选择您要查看脊柱筛查结果的学生<br>
    <span style="color:#999;font-size:12px">（以下为您提交过的学生信息）</span>
  </div>

  <form method="post">
    <input type="hidden" name="action" value="select_student">
    <?php foreach ($students_with_idcard as $i => $sub): ?>
      <?php
        $sid = $sub['data']['学生身份证号'] ?? $sub['data']['身份证号'] ?? '';
        $sname = $sub['data']['学生姓名'] ?? $sub['data']['姓名'] ?? '未知';
        $sgrade = $sub['data']['年级'] ?? ($userInfo['DeptGrade'] ?? '-');
        $sclass = $sub['data']['班级'] ?? ($userInfo['WorkGroupClass'] ?? '-');
      ?>
      <div class="student-item" onclick="selectStudent(<?= $i ?>)">
        <input type="radio" name="student_idx" value="<?= $i ?>" id="s_<?= $i ?>" style="display:none">
        <div class="s-name">
          <span style="color:#1890ff;margin-right:6px">◉</span><?= htmlspecialchars($sname) ?>
        </div>
        <div class="s-grade"><?= htmlspecialchars($sgrade) ?> <?= htmlspecialchars($sclass) ?></div>
        <div class="s-info" style="font-size:11px;color:#bfbfbf;margin-top:2px">身份证号：<?= mask_idcard($sid) ?></div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($students_with_idcard)): ?>
    <div class="error-msg">您提交的学生信息中均未登记身份证号，无法查询脊柱筛查结果</div>
    <?php else: ?>
    <button type="submit" class="btn-block" style="margin-top:16px">🔍 查看脊柱筛查结果</button>
    <?php endif; ?>
  </form>
</div>

<div style="text-align:center">
  <a href="spine_result.php?k=<?= htmlspecialchars($url_key) ?>" class="back-link" style="color:#8c8c8c">← 重新输入手机号/修改码</a>
</div>

<?php else: ?>
<!-- ===== 初始页面：手机号+修改码验证 ===== -->

<div class="card">
  <div class="tips-box">
    <strong>查询说明：</strong><br>
    请填写您提交信息时使用的<strong>手机号</strong>，以及提交成功时获得的<strong>修改码</strong>。系统将自动查询您的脊柱筛查结果。
  </div>

  <?php if ($error): ?>
  <div class="error-msg">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="verify">
    <div class="form-group">
      <label>手机号</label>
      <input type="tel" name="phone" placeholder="请输入11位手机号" maxlength="11" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>修改码</label>
      <input type="text" name="edit_code" placeholder="请输入6位修改码" maxlength="6" required value="<?= htmlspecialchars($_POST['edit_code'] ?? '') ?>">
    </div>
    <button type="submit" class="btn-block">🔍 下一步</button>
  </form>
</div>

<?php endif; ?>

</div>

<script>
function selectStudent(idx) {
    document.querySelectorAll('.student-item').forEach(function(el, i) {
        el.classList.toggle('selected', i == idx);
    });
    document.getElementById('s_' + idx).checked = true;
}
</script>
</body>
</html>