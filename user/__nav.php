<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="topbar">
  <div class="topbar-title">🏢 信息采集 · <?= htmlspecialchars($_SESSION['name'] ?? uid()) ?></div>
  <div class="topbar-user">
    <a href="../logout.php" style="color:#ff4d4f">退出</a>
  </div>
</div>
<div class="layout">
  <div class="sidebar">
    <a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">📊 我的表单</a>
    <a href="password.php" class="<?= $current === 'password.php' ? 'active' : '' ?>">🔑 修改密码</a>
    <a href="../login.php">🏠 系统首页</a>
  </div>
  <div class="main">