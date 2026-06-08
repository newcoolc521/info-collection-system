<?php
require_once __DIR__ . '/../lib.php';
require_role('admin');

$msg = '';
$error = '';

// 处理：设置脊柱库根目录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'set_root') {
        $path = trim($_POST['spine_db_root'] ?? '');
        if ($path && !is_dir($path)) {
            $error = '目录不存在或无权限访问：' . htmlspecialchars($path);
        } else {
            set_spine_db_root($path);
            $msg = '脊柱库根目录已保存';
        }
    }
    // 处理：添加登记
    if ($_POST['action'] === 'add') {
        $orgName = trim($_POST['org_name'] ?? '');
        $dbPath = trim($_POST['db_path'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        if ($orgName && $dbPath) {
            add_spine_database($orgName, $dbPath, $remark);
            $msg = '已登记：' . htmlspecialchars($orgName);
        } else {
            $error = '机构名称和路径不能为空';
        }
    }
    // 处理：删除登记
    if ($_POST['action'] === 'delete' && isset($_POST['del_id'])) {
        delete_spine_database(intval($_POST['del_id']));
        $msg = '已删除';
    }
       // 处理：扫描并自动发现
    if ($_POST['action'] === 'scan') {
        $found = scan_spine_databases();
        $added = 0;
        foreach ($found as $f) {
            if (!spine_database_exists_by_path($f['db_path'])) {
                add_spine_database($f['org_name'], $f['db_path'], '');
                $added++;
            }
        }
        $msg = "扫描完成，发现 {$added} 个新脊柱库";
        set_last_auto_scan();
    }
}

$spineRoot = get_spine_db_root();
$registeredDBs = get_spine_databases();
$lastScanTime = get_last_auto_scan();
$autoScanEnabled = !empty($spineRoot);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>脊柱筛查库管理</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.db-card { background: #fafafa; border: 1px solid #e8e8e8; border-radius: 8px; padding: 16px; }
.db-card .org-name { font-weight: 700; font-size: 16px; color: #262626; margin-bottom: 6px; }
.db-card .db-path { font-size: 12px; color: #999; word-break: break-all; margin-bottom: 6px; }
.db-card .remark { font-size: 12px; color: #666; margin-bottom: 8px; }
.scan-hint { background: #f0f7ff; border: 1px solid #91d5ff; border-radius: 6px; padding: 12px 16px; font-size: 13px; margin-bottom: 16px; color: #333; }
</style>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<div style="margin:20px">

<?php if ($msg): ?>
<div class="alert alert-success">✓ <?= $msg ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">✗ <?= $error ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">⚙️ 脊柱库根目录配置</div>
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="action" value="set_root">
    <input type="text" name="spine_db_root" value="<?= htmlspecialchars($spineRoot) ?>"
      placeholder="例如：D:\spine_db\ 或 /var/www/spine_db/" style="flex:1;min-width:200px">
    <button type="submit" class="btnbtn-primary">💾 保存路径</button>
  </form>
  <div style="font-size:12px;color:#999;margin-top:6px">
    设置脊柱筛查数据库所在文件夹的根目录（脊柱库应放在子文件夹中，每个子文件夹包含 SpinalMeasurement_Pro.db）
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">🔍 扫描发现脊柱库</div>
  <div class="scan-hint">
    点击「扫描」后，系统会自动扫描根目录下的所有子文件夹，读取每个脊柱库中的机构名称，并列出发现的脊柱库。<br>
    <strong>注意：</strong>脊柱库文件必须命名为<code>SpinalMeasurement_Pro.db</code>
  </div>
  <form method="post">
    <input type="hidden" name="action" value="scan">
    <button type="submit" class="btn btn-primary">🔍 扫描脊柱库目录</button>
    <span style="font-size:12px;color:#999;margin-left:12px">
      根目录：<code><?= htmlspecialchars($spineRoot ?: '（未设置）') ?></code>
    </span>
  </form>
</div>

<div class="card" style="margin-bottom:20px;background:#fafafa">
  <div class="card-title">⚡ 自动扫描状态</div>
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div style="font-size:13px">
      <span style="color:#8c8c8c">上次扫描：</span>
      <span id="lastScanDisplay"><?= $lastScanTime ? htmlspecialchars($lastScanTime) : '从未扫描' ?></span>
    </div>
    <div style="font-size:13px">
      <span style="color:#8c8c8c8c8c8c">自动扫描：</span>
      <span id="autoStatus" style="color:#52c41a;font-weight:600">● 已启用（每1分钟自动扫描）</span>
    </div>
    <div style="font-size:12px;color:#999">
      提示：也可以使用 Windows任务计划程序定时执行 <code>auto_scan.php</code> 实现真正的后台自动扫描
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">➕ 手动登记脊柱库</div>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="min-width:180px">
      <label>机构名称</label>
      <input type="text" name="org_name" placeholder="如：明玮体检中心" required>
    </div>
    <div class="form-group" style="flex:1;min-width:220px">
      <label>文件夹路径</label>
      <input type="text" name="db_path" placeholder="如：D:\spine_db\测试公司" required>
    </div>
    <div class="form-group" style="min-width:150px">
      <label>备注</label>
      <input type="text" name="remark" placeholder="如：对应哪个表单">
    </div>
    <button type="submit" class="btn btn-primary">➕登记</button>
  </form>
</div>

<div class="card">
  <div class="card-title">📋 已登记的脊柱库（<?= count($registeredDBs) ?> 个）</div>
  <?php if (empty($registeredDBs)): ?>
  <div class="empty-state">
    <p>暂无已登记的脊柱库，请先设置根目录并扫描，或手动登记。</p>
  </div>
  <?php else: ?>
  <div class="card-grid">
    <?php foreach ($registeredDBs as $db): ?>
    <div class="db-card">
      <div class="org-name">🏥 <?= htmlspecialchars($db['org_name']) ?></div>
      <div class="db-path">📁 <?= htmlspecialchars($db['db_path']) ?></div>
      <?php if ($db['remark']): ?>
      <div class="remark">备注：<?= htmlspecialchars($db['remark']) ?></div>
      <?php endif; ?>
      <div style="font-size:11px;color:#999;margin-bottom:8px">
        登记时间：<?= htmlspecialchars($db['created_at']) ?>
      </div>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="del_id" value="<?= $db['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm"
          onclick="return confirm('确定删除「<?= htmlspecialchars($db['org_name']) ?>」的登记？')">🗑️ 删除</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div>
</body>
</html>