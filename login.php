<?php
require_once __DIR__ . '/lib.php';

if (is_login()) {
    if (role() === 'admin') redirect('admin/index.php');
    redirect('user/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $user = get_user($u);
    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['uid'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        if ($user['role'] === 'admin') {
            redirect('admin/index.php');
        } else {
            redirect('user/index.php');
        }
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>脊柱健康筛查信息登记系统 - 登录</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-box">
  <h2>🏢 脊柱健康筛查信息登记系统</h2>
  <form method="post">
    <div class="form-group">
      <label>用户名</label>
      <input type="text" name="username" required placeholder="请输入用户名" autocomplete="username">
    </div>
    <div class="form-group">
      <label>密码</label>
      <input type="password" name="password" required placeholder="请输入密码" autocomplete="current-password">
    </div>
    <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <button type="submit" class="btn-primary">登 录</button>
  </form>
  <p class="login-tip">明玮健康医疗科技（广州）有限公司</p>
</div>
</body>
</html>