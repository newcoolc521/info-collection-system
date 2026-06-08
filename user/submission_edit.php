<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$sub_id = intval($_GET['id'] ?? 0);
$sub = get_submission_by_id($sub_id);
if (!$sub) {
    die('记录不存在');
}

$form = get_form_by_id($sub['form_id']);
if (!$form || $form['username'] !== $uid) {
    die('无权操作此记录');
}

$fields_config = $form['fields_config'] ?? [];
$field_keys = array_keys($fields_config);
$PRESET_KEYS = ['省','市','区','学校','筛查项目'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [];
    foreach ($PRESET_KEYS as $pk) {
        if (isset($fields_config[$pk])) {
            $form_data[$pk] = $fields_config[$pk]['value'] ?? '';
        }
    }
    foreach ($fields_config as $field_key => $conf) {
        if (in_array($field_key, $PRESET_KEYS)) continue;
        $val = $_POST[$field_key] ?? '';
        $form_data[$field_key] = is_array($val) ? implode(',', $val) : trim($val);
    }
    foreach ($fields_config as $field_key => $conf) {
        if (!empty($conf['required']) && empty($form_data[$field_key])) {
            $error = "「{$field_key}」为必填项";
        }
    }
    if (!empty($_POST['电话']) && !preg_match('/^1[3-9]\d{9}$/', $_POST['电话'])) {
        $error = '请输入有效的11位手机号';
    }
    if (!empty($_POST['身份证号']) && !preg_match('/^[0-9Xx]{18}$/', $_POST['身份证号'])) {
        $error = '请输入有效的18位身份证号';
    }
    if (empty($error)) {
        update_submission($sub_id, $form_data);
        $msg = '修改成功';
        $sub = get_submission_by_id($sub_id); // 刷新
    }
}

$cfg = $form['fields_config'] ?? [];
$region_data = load_region_data();
$saved_province = $cfg['省']['value'] ?? '';
$saved_city = $cfg['市']['value'] ?? '';
$saved_district = $cfg['区']['value'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>编辑数据 - <?= htmlspecialchars($form['name']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 12px; }
.form-group label { font-weight: 600; font-size: 14px; margin-bottom: 6px; display: block; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.preset-field { background: #f5f5f5; color: #999; }
.preset-label { color: #1890ff; font-size: 12px; }
</style>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<div class="card" style="margin:20px">
  <div class="card-title">✏️ 编辑数据</div>

  <?php if (!empty($msg)): ?>
  <div class="alert alert-success" style="margin:0 20px 16px">✓ <?= $msg ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <div class="alert alert-error" style="margin:0 20px 16px">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" style="padding:0 20px 20px">
    <div class="form-grid">
      <?php foreach ($fields_config as $field_key => $conf): ?>
      <?php $is_preset = in_array($field_key, $PRESET_KEYS); ?>
      <?php $current_val = $sub['data'][$field_key] ?? ''; ?>

      <?php if ($field_key === '省'): ?>
      <div class="form-group">
        <label>● 省（预置）</label>
        <input type="text" value="<?= htmlspecialchars($cfg['省']['value'] ?? '') ?>" class="preset-field" readonly>
      </div>

      <?php elseif ($field_key === '市'): ?>
      <div class="form-group">
        <label>● 市（预置）</label>
        <input type="text" value="<?= htmlspecialchars($cfg['市']['value'] ?? '') ?>" class="preset-field" readonly>
      </div>

      <?php elseif ($field_key === '区'): ?>
      <div class="form-group">
        <label>● 区（预置）</label>
        <input type="text" value="<?= htmlspecialchars($cfg['区']['value'] ?? '') ?>" class="preset-field" readonly>
      </div>

      <?php elseif ($field_key === '学校'): ?>
      <div class="form-group">
        <label>● 学校名称（预置）</label>
        <input type="text" value="<?= htmlspecialchars($cfg['学校']['value'] ?? '') ?>" class="preset-field" readonly>
      </div>

      <?php elseif ($field_key === '筛查项目'): ?>
      <div class="form-group">
        <label>● 筛查项目（预置）</label>
        <input type="text" value="<?= htmlspecialchars($cfg['筛查项目']['value'] ?? '') ?>" class="preset-field" readonly>
      </div>

      <?php elseif ($field_key === '年级'): ?>
      <div class="form-group">
        <label>年级 <span class="required">*</span></label>
        <select name="年级" required>
          <option value="">请选择年级</option>
          <?php foreach (($cfg['年级']['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php elseif ($field_key === '班级'): ?>
      <div class="form-group">
        <label>班级 <span class="required">*</span></label>
        <select name="班级" required>
          <option value="">请选择班级</option>
          <?php foreach (($cfg['班级']['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php elseif ($field_key === '性别'): ?>
      <div class="form-group">
        <label>性别 <span class="required">*</span></label>
        <select name="性别" required>
          <option value="">请选择</option>
          <?php foreach (($cfg['性别']['options'] ?? ['男','女']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php elseif ($field_key === '电话'): ?>
      <div class="form-group">
        <label>联系电话</label>
        <input type="text" name="电话" value="<?= htmlspecialchars($current_val) ?>" placeholder="11位手机号" maxlength="11">
      </div>

      <?php elseif ($field_key === '出生日期'): ?>
      <div class="form-group">
        <label>出生日期</label>
        <input type="text" name="出生日期" value="<?= htmlspecialchars($current_val) ?>" placeholder="如：2015-03-15">
      </div>

      <?php elseif ($field_key === '身份证号'): ?>
      <div class="form-group">
        <label>身份证号</label>
        <input type="text" name="身份证号" value="<?= htmlspecialchars($current_val) ?>" placeholder="18位身份证号" maxlength="18">
      </div>

      <?php elseif ($conf['type'] === 'select'): ?>
      <div class="form-group">
        <label><?= htmlspecialchars($field_key) ?> <span class="required">*</span></label>
        <select name="<?= htmlspecialchars($field_key) ?>" required>
          <option value="">请选择</option>
          <?php foreach (($cfg[$field_key]['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php else: ?>
      <div class="form-group">
        <label><?= htmlspecialchars($field_key) ?></label>
        <input type="text" name="<?= htmlspecialchars($field_key) ?>" value="<?= htmlspecialchars($current_val) ?>">
      </div>
      <?php endif; ?>

      <?php endforeach; ?>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 保存修改</button>
      <a href="submissions.php?fid=<?= $form['id'] ?>" class="btn btn-default">← 返回列表</a>
    </div>
  </form>
</div>
</div>
</body>
</html>