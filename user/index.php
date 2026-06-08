<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$forms = get_forms_by_user($uid);
$user_dir = DATA_DIR . DS . $uid;
if (!is_dir($user_dir)) mkdir($user_dir, 0755, true);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>我的面板</title>
<link rel="stylesheet" href="../assets/style.css">
<script src="../assets/qrcodejs.js"></script>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">

<div class="stats-grid">
  <div class="stat-card"><div class="stat-num"><?= count($forms) ?></div><div class="stat-label">我的表单</div></div>
  <div class="stat-card"><div class="stat-num"><?= count(array_filter($forms, fn($f)=>$f['active'])) ?></div><div class="stat-label">开启采集</div></div>
</div>

<div class="card">
  <div class="card-title">
    我的表单
    <a href="form_edit.php" class="btn btn-primary btn-sm" style="float:right">➕ 新建表单</a>
  </div>
  <?php if (empty($forms)): ?>
  <div class="empty-state">
    <p>暂无表单，请点击右上角「新建表单」创建一个</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>表单名称</th>
        <th>采集链接</th>
        <th>二维码</th>
        <th>状态</th>
        <th>已采集/上限</th>
        <th>时间窗口</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($forms as $f):
        $count = get_form_submissions_count($f['id']);
        $max = $f['max_count'];
        $now = date('Y-m-d H:i');
        $in_time = true;
        if ($f['start_time'] && $now < $f['start_time']) $in_time = false;
        if ($f['end_time'] && $now > $f['end_time']) $in_time = false;
        $closed = !$f['active'] || !$in_time || ($max > 0 && $count >= $max);
    ?>
      <tr>
        <td><?= htmlspecialchars($f['name']) ?></td>
        <td style="font-size:12px;max-width:200px;word-break:break-all">
          <a href="../form.php?k=<?= htmlspecialchars($f['url_key']) ?>" target="_blank"><?= htmlspecialchars($f['url_key']) ?></a>
        </td>
        <td style="text-align:center">
          <div class="qr-cell" data-url="<?= htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '').'/form.php?k='.$f['url_key']) ?>" id="qrcell_<?= $f['id'] ?>" style="display:inline-block;cursor:pointer;border:1px solid #ddd;border-radius:3px;padding:2px;background:#fff;line-height:0"></div>
        </td>
        <td class="<?= $closed ? 'status-off' : 'status-on' ?>"><?= $closed ? '已停止' : '收集中' ?></td>
        <td><?= $count ?><?= $max ? '/'.$max : '' ?></td>
        <td style="font-size:11px;color:#666">
          <?= $f['start_time'] ? date('m-d H:i', strtotime($f['start_time'])) : '未设' ?>
          ~
          <?= $f['end_time'] ? date('m-d H:i', strtotime($f['end_time'])) : '未设' ?>
        </td>
        <td>
          <a href="form_edit.php?id=<?= $f['id'] ?>" class="btn btn-default btn-sm">配置</a>
          <a href="submissions.php?fid=<?= $f['id'] ?>" class="btn btn-default btn-sm">数据</a>
          <a href="../form.php?k=<?= htmlspecialchars($f['url_key']) ?>" class="btn btn-success btn-sm" target="_blank">预览</a>
          <a href="form_delete.php?id=<?= $f['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('删除后数据不可恢复，确定删除？')">删除</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-title">快捷操作</div>
  <a href="password.php" class="btn btn-default">🔑 修改密码</a>
</div>
</div>

<script>
(function(){
  var cells = document.querySelectorAll('.qr-cell');
  cells.forEach(function(el){
    var url = el.getAttribute('data-url');
    if (url && typeof QRCode !== 'undefined') {
      el.innerHTML = '';
      new QRCode(el, {
        text: url,
        width: 36,
        height: 36,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
      });
    }
  });
})();
</script>
</body>
</html>