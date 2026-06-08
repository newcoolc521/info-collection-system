<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$uid = uid();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['old'] ?? '';
    $new1 = $_POST['new1'] ?? '';
    $new2 = $_POST['new2'] ?? '';
    if (!$old || !$new1 || !$new2) {
        $error = '请填写所有字段';
    } elseif ($new1 !== $new2) {
        $error = '两次新密码不一致';
    } elseif (strlen($new1) < 6) {
        $error = '新密码至少6位';
    } else {
        $user = get_user($uid);
        if (!$user || !password_verify($old, $user['password'])) {
            $error = '原密码错误';
        } else {
            $hash = password_hash($new1, PASSWORD_DEFAULT);
            $db = get_db();
            $stmt = $db->prepare('UPDATE users SET password = :p WHERE username = :u');
            $stmt->bindValue(':p', $hash);
            $stmt->bindValue(':u', $uid);
            $stmt->execute();
            $msg = '密码修改成功';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>修改密码 - 管理后台</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="card" style="max-width:480px;margin:24px">
  <div class="card-title">🔑 修改管理员密码</div>
  <div style="padding:0 0 8px">
    <?php if ($msg): ?>
    <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error">✗ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>原密码</label>
        <input type="password" name="old" required placeholder="请输入原密码">
      </div>
      <div class="form-group">
        <label>新密码</label>
        <input type="password" name="new1" required placeholder="请输入新密码（至少6位）">
      </div>
      <div class="form-group">
        <label>确认新密码</label>
        <input type="password" name="new2" required placeholder="请再次输入新密码">
      </div>
      <button type="submit" class="btn btn-primary">确认修改</button>
      <a href="index.php" class="btn btn-default" style="margin-left:8px">返回</a>
    </form>
  </div>
</div>
</body>
</html>