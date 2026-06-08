<?php
/**
 * 终端客户修改已提交信息（方案B：一个修改码管理全家）
 * 访问方式：form_edit.php?k=表单url_key
 */
require_once __DIR__ . '/lib.php';
session_start();

$url_key = trim($_GET['k'] ?? '');
if (!$url_key) { http_response_code(403); echo '缺少表单标识'; exit; }

$form = get_form_by_url_key($url_key);
if (!$form) { http_response_code(404); echo '表单不存在或已下架'; exit; }

$fields_config = $form['fields_config'] ?? [];
$PRESET_KEYS = ['省','市','区','学校','筛查项目'];
$region_data = load_region_data();

$error = '';
$msg = '';
$family_subs = [];
$edit_code = '';
$selected_sub = null;

// ── 退出验证模式 ──
if (isset($_GET['logout'])) {
    unset($_SESSION['fe_phone'], $_SESSION['fe_edit_code'], $_SESSION['fe_form_id']);
    header('Location: form_edit.php?k=' . urlencode($url_key));
    exit;
}

// ── 读取Session ──
$saved_phone = $_SESSION['fe_phone'] ?? '';
$saved_edit_code = $_SESSION['fe_edit_code'] ?? '';
$in_session = ($saved_phone && $saved_edit_code);

// ── 处理删除操作（跳转前处理）──
if (!empty($_GET['del']) && $in_session) {
    $del_id = intval($_GET['del']);
    $del_sub = get_submission_by_id($del_id);
    if ($del_sub && $del_sub['edit_code'] === $saved_edit_code && $del_sub['form_id'] == $form['id']) {
        delete_submission($del_id);
        header('Location: form_edit.php?k=' . urlencode($url_key));
        exit;
    }
}

// ── 处理身份验证POST（跳转前处理）──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'verify') {
    $phone = trim($_POST['phone'] ?? '');
    $edit_code = trim($_POST['edit_code'] ?? '');
    if ($phone && $edit_code && preg_match('/^1[3-9]\d{9}$/', $phone) && strlen($edit_code) === 6 && ctype_digit($edit_code)) {
        $db = get_db();
        $stmt = $db->prepare('SELECT edit_code FROM family_codes WHERE form_id = :fid AND phone = :phone AND edit_code = :ec');
        $stmt->bindValue(':fid', $form['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':phone', $phone);
        $stmt->bindValue(':ec', $edit_code);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && $row['edit_code'] === $edit_code) {
            $_SESSION['fe_phone'] = $phone;
            $_SESSION['fe_edit_code'] = $edit_code;
            $_SESSION['fe_form_id'] = $form['id'];
            header('Location: form_edit.php?k=' . urlencode($url_key));
            exit;
        } else {
            $error = '手机号或修改码错误，请核对后重试';
        }
    } else {
        $error = '请填写正确的手机号和6位修改码';
    }
}

