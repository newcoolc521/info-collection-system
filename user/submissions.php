<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = intval($_GET['fid'] ?? 0);
$form = get_form_by_id($form_id);
if (!$form || $form['username'] !== $uid) {
    redirect('index.php');
}

$fields_config = $form['fields_config'] ?? [];
$field_keys = array_keys($fields_config);

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$total = get_form_submissions_count($form_id);
$pages = $total > 0 ? ceil($total / $per_page) : 1;
$subs = get_form_submissions($form_id, $page, $per_page);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>已采集数据 - <?= htmlspecialchars($form['name']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.spine-btn { background: #e6f7ff; border: 1px solid #1890ff; color: #1890ff; border-radius: 4px; padding: 3px 8px; font-size: 12px; cursor: pointer; }
.spine-btn:hover { background: #bae7ff; }
</style>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<div class="card" style="margin:20px">
  <div class="card-title">
    <?= htmlspecialchars($form['name']) ?> · 已采集数据（共 <?= $total ?> 条）
    <div style="float:right;display:flex;gap:8px">
      <?php if ($total > 0): ?>
      <a href="export.php?fid=<?= $form_id ?>" class="btn btn-success btn-sm">📥 导出 Excel</a>
      <?php endif; ?>
      <a href="clear.php?fid=<?= $form_id ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定清除该表单所有数据？不可恢复！')">🗑️ 清除数据</a>
      <a href="index.php" class="btn btn-default btn-sm">← 返回</a>
    </div>
  </div>

  <?php if ($total === 0): ?>
  <div class="empty-state"><p>暂无采集数据</p></div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th style="width:50px">#</th>
        <?php foreach ($field_keys as $f): ?>
        <th><?= htmlspecialchars($f) ?></th>
        <?php endforeach; ?>
        <th style="width:150px">提交时间</th>
      <th style="width:100px">修改码</th>
      <th style="width:100px">操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($subs as $i => $s): ?>
    <tr>
      <td><?= ($page-1)*$per_page + $i + 1 ?></td>
      <?php foreach ($field_keys as $f): ?>
      <td><?= htmlspecialchars($s['data'][$f] ?? '-') ?></td>
      <?php endforeach; ?>
      <td><?= htmlspecialchars($s['submit_time']) ?></td>
      <td><?= htmlspecialchars($s['edit_code'] ?? '') ? '<span style="font-family:monospace;font-size:14px;font-weight:600;color:#ff4d4f">'.$s['edit_code'].'</span>' : '-' ?></td>
      <td>
        <?php if (!empty($form['spine_db_path'])): ?>
          <?php $idCard = $s['data']['学生身份证号'] ?? $s['data']['身份证号'] ?? ''; ?>
          <?php if ($idCard): ?>
          <a href="spine_result_view.php?fid=<?= $form_id ?>&sid=<?= $s['id'] ?>" class="btn btn-sm spine-btn" target="_blank" title="查看脊柱筛查结果">🦴 结果</a>
          <?php endif; ?>
        <?php endif; ?>
        <a href="submission_edit.php?id=<?= $s['id'] ?>" class="btn btn-default btn-sm" style="margin-right:4px">✏️</a>
        <a href="submission_delete.php?id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定删除此条记录？')">🗑️</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?fid=<?= $form_id ?>&page=<?= $page-1 ?>">‹</a><?php endif; ?>
    <?php for ($p = max(1, $page-2); $p <= min($pages, $page+2); $p++): ?>
    <a href="?fid=<?= $form_id ?>&page=<?= $p ?>" class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?fid=<?= $form_id ?>&page=<?= $page+1 ?>">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
</div>
</body>
</html>