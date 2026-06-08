<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['user_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $pw = $_POST['password'] ?? '';

    if (!$uid || !$name) {
        $error = '用户名和名称不能为空';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $uid)) {
        $error = '用户名需为3-20位英文字母或数字';
    } elseif (get_user($uid)) {
        $error = '用户名已存在';
    } else {
        $ok = create_user($uid, $name, $pw);
        if ($ok) {
            redirect('index.php');
        } else {
            $error = '创建失败，请重试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新增用户</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
  <div class="card" style="max-width:500px">
    <div class="card-title">新增普通用户</div>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>用户名（登录账号）</label>
        <input type="text" name="user_id" required placeholder="3-20位英文字母或数字" pattern="[a-zA-Z0-9_]{3,20}">
      </div>
      <div class="form-group">
        <label>显示名称</label>
        <input type="text" name="name" required placeholder="如：某学校采集点">
      </div>
      <div class="form-group">
        <label>初始密码（默认 123456）</label>
        <input type="password" name="password" placeholder="不填则使用默认密码">
      </div>
      <button type="submit" class="btn btn-primary">创建</button>
      <a href="index.php" class="btn btn-default">取消</a>
    </form>
  </div>
</div>
</body>
</html>