// ── 已登录：加载家庭所有提交 ──
if ($in_session) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid AND edit_code = :ec ORDER BY id DESC');
    $stmt->bindValue(':fid', $form['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':ec', $saved_edit_code);
    $result = $stmt->execute();
    while ($r = $result->fetchArray(SQLITE3_ASSOC)) {
        $r['data'] = json_decode($r['data'], true);
        $family_subs[] = $r;
    }
    $edit_code = $saved_edit_code;
}

// ── 选择要编辑的记录 ──
$select_id = 0;
if (!empty($_GET['sid'])) {
    $select_id = intval($_GET['sid']);
}
if ($select_id > 0 && $in_session) {
    $selected_sub = get_submission_by_id($select_id);
    if (!$selected_sub || $selected_sub['edit_code'] !== $saved_edit_code) {
        $selected_sub = null;
        $error = '记录不存在或无权修改';
    }
}

// ── 保存修改 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'save') {
    if (!$in_session) {
        $error = '会话已过期，请重新验证';
    } else {
        $sub_id = intval($_POST['sub_id'] ?? 0);
        $sub = get_submission_by_id($sub_id);
        if (!$sub || $sub['form_id'] != $form['id'] || $sub['edit_code'] !== $saved_edit_code) {
            $error = '记录不存在或无权修改';
        } else {
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
            if (!empty($form_data['电话']) && !preg_match('/^1[3-9]\d{9}$/', $form_data['电话'])) {
                $error = '请输入有效的11位手机号';
            }
            if (!empty($form_data['身份证号']) && !preg_match('/^[0-9Xx]{18}$/', $form_data['身份证号'])) {
                $error = '请输入有效的18位身份证号';
            }
            if (empty($error)) {
                update_submission($sub_id, $form_data);
                $msg = '修改成功';
                $selected_sub = get_submission_by_id($sub_id);
                // 刷新列表
                $db = get_db();
                $stmt2 = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid AND edit_code = :ec ORDER BY id DESC');
                $stmt2->bindValue(':fid', $form['id'], SQLITE3_INTEGER);
                $stmt2->bindValue(':ec', $saved_edit_code);
                $result = $stmt2->execute();
                $family_subs = [];
                while ($r = $result->fetchArray(SQLITE3_ASSOC)) {
                    $r['data'] = json_decode($r['data'], true);
                    $family_subs[] = $r;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>修改已提交信息 - <?= htmlspecialchars($form['name']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.edit-wrap { max-width: 700px; margin: 40px auto; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; }
.card-title { padding: 18px 24px; border-bottom: 1px solid #f0f0f0; font-size: 16px; font-weight: 600; color: #1f1f1f; }
.card-body { padding: 24px; }
.form-row { margin-bottom: 16px; }
.form-row label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; color: #333; }
.form-row input, .form-row select { width: 100%; padding: 10px 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.form-row input:focus, .form-row select:focus { border-color: #1890ff; outline: none; }
.btn-primary { background: #1890ff; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-size: 15px; cursor: pointer; width: 100%; }
.btn-primary:hover { background: #096dd9; }
.btn-secondary { background: #fff; color: #1890ff; border: 1px solid #1890ff; padding: 10px 24px; border-radius: 6px; font-size: 15px; cursor: pointer; width: 100%; margin-top: 8px; }
.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
.alert-error { background: #fff1f0; border: 1px solid #ffccc7; color: #ff4d4f; }
.alert-success { background: #f6ffed; border: 1px solid #b7eb8f; color: #52c41a; }
.info-box { background: #f0f7ff; border: 1px solid #91d5ff; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #666; margin-bottom: 20px; }
.preset-field { background: #f5f5f5; color: #999; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.sub-list { border: 1px solid #e8e8e8; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
.sub-item-wrap { display: flex; align-items: center; border-bottom: 1px solid #f0f0f0; }
.sub-item-wrap:last-child { border-bottom: none; }
.sub-item { flex: 1; padding: 14px 16px; cursor: pointer; transition: background 0.2s; text-decoration: none; color: inherit; display: block; }
.sub-item:hover { background: #f0f7ff; }
.sub-item .sub-name { font-size: 15px; font-weight: 600; color: #333; }
.sub-item .sub-class { font-size: 13px; color: #666; margin-top: 2px; }
.sub-item .sub-time { font-size: 12px; color: #999; margin-top: 2px; }
.btn-del { padding: 14px 16px; color: #ff4d4f; font-size: 16px; text-decoration: none; flex-shrink: 0; }
.btn-del:hover { color: #ff7875; }
.back-link { display: block; text-align: center; padding: 12px; color: #1890ff; font-size: 14px; text-decoration: none; }
.back-link:hover { text-decoration: underline; }
.header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="edit-wrap">
<div class="card">
  <div class="card-title">✏️ 修改已提交的信息</div>
  <div class="card-body">

<?php if (!empty($msg)): ?>
<div class="alert alert-success">✓ <?= $msg ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-error">✗ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($selected_sub): ?>
    <!-- 编辑界面 -->
    <div class="info-box" style="margin-bottom:20px">
      当前修改：<strong><?= htmlspecialchars($selected_sub['data']['姓名'] ?? '未知') ?></strong>
      （<?= htmlspecialchars(($selected_sub['data']['年级'] ?? '') . ' ' . ($selected_sub['data']['班级'] ?? '')) ?>）
      <span style="color:#ff4d4f;font-size:12px;margin-left:8px">修改码：<?= htmlspecialchars($edit_code) ?>（全家共用）</span>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="sub_id" value="<?= $selected_sub['id'] ?>">
      <div class="form-grid">
      <?php foreach ($fields_config as $field_key => $conf): ?>
      <?php $is_preset = in_array($field_key, $PRESET_KEYS); ?>
      <?php $current_val = $selected_sub['data'][$field_key] ?? ''; ?>

      <?php if (in_array($field_key, ['省','市','区','学校','筛查项目'])): ?>
      <div class="form-row">
        <label>● <?= htmlspecialchars($field_key) ?>（预置·不可改）</label>
        <input type="text" value="<?= htmlspecialchars($fields_config[$field_key]['value'] ?? '') ?>" class="preset-field" readonly>
      </div>
      <?php elseif ($field_key === '年级'): ?>
      <div class="form-row">
        <label><?= htmlspecialchars($field_key) ?> <span style="color:#ff4d4f">*</span></label>
        <select name="年级" required>
          <option value="">请选择年级</option>
          <?php foreach (($fields_config['年级']['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($field_key === '班级'): ?>
      <div class="form-row">
        <label><?= htmlspecialchars($field_key) ?> <span style="color:#ff4d4f">*</span></label>
        <select name="班级" required>
          <option value="">请选择班级</option>
          <?php foreach (($fields_config['班级']['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($field_key === '性别'): ?>
      <div class="form-row">
        <label>学生性别 <span style="color:#ff4d4f">*</span></label>
        <select name="性别" required>
          <option value="">请选择</option>
          <?php foreach (($fields_config['性别']['options'] ?? ['男','女']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($field_key === '电话'): ?>
      <div class="form-row">
        <label>监护人电话</label>
        <input type="tel" name="电话" value="<?= htmlspecialchars($current_val) ?>" maxlength="11">
      </div>
      <?php elseif ($field_key === '出生日期'): ?>
      <div class="form-row">
        <label>学生出生日期</label>
        <input type="text" name="出生日期" value="<?= htmlspecialchars($current_val) ?>" placeholder="如：2015-03-15">
      </div>
      <?php elseif ($field_key === '身份证号'): ?>
      <div class="form-row">
        <label>学生身份证号</label>
        <input type="text" name="身份证号" value="<?= htmlspecialchars($current_val) ?>" maxlength="18">
      </div>
      <?php elseif ($field_key === '姓名'): ?>
      <div class="form-row">
        <label>学生姓名 <span style="color:#ff4d4f">*</span></label>
        <input type="text" name="姓名" value="<?= htmlspecialchars($current_val) ?>" required>
      </div>
      <?php elseif ($conf['type'] === 'select'): ?>
      <div class="form-row">
        <label><?= htmlspecialchars($field_key) ?></label>
        <select name="<?= htmlspecialchars($field_key) ?>">
          <option value="">请选择</option>
          <?php foreach (($fields_config[$field_key]['options'] ?? $conf['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $current_val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div class="form-row">
        <label><?= htmlspecialchars($field_key) ?></label>
        <input type="text" name="<?= htmlspecialchars($field_key) ?>" value="<?= htmlspecialchars($current_val) ?>">
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
      </div>
      <div style="margin-top:20px">
        <button type="submit" class="btn-primary">💾 保存修改</button>
      </div>
    </form>
    <a href="form_edit.php?k=<?= htmlspecialchars($url_key) ?>" class="back-link">← 返回记录列表</a>

<?php elseif (!empty($family_subs)): ?>
    <!-- 记录列表 -->
    <div class="header-bar">
      <div class="info-box" style="flex:1;margin:0">
        您的家庭共 <?= count($family_subs) ?> 条提交记录，请选择要修改的：
      </div>
      <a href="form_edit.php?k=<?= htmlspecialchars($url_key) ?>&logout=1" style="margin-left:12px;color:#999;font-size:12px;text-decoration:none">退出</a>
    </div>
    <div class="sub-list">
      <?php foreach ($family_subs as $s): ?>
      <div class="sub-item-wrap">
        <a href="form_edit.php?k=<?= htmlspecialchars($url_key) ?>&sid=<?= $s['id'] ?>" class="sub-item">
          <div class="sub-name"><?= htmlspecialchars($s['data']['姓名'] ?? '未填姓名') ?></div>
          <div class="sub-class"><?= htmlspecialchars(($s['data']['年级'] ?? '') . ' ' . ($s['data']['班级'] ?? '')) ?></div>
          <div class="sub-time">提交时间：<?= htmlspecialchars($s['submit_time']) ?></div>
        </a>
        <a href="form_edit.php?k=<?= htmlspecialchars($url_key) ?>&del=<?= $s['id'] ?>" class="btn-del" onclick="return confirm('确定删除「<?= htmlspecialchars(addslashes($s['data']['姓名'] ?? '该记录')) ?>」？此操作不可恢复！')" title="删除此记录">🗑️</a>
      </div>
      <?php endforeach; ?>
    </div>
    <a href="form.php?k=<?= htmlspecialchars($url_key) ?>" class="back-link">← 返回表单填写页</a>

<?php else: ?>
    <!-- 验证身份 -->
    <div class="info-box">
      请输入您提交时填写的<strong>手机号</strong>和提交成功后显示的<strong>修改码</strong>。<br>
      ⚠️ 一个修改码可管理该手机号提交的学生信息。
    </div>
    <form method="post">
      <input type="hidden" name="action" value="verify">
      <div class="form-row">
        <label>手机号</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="请输入11位手机号" maxlength="11" required>
      </div>
      <div class="form-row">
        <label>修改码</label>
        <input type="text" name="edit_code" value="" placeholder="6位数字修改码" maxlength="6" pattern="[0-9]{6}" required>
      </div>
      <button type="submit" class="btn-primary">验证身份</button>
    </form>
<?php endif; ?>

  </div>
</div>
</div>
</body>
</html>