<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$sub_id = intval($_GET['id'] ?? 0);
$sub = get_submission_by_id($sub_id);
if (!$sub) {
    die('记录不存在');
}

$form = get_form_by_id($sub['form_id']);
if (!$form || $form['username'] !== $uid) {
    die('无权操作此记录');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    delete_submission($sub_id);
    header('Location: submissions.php?fid=' . $form['id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>删除确认</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<div class="card" style="margin:60px auto;max-width:500px">
  <div class="card-title" style="color:#ff4d4f">⚠️ 确认删除</div>
  <div style="padding:20px;font-size:15px">
    <p>确定要删除以下数据吗？此操作不可恢复。</p>
    <div style="background:#fff1f0;border:1px solid #ffccc7;border-radius:6px;padding:14px;margin:16px 0;font-size:13px">
      <div><strong>表单：</strong><?= htmlspecialchars($form['name']) ?></div>
      <div><strong>提交时间：</strong><?= htmlspecialchars($sub['submit_time']) ?></div>
      <div><strong>IP地址：</strong><?= htmlspecialchars($sub['ip']) ?></div>
    </div>
    <form method="post" style="display:flex;gap:10px">
      <button type="submit" class="btn btn-danger" style="flex:1">🗑️ 确认删除</button>
      <a href="submissions.php?fid=<?= $form['id'] ?>" class="btn btn-default" style="flex:1;text-align:center">取消</a>
    </form>
  </div>
</div>
</div>
</body>
</html>