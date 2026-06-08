<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$uid = trim($_GET['id'] ?? '');
if (!$uid || $uid === 'admin') redirect('index.php');

$user = get_user($uid);
if (!$user) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pw = $_POST['password'] ?? '';
    if (!$name) {
        $error = '名称不能为空';
    } else {
        $ok = update_user($uid, $name, $pw ?: null);
        if ($ok) {
            redirect('index.php');
        } else {
            $error = '保存失败';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>编辑用户</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
  <div class="card" style="max-width:500px">
    <div class="card-title">编辑用户：<?= htmlspecialchars($uid) ?></div>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" value="<?= htmlspecialchars($uid) ?>" disabled>
      </div>
      <div class="form-group">
        <label>显示名称</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>">
      </div>
      <div class="form-group">
        <label>新密码（留空不修改）</label>
        <input type="password" name="password" placeholder="不修改请留空">
      </div>
      <button type="submit" class="btn btn-primary">保存</button>
      <a href="index.php" class="btn btn-default">取消</a>
    </form>
  </div>
</div>
</body>
</html>