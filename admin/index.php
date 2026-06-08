<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$users = get_all_users();
$user_list = [];
foreach ($users as $k => $v) {
    if ($k === 'admin') continue;
    $forms = get_forms_by_user($k);
    $total_subs = 0;
    foreach ($forms as $f) {
        $total_subs += get_form_submissions_count($f['id']);
    }
    $user_list[] = [
        'id' => $k,
        'name' => $v['name'],
        'created' => $v['created_at'],
        'form_count' => count($forms),
        'total_subs' => $total_subs,
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= count($user_list) ?></div><div class="stat-label">普通用户数</div></div>
    <div class="stat-card"><div class="stat-num"><?= count(array_filter($user_list, fn($u)=>$u['form_count']>0)) ?></div><div class="stat-label">已有表单</div></div>
  </div>

  <div class="card">
    <div class="card-title">
      普通用户列表
      <a href="user_add.php" class="btn btn-primary btn-sm" style="float:right">➕ 新增用户</a>
    </div>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>用户名</th><th>显示名称</th><th>创建时间</th>
          <th>表单数</th><th>采集条数</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($user_list as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['id']) ?></td>
          <td><?= htmlspecialchars($u['name']) ?></td>
          <td><?= htmlspecialchars($u['created']) ?></td>
          <td><?= $u['form_count'] ?></td>
          <td><?= $u['total_subs'] ?></td>
          <td>
            <a href="user_edit.php?id=<?= urlencode($u['id']) ?>" class="btn btn-default btn-sm">编辑</a>
            <a href="user_del.php?id=<?= urlencode($u['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('删除用户将同时删除其所有表单和数据，确定？')">删除</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($user_list)): ?>
        <tr><td colspan="6" style="text-align:center;color:#999">暂无普通用户</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
</body>
</html>