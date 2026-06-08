<?php
/**
 * 用户后台 - 查看脊柱筛查结果
 * V2.0 新增功能
 */
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = intval($_GET['fid'] ?? 0);
$submission_id = intval($_GET['sid'] ?? 0);

$form = get_form_by_id($form_id);
if (!$form || $form['username'] !== $uid) {
    redirect('index.php');
}

$submission = get_submission_by_id($submission_id);
if (!$submission || $submission['form_id'] != $form_id) {
    redirect("submissions.php?fid={$form_id}");
}

$form_data = $submission['data'] ?? [];
$student_name = $form_data['学生姓名'] ?? $form_data['姓名'] ?? '未知';
$id_card = $form_data['学生身份证号'] ?? $form_data['身份证号'] ?? '';
$spine_db_path = $form['spine_db_path'] ?? '';

if (!$spine_db_path || !$id_card) {
    $error_msg = !$spine_db_path ? '该表单未绑定脊柱筛查库' : '该学生未登记身份证号';
}

$spine_results = null;
if ($spine_db_path && $id_card) {
    $spine_results = get_spine_results_by_idcard($id_card, $spine_db_path);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>脊柱筛查结果 - <?= htmlspecialchars($student_name) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.result-header { background: linear-gradient(135deg, #1890ff, #096dd9); color: #fff; border-radius: 10px; padding: 20px 16px; margin-bottom: 14px; text-align: center; }
.result-header .name { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
.result-header .idcard { font-size: 13px; opacity: 0.85; font-family: monospace; }
.result-info { background: #fff; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #8c8c8c; }
.info-row .value { color: #262626; text-align: right; }
.report-card { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.report-title { font-weight: 700; font-size: 15px; color: #262626; margin-bottom: 10px; }
.report-date { font-size: 12px; color: #bfbfbf; margin-bottom: 10px; }
.tag { display: block; text-align: center; padding: 10px 14px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-bottom: 10px; }
.tag-normal { background: #f6ffed; border: 1px solid #b7eb8f; color: #52c41a; }
.tag-warn { background: #fff7e6; border: 1px solid #ffb74d; color: #fa8c16; }
.tag-error { background: #fff1f0; border: 1px solid #ffccc7; color: #ff4d4f; }
.eval-text { background: #fafafa; border-radius: 6px; padding: 12px; font-size: 13px; color: #595959; line-height: 1.7; margin-top: 10px; }
.no-result { background: #fff; border-radius: 10px; padding: 24px; text-align: center; font-size: 14px; color: #8c8c8c; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 14px; }
.idcard-mask { font-family: monospace; font-size: 14px; letter-spacing: 1px; color: #8c8c8c; }
</style>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">

<a href="submissions.php?fid=<?= $form_id ?>" class="back-link" style="display:inline-block;margin:16px 20px;font-size:13px">← 返回数据列表</a>

<?php if (!empty($error_msg)): ?>
<div style="margin:0 20px">
  <div class="alert alert-error">✗ <?= htmlspecialchars($error_msg) ?></div>
</div>
<?php else:

$userInfo = $spine_results['user_info'] ?? null;
$printReports = $spine_results['print_reports'] ?? [];
?>

<!-- 学生信息 -->
<div style="margin:0 20px">
<div class="result-header">
  <div class="name"><?= htmlspecialchars($student_name) ?></div>
  <div class="idcard"><?= mask_idcard($id_card) ?></div>
</div>

<!-- 学生信息（脊柱库） -->
<?php if ($userInfo): ?>
<div class="result-info">
  <div class="info-row">
    <span class="label">筛查项目</span>
    <span class="value"><?= htmlspecialchars($userInfo['SType'] ?? '脊柱筛查') ?></span>
  </div>
  <div class="info-row">
    <span class="label">学校</span>
    <span class="value"><?= htmlspecialchars($userInfo['UnitSchool'] ?? '-') ?></span>
  </div>
  <div class="info-row">
    <span class="label">年级</span>
    <span class="value"><?= htmlspecialchars($userInfo['DeptGrade'] ?? '-') ?></span>
  </div>
  <div class="info-row">
    <span class="label">班级</span>
    <span class="value"><?= htmlspecialchars($userInfo['WorkGroupClass'] ?? '-') ?></span>
  </div>
</div>
<?php endif; ?>

<!-- 报告列表 -->
<?php if (!$spine_results['found']): ?>
<div class="no-result">✅ 该学生暂无脊柱筛查记录</div>
<?php else: ?>
<?php foreach ($printReports as $pr): ?>
<div class="report-card">
  <div class="report-title">
    📋 脊柱筛查报告
    <?php $type = $pr['Type'] ?? ''; if ($type): ?>
    <span style="font-size:12px;color:#8c8c8c;font-weight:normal;margin-left:8px">[<?= htmlspecialchars($type) ?>]</span>
    <?php endif; ?>
  </div>
  <div class="report-date">报告日期：<?= htmlspecialchars(substr($pr['PrintDate'] ?? '', 0, 10)) ?></div>

  <?php
    $suggested = $pr['SuggestedContent'] ?? '';
    $is_abnormal = $suggested !== '正常';
  ?>
  <div class="tag <?= $is_abnormal ? 'tag-warn' : 'tag-normal' ?>">
    筛查结论：<?= htmlspecialchars($suggested ?: '未知') ?>
  </div>

  <?php if (!empty($pr['ResultEvaluation'])): ?>
  <div class="eval-text"><?= htmlspecialchars($pr['ResultEvaluation']) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<?php endif; ?>

</div>
</body>
</html>