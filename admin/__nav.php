<?php
$current = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="topbar">
  <div class="topbar-title">🏢 信息采集系统 · 管理后台</div>
  <div class="topbar-user">
   <span>管理员：<?= htmlspecialchars($_SESSION['name'] ?? uid()) ?></span>
    <a href="../logout.php" style="color:#ff4d4f;margin-left:8px">退出</a>
  </div>
</div>
<div class="layout">
  <div class="sidebar">
    <a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">📊 仪表盘</a>
    <a href="user_add.php" class="<?= $current === 'user_add.php' ? 'active' : '' ?>">➕ 新增用户</a>
    <a href="password.php" class="<?= $current === 'password.php' ? 'active' : '' ?>">🔑 修改密码</a>
    <a href="spine_db.php" class="<?= $current === 'spine_db.php' ? 'active' : '' ?>">🦴 脊柱库管理</a>
    <a href="../index.php">🏠 返回首页</a>
  </div>
  <div class="main"><?php // intentionally open div, closed by caller ?>