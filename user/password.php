<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['old_password'] ?? '';
    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';
    if (!$old || !$new1) {
        $err = '请填写完整';
    } elseif ($new1 !== $new2) {
        $err = '两次新密码不一致';
    } elseif (strlen($new1) < 6) {
        $err = '新密码至少6位';
    } else {
        $ok = update_password(uid(), $old, $new1);
        if ($ok) {
            $msg = '密码修改成功';
        } else {
            $err = '原密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>修改密码</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
  <div class="card" style="max-width:450px;margin:20px">
    <div class="card-title">🔑 修改密码</div>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success">✓ <?= $msg ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label>原密码</label><input type="password" name="old_password" required></div>
      <div class="form-group"><label>新密码</label><input type="password" name="new_password" required minlength="6" placeholder="至少6位"></div>
      <div class="form-group"><label>确认新密码</label><input type="password" name="new_password2" required></div>
      <button type="submit" class="btn btn-primary">确认修改</button>
    </form>
  </div>
</div>
</body>
</html>