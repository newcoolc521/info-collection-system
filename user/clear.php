<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = intval($_GET['fid'] ?? 0);
$form = get_form_by_id($form_id);
if (!$form || $form['username'] !== $uid) {
    redirect('index.php');
}

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== '确认删除') {
        $err = '请输入「确认删除」';
    } else {
        clear_form_submissions($form_id);
        redirect("submissions.php?fid=$form_id");
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>清除数据 - <?= htmlspecialchars($form['name']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
  <div class="card" style="max-width:500px;margin:20px">
    <div class="card-title" style="color:#ff4d4f">⚠️ 清除「<?= htmlspecialchars($form['name']) ?>」的所有采集数据</div>
    <div class="alert alert-error">
      此操作将永久删除该表单下<strong>所有已采集的数据</strong>，<strong>不可恢复</strong>！
    </div>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>请输入 <strong>确认删除</strong> 以继续</label>
        <input type="text" name="confirm" required placeholder="输入：确认删除" style="margin-top:8px">
      </div>
      <button type="submit" class="btn btn-danger">确认删除</button>
      <a href="submissions.php?fid=<?= $form_id ?>" class="btn btn-default">取消</a>
    </form>
  </div>
</div>
</body>
</html>