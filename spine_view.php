<?php
/**
 * 脊柱筛查结果查询 - Step3 结果展示
 * V2.0
 */
require_once __DIR__ . '/lib.php';

$url_key = trim($_GET['k'] ?? '');
$t = trim($_GET['t'] ?? '');
$idx = intval($_GET['idx'] ?? -1);

if (!$url_key || !$t || $idx < 0) { http_response_code(403); exit('参数错误'); }

$form = get_form_by_url_key($url_key);
if (!$form) { http_response_code(404); exit('表单不存在'); }

$spine_db_path = $form['spine_db_path'] ?? '';
if (!$spine_db_path) { exit('<div style="text-align:center;padding:60px;font-size:15px;color:#ff4d4f">该表单未绑定脊柱筛查库</div>'); }

// 验证 token
$parts = explode('|', base64_decode($t));
if (count($parts) !== 2) { http_response_code(403); exit('验证信息无效'); }
list($phone, $edit_code) = $parts;

$result = get_all_submissions_by_phone_and_code($phone, $edit_code, $form['id']);
$students = $result['submissions'];
if (!$result['found'] || !isset($students[$idx])) { http_response_code(403); exit('学生信息不存在'); }

$stu = $students[$idx];
$id_card = $stu['data']['学生身份证号'] ?? $stu['data']['身份证号'] ?? '';
$stu_name = $stu['data']['学生姓名'] ?? $stu['data']['姓名'] ?? '未知';

if (!$id_card) { exit('<div style="text-align:center;padding:60px;font-size:15px;color:#ff4d4f">该学生未登记身份证号</div>'); }

$results = get_spine_results_by_idcard($id_card, $spine_db_path);
$ui = $results['user_info'] ?? null;
$prs = $results['print_reports'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>脊柱筛查结果 - <?= htmlspecialchars($stu_name) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh}
.wrap{max-width:480px;margin:0 auto;padding:0 0 40px}
.hdr{background:linear-gradient(135deg,#1890ff,#0050b3);color:#fff;text-align:center;padding:24px 20px;border-radius:0 0 20px 20px;margin-bottom:16px}
.hdr h2{font-size:20px;font-weight:700;margin-bottom:6px}
.hdr p{font-size:13px;opacity:.85;font-family:monospace}
.info{background:#fff;border-radius:12px;margin:0 16px 14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.row{display:flex;padding:12px 16px;border-bottom:1px solid #f0f0f0;font-size:14px}
.row:last-child{border-bottom:none}
.row .lb{color:#999;width:80px;flex-shrink:0}
.row .vl{color:#1a1a1a;flex:1}
.rc{background:#fff;border-radius:12px;margin:0 16px 14px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.rc-date{font-size:12px;color:#bbb;margin-bottom:10px}
.tag{padding:10px 14px;border-radius:8px;font-size:14px;font-weight:700;text-align:center;margin-bottom:10px}
.tag-ok{background:#f6ffed;border:1px solid #b7eb8f;color:#52c41a}
.tag-warn{background:#fff7e6;border:1px solid #ffb74d;color:#fa8c16}
.ev{background:#fafafa;border-radius:6px;padding:12px;font-size:13px;color:#666;line-height:1.7}
.empty{background:#fff;border-radius:12px;margin:0 16px;padding:30px 20px;text-align:center;font-size:14px;color:#999;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.footer{text-align:center;font-size:12px;color:#ccc;padding:16px 0}
.bback{display:block;text-align:center;padding:12px;font-size:13px;color:#1890ff;margin:0 16px 14px}
</style>
</head>
<body>
<div class="wrap">
<a href="spine_students.php?k=<?= htmlspecialchars($url_key) ?>&t=<?= htmlspecialchars($t) ?>" class="bback">← 重新选择学生</a>

<div class="hdr">
  <h2><?= htmlspecialchars($stu_name) ?></h2>
  <p><?= mask_idcard($id_card) ?></p>
</div>

<?php if ($ui): ?>
<div class="info">
  <div class="row"><span class="lb">筛查项目</span><span class="vl"><?= htmlspecialchars($ui['SType'] ?? '脊柱筛查') ?></span></div>
  <div class="row"><span class="lb">学校</span><span class="vl"><?= htmlspecialchars($ui['UnitSchool'] ?? '-') ?></span></div>
  <div class="row"><span class="lb">年级</span><span class="vl"><?= htmlspecialchars($ui['DeptGrade'] ?? '-') ?></span></div>
  <div class="row"><span class="lb">班级</span><span class="vl"><?= htmlspecialchars($ui['WorkGroupClass'] ?? '-') ?></span></div>
</div>
<?php endif; ?>

<?php if (!$results['found']): ?>
<div class="empty">✅ 暂无脊柱筛查记录<br><span style="font-size:12px;color:#bbb;margin-top:6px;display:inline-block">如有疑问请联系学校或筛查机构</span></div>
<?php else: ?>
<?php foreach ($prs as $pr): ?>
<div class="rc">
  <div class="rc-date">📅 <?= htmlspecialchars(substr($pr['PrintDate'] ?? '', 0, 10)) ?></div>
  <?php $sg = $pr['SuggestedContent'] ?? ''; $ab = $sg !== '正常'; ?>
  <div class="tag <?= $ab ? 'tag-warn' : 'tag-ok' ?>">
    <?php $tp = $pr['Type'] ?? ''; if ($tp) echo htmlspecialchars($tp).' '; ?>
    结论：<?= htmlspecialchars($sg ?: '未知') ?>
  </div>
  <?php if (!empty($pr['ResultEvaluation'])): ?>
  <div class="ev"><?= htmlspecialchars($pr['ResultEvaluation']) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div class="footer">脊柱筛查结果由设备自动生成，如有疑问请咨询专业医疗机构</div>
</div>
</body>
</html>