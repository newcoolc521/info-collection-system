<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$cfg = get_user_config($uid);

// 默认字段（与原系统一致）
$all_fields = ['省','市','区','学校','年级','班级','姓名','性别','筛查项目','电话','出生日期','学籍号'];
$enabled_fields = $cfg['fields'] ?: $all_fields;

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg['active'] = isset($_POST['active']);
    $cfg['start_time'] = $_POST['start_time'] ?? '';
    $cfg['end_time'] = $_POST['end_time'] ?? '';
    $cfg['max_count'] = intval($_POST['max_count'] ?? 0);
    $cfg['fields'] = isset($_POST['fields']) ? array_values($_POST['fields']) : $all_fields;
    save_user_config($uid, $cfg);
    $msg = '配置已保存';
    $enabled_fields = $cfg['fields'];
}

$region_count = count(load_region_data());
$grades = json_decode(file_get_contents(__DIR__ . '/../data_source/grades.json'), true) ?: [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>表单配置</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<?php if ($msg): ?>
<div class="alert alert-success" style="margin:20px 20px 0">✓ <?= $msg ?></div>
<?php endif; ?>

<div class="card" style="margin:20px">
  <div class="card-title">📋 表单字段配置</div>
  <form method="post" id="cfgForm">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group">
        <label>采集状态</label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
          <input type="checkbox" name="active" value="1" <?= $cfg['active'] ? 'checked' : '' ?>>
          开启信息采集（开启后方可接收数据）
        </label>
      </div>
      <div class="form-group">
        <label>采集开始时间</label>
        <input type="datetime-local" name="start_time" value="<?= $cfg['start_time'] ? date('Y-m-d\TH:i', strtotime($cfg['start_time'])) : '' ?>">
      </div>
      <div class="form-group">
        <label>采集结束时间</label>
        <input type="datetime-local" name="end_time" value="<?= $cfg['end_time'] ? date('Y-m-d\TH:i', strtotime($cfg['end_time'])) : '' ?>">
      </div>
    </div>
    <div class="form-group" style="max-width:300px;margin-top:16px">
      <label>采集人数上限（0或不填 = 不限）</label>
      <input type="number" name="max_count" min="0" value="<?= $cfg['max_count'] ?:0 ?>">
    </div>

    <div class="card-title" style="margin-top:24px">字段配置说明</div>
    <div style="background:#f0f7ff;border:1px solid #91d5ff;border-radius:6px;padding:16px;margin-bottom:16px;font-size:13px;color:#333">
      <p>✅<strong>勾选</strong>：终端客户可填写（提交时必填字段需填写）</p>
      <p>❌ <strong>取消勾选</strong>：终端客户可查看但不可修改（表单隐藏该字段）</p>
      <p style="margin-top:8px;color:#999">省/市/区三级联动，数据来源于 xlsx 中的省市区数据源表单</p>
    </div>

    <div class="form-config-grid">
      <?php foreach ($all_fields as $f): ?>
      <div class="field-item">
        <input type="checkbox" name="fields[]" value="<?= htmlspecialchars($f) ?>" id="f_<?= md5($f) ?>" <?= in_array($f, $enabled_fields) ? 'checked' : '' ?>>
        <label class="field-desc" for="f_<?= md5($f) ?>"><?= htmlspecialchars($f) ?></label>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card-title" style="margin-top:24px">数据源</div>
    <div style="display:flex;gap:24px;font-size:13px;color:#666;flex-wrap:wrap">
      <span>📍 省市区：<?= $region_count ?> 个省级行政区域</span>
     <span>📚 年级：<?= count($grades) ?> 个选项</span>
     <span>📋 班级：系统内置班级列表</span>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:24px">💾 保存配置</button>
  </form>
</div>
</div>
</body>
</html>