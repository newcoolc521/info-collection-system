<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$form = $form_id > 0 ? get_form_by_id($form_id) : null;

if ($form && $form['username'] !== $uid) {
    redirect('index.php');
}

$msg = '';

// 字段定义
$FIELD_DEFS = [
    '省'        => ['name'=>'省',           'type'=>'province', 'options'=>get_provinces()],
    '市'        => ['name'=>'市',           'type'=>'city',       'options'=>[]],
    '区'        => ['name'=>'区',           'type'=>'district',   'options'=>[]],
    '学校'      => ['name'=>'学校名称',     'type'=>'text',       'options'=>[]],
    '年级'      => ['name'=>'年级',         'type'=>'select',     'options'=>['一年级','二年级','三年级','四年级','五年级','六年级','初一','初二','初三','高一','高二','高三','大一','大二','大三','大四']],
    '班级'      => ['name'=>'班级',         'type'=>'select',     'options'=>['一班','二班','三班','四班','五班','六班','七班','八班','九班','十班','十一班','十二班','十三班','十四班','十五班','十六班','十七班','十八班','十九班','二十班','二十一班','二十二班','二十三班','二十四班','二十五班','二十六班','二十七班','二十八班','二十九班','三十班','三十一班','三十二班','三十三班','三十四班','三十五班','三十六班','三十七班','三十八班','三十九班','四十班','四十一班','四十二班','四十三班','四十四班','四十五班','四十六班','四十七班','四十八班','四十九班','五十班']],
    '姓名'      => ['name'=>'学生姓名',       'type'=>'text',       'options'=>[]],
    '性别'      => ['name'=>'学生性别',       'type'=>'select',     'options'=>['男','女'],     'fixed_opts'=>true],
    '筛查项目'  => ['name'=>'筛查项目',     'type'=>'select',     'options'=>['侧弯','后凸','侧弯&后凸'], 'fixed_opts'=>true],
    '电话'      => ['name'=>'监护人电话',   'type'=>'text',       'options'=>[]],
    '出生日期'  => ['name'=>'学生出生日期', 'type'=>'date',       'options'=>[]],
    '身份证号'  => ['name'=>'学生身份证号','type'=>'text',       'options'=>[]],
];

$PRESET_KEYS = ['省','市','区','学校','筛查项目'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '') ?: '未命名';

    $fields_config = [];
    $custom_options = isset($_POST['custom_options']) ? $_POST['custom_options'] : [];
    $error = '';

    foreach ($FIELD_DEFS as $key => $def) {
        $is_preset = in_array($key, $PRESET_KEYS);
        $opts = $def['options'];


        // 非预置字段支持自定义选项
        if (!empty($custom_options[$key]) && !$is_preset) {
            $opts = array_filter(array_map('trim', explode("\n", $custom_options[$key])));
        }

        $preset_val = '';
        if ($key === '省')        $preset_val = $_POST['preset_province'] ?? '';
        if ($key === '市')        $preset_val = $_POST['preset_city'] ?? '';
        if ($key === '区')        $preset_val = $_POST['preset_district'] ?? '';
        if ($key === '学校')      $preset_val = $_POST['preset_text_学校'] ?? '';
        if ($key === '筛查项目')  $preset_val = $_POST['preset_select_筛查项目'] ?? '';

        // 预置字段必须填写
        if (in_array($key, $PRESET_KEYS) && $preset_val === '') {
            $error = "「{$def['name']}」为必填项，请到表单字段配置中填写完整";
        }

        $fields_config[$key] = [
            'type'     => $def['type'],
            'required' => 1,
            'options'  => $opts,
            'value'    => $preset_val,
        ];
    }

    if (!$error) {
        $data = [
            'name'           => $name,
            'active'         => isset($_POST['active']) ? 1 : 0,
            'start_time'     => $form ? ($_POST['start_time'] ?? '') : date('Y-m-d H:i'),
            'end_time'       => $_POST['end_time'] ?? '',
            'max_count'      => intval($_POST['max_count'] ?? 0),
            'fields_config'  => $fields_config,
            'spine_db_path'  => $_POST['spine_db_path'] ?? '',
        ];

        if ($form) {
            update_form($form['id'], $data);
            $msg = '配置已保存';
            $form = get_form_by_id($form['id']);
        } else {
            $form_id = create_form($uid, $name);
            update_form($form_id, $data);
            $msg = '表单已创建';
            $form = get_form_by_id($form_id);
        }
    }
}

$cfg = $form ? ($form['fields_config'] ?? []) : [];
$region_data = load_region_data();

// 用于JS联动：当前保存的省/市/区值
$saved_province = $cfg['省']['value'] ?? '';
$saved_city = $cfg['市']['value'] ?? '';
$saved_district = $cfg['区']['value'] ?? '';

// V2.0 加载脊柱库列表
$spineDBList = get_spine_databases();
$currentSpinePath = $form['spine_db_path'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $form ? '配置表单：'.htmlspecialchars($form['name']) : '新建表单' ?></title>
<link rel="stylesheet" href="../assets/style.css">
<script src="../assets/qrcodejs.js"></script>
<style>
.config-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.tips-box { background: #f0f7ff; border: 1px solid #91d5ff; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; color: #333; }
.field-block { border: 1px solid #e8e8e8; border-radius: 8px; padding: 16px; margin-bottom: 12px; background: #fafafa; }
.field-block.preset { border-color: #91d5ff; background: #f0f7ff; }
.field-block label { font-weight: 600; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.field-options-textarea { width: 100%; min-height: 60px; margin-top: 6px; font-size: 13px; }
.preset-note { font-size: 11px; color: #1890ff; margin-left: 4px; }
</style>
</head>
<body>
<?php include '__nav.php'; ?>
<div class="main">
<?php if ($msg): ?>
<div class="alert alert-success" style="margin:20px 20px 0">✓ <?= $msg ?>
<?php if ($form): ?>
 — <a href="../form.php?k=<?= htmlspecialchars($form['url_key']) ?>" target="_blank" style="font-weight:600">查看终端表单</a>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-error" style="margin:20px 20px 0">✗ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
<div class="card" style="margin:20px">
  <div class="card-title"><?= $form ? '配置表单：'.htmlspecialchars($form['name']) : '新建表单' ?></div>

  <?php if ($form): ?>
  <?php
    $formUrl = (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '') . '/form.php?k=' . $form['url_key'];
  ?>
  <div style="margin-bottom:16px;background:#fff3e0;border:1px solid #ffb74d;border-radius:6px;padding:12px 16px;font-size:13px">
    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <strong>采集链接：</strong>
        <a href="../form.php?k=<?= htmlspecialchars($form['url_key']) ?>" target="_blank" style="word-break:break-all">
          <?= htmlspecialchars($formUrl) ?>
        </a>
        <br><span style="color:#999">将此链接发送给终端客户填写</span>
      </div>
      <div style="text-align:center">
        <div id="qrcode_preview" style="display:inline-block;border:1px solid #ffb74d;border-radius:4px;padding:8px;background:#fff;line-height:0"></div>
        <br><span style="font-size:11px;color:#ff9800">扫码填写表单</span>
      </div>
    </div>
  </div>

  <?php
    $endTime = $form['end_time'] ?? '';
    $endTimeDisplay = '';
    if ($endTime) {
      $ts = strtotime($endTime);
      $endTimeDisplay = date('Y年m月d日H时i分', $ts);
    }
    $noticeText = "尊敬的家长的朋友们，为了学生们的骨骼健康，我校将开展脊柱健康筛查活动，请扫描二维码或者访问{$formUrl}，登记学生相关信息。信息登记入口将于{$endTimeDisplay}关闭，务必在关闭前登记完毕。信息提交后将修改码妥善保存，方便随时更正学生信息。";
  ?>
  <div style="margin-top:12px;background:#f6ffed;border:1px solid #b7eb8f;border-radius:6px;padding:12px 16px;font-size:13px">
    <div style="font-weight:600;margin-bottom:8px;color:#52c41a">📢 采集通知语（可复制发送给家长）</div>
    <textarea id="notice_text" readonly style="width:100%;min-height:100px;resize:vertical;font-size:13px;line-height:1.7;border:1px solid #d9d9d9;border-radius:4px;padding:10px;color:#262626;box-sizing:border-box;background:#fff;font-family:inherit"><?= htmlspecialchars($noticeText) ?></textarea>
    <div style="margin-top:8px;text-align:right">
      <button type="button" class="btn btn-default btn-sm" onclick="copyNotice()">📋 复制通知语</button>
      <span id="copy_tip" style="color:#52c41a;font-size:12px;margin-left:8px;display:none">✓ 已复制</span>
    </div>
  </div>
  <?php endif; ?>

<script>
(function(){
  var el = document.getElementById('qrcode_preview');
  if (el && typeof QRCode !== 'undefined') {
    el.innerHTML = '';
    new QRCode(el, {
      text: <?= json_encode($formUrl) ?>,
      width: 120,
      height: 120,
      colorDark : '#000000',
      colorLight : '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });
  }
})();

function copyNotice() {
  var ta = document.getElementById('notice_text');
  if (!ta) return;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(ta.value).then(function(){
      showCopyTip();
    }).catch(function(){
      fallbackCopy(ta);
    });
  } else {
    fallbackCopy(ta);
  }
}

function fallbackCopy(el) {
  el.select();
  el.setSelectionRange(0, 99999);
  try { document.execCommand('copy'); showCopyTip(); } catch(e){}
}

function showCopyTip() {
  var tip = document.getElementById('copy_tip');
  if (tip) { tip.style.display = 'inline'; setTimeout(function(){ tip.style.display = 'none'; }, 2000); }
}
</script>

  <div class="config-grid">
    <div class="form-group">
      <label>表单名称</label>
      <input type="text" name="name" value="<?= htmlspecialchars($form['name'] ?? '') ?>" placeholder="如：脊柱筛查信息采集表">
    </div>
    <div class="form-group">
      <label>采集状态</label>
      <div style="padding:8px 0">
        <input type="checkbox" name="active" value="1" id="active_chk" <?= !empty($form['active']) ? 'checked' : '' ?>>
        <label for="active_chk" style="font-weight:normal;margin-left:6px">开启信息采集</label>
      </div>
      <div style="font-size:12px;color:#666;margin-top:4px">
        ✓ 开启：终端客户可提交表单<br>
        ✗ 关闭：终端客户看到"采集已关闭"提示
      </div>
    </div>
  </div>

  <div class="config-grid" style="margin-top:16px">
    <div class="form-group">
      <label>采集开始时间</label>
      <input type="text" value="<?= $form ? date('Y-m-d H:i', strtotime($form['start_time'])) : date('Y-m-d H:i') ?>" readonly style="background:#f5f5f5;color:#999">
      <div style="font-size:11px;color:#999;margin-top:2px">新建时自动设为当前时间</div>
    </div>
    <div class="form-group">
      <label>采集结束时间</label>
      <input type="datetime-local" name="end_time" value="<?= $form['end_time'] ? date('Y-m-d\TH:i', strtotime($form['end_time'])) : '' ?>">
    </div>
  </div>

  <div class="form-group" style="max-width:300px;margin-top:16px">
    <label>采集人数上限（0或不填 = 不限）</label>
    <input type="number" name="max_count" min="0" value="<?= $form['max_count'] ?? 0 ?>">
  </div>
  <div class="form-group" style="max-width:400px;margin-top:16px">
    <label>🦴 绑定脊柱筛查库（可选）</label>
    <select name="spine_db_path">
      <option value="">-- 不绑定脊柱库 --</option>
      <?php foreach ($spineDBList as $sd): ?>
      <option value="<?= htmlspecialchars($sd['db_path']) ?>" <?= ($currentSpinePath === $sd['db_path']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($sd['org_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <div style="font-size:11px;color:#999;margin-top:4px">
      请在「管理后台→脊柱库管理」中先登记脊柱库后再选择绑定
    </div>
  </div>
</div>

<div class="card" style="margin:0 20px 20px">
  <div class="card-title">📋 表单字段配置</div>
  <div class="tips-box">
    <p>📌 <strong>蓝色边框字段（预置字段）</strong>：由您在下方预先设定，终端客户只能查看，无法修改</p>
    <p>🔓 <strong>其他字段</strong>：终端客户自行填写（全部必填）</p>
    <p>📍 <strong>省/市/区</strong>：请在下方三级联动选择</p>
    <p>🏫 <strong>学校名称</strong>：请在下方填写学校全称</p>
   <p>🔬 <strong>筛查项目</strong>：请在下方选择一项</p>
  </div>

  <?php foreach ($FIELD_DEFS as $key => $def): ?>
  <?php
    $field_cfg = $cfg[$key] ?? null;
    $is_preset = in_array($key, $PRESET_KEYS);
    $current_val = $field_cfg['value'] ?? '';
  ?>
  <div class="field-block <?= $is_preset ? 'preset' : '' ?>">
    <label>
      <span><?= htmlspecialchars($def['name']) ?></span>
      <?php if ($is_preset): ?>
      <span class="preset-note">【预置·终端不可改】</span>
      <?php endif; ?>

      <?php if ($def['type'] === 'province' || $def['type'] === 'city' || $def['type'] === 'district'): ?>
      <span style="font-size:11px;background:#91d5ff;color:#fff;padding:1px 6px;border-radius:3px">三级联动</span>
      <?php elseif ($def['type'] === 'select'): ?>
      <span style="font-size:11px;background:#52c41a;color:#fff;padding:1px 6px;border-radius:3px">选项型</span>
      <?php else: ?>
      <span style="font-size:11px;background:#faad14;color:#fff;padding:1px 6px;border-radius:3px">文本型</span>
      <?php endif; ?>
    </label>

    <div style="margin-top:10px">
      <div style="margin-bottom:8px;font-size:12px;color:#1890ff">
        ← 此值将作为终端客户的预填值，不可修改
      </div>

      <?php if ($def['type'] === 'province'): ?>
      <div class="form-group">
        <label>选择省</label>
        <select name="preset_province" id="preset_province" onchange="onProvinceChange()">
          <option value="">请选择省</option>
          <?php foreach (array_keys($region_data) as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>" <?= ($current_val === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php elseif ($def['type'] === 'city'): ?>
      <div class="form-group">
        <label>选择市</label>
        <select name="preset_city" id="preset_city" onchange="onCityChange()">
          <option value="">请先选省</option>
        </select>
      </div>

      <?php elseif ($def['type'] === 'district'): ?>
      <div class="form-group">
        <label>选择区</label>
        <select name="preset_district" id="preset_district">
          <option value="">请先选市</option>
        </select>
      </div>

      <?php elseif ($key === '学校'): ?>
      <div class="form-group">
        <label>填写学校名称</label>
        <input type="text" name="preset_text_学校"
          value="<?= htmlspecialchars($current_val) ?>"
          placeholder="请输入学校全称，如：广州市第一中学"
          maxlength="100">
      </div>

      <?php elseif ($key === '筛查项目'): ?>
      <div class="form-group">
        <label>选择筛查项目</label>
        <select name="preset_select_筛查项目">
          <option value="">请选择</option>
          <?php foreach (($field_cfg['options'] ?? $def['options']) as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= ($current_val === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php elseif ($def['type'] === 'select'): ?>
      <?php if (!empty($def['fixed_opts'])): ?>
      <div style="font-size:12px;color:#888">选项（固定）：<?= htmlspecialchars(implode('、', $field_cfg['options'] ?? $def['options'])) ?></div>
      <?php else: ?>
      <div style="margin-bottom:6px">
        <label style="font-size:12px;color:#666">选项配置（每行一个，终端客户从中选择）：</label>
      </div>
      <textarea name="custom_options[<?= htmlspecialchars($key) ?>]"
        class="field-options-textarea"><?= htmlspecialchars(implode("\n", $field_cfg['options'] ?? $def['options'])) ?></textarea>
      <?php endif; ?>

      <?php else: ?>
      <div style="font-size:12px;color:#888">终端客户自由填写（文本输入）</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <button type="submit" class="btn btn-primary" style="margin-top:16px">💾 保存配置</button>
  <?php if ($form): ?>
  <a href="index.php" class="btn btn-default" style="margin-left:8px">返回列表</a>
  <?php endif; ?>
</div>
</form>

<script>
var regionData = <?= json_encode($region_data) ?>;
var savedProvince = '<?= htmlspecialchars($saved_province) ?>';
var savedCity = '<?= htmlspecialchars($saved_city) ?>';
var savedDistrict = '<?= htmlspecialchars($saved_district) ?>';

function onProvinceChange() {
    var prov = document.getElementById('preset_province').value;
    var citySel = document.getElementById('preset_city');
    var distSel = document.getElementById('preset_district');
    citySel.innerHTML = '<option value="">请选择市</option>';
    distSel.innerHTML = '<option value="">请先选市</option>';
    for (var c in (regionData[prov] || {})) {
        citySel.add(new Option(c, c));
    }
}

function onCityChange() {
    var prov = document.getElementById('preset_province').value;
    var city = document.getElementById('preset_city').value;
    var distSel = document.getElementById('preset_district');
    distSel.innerHTML = '<option value="">请选择区</option>';
    var districts = regionData[prov]?.[city] || [];
    for (var d of districts) {
        distSel.add(new Option(d, d));
    }
}

// 初始化：已有保存的省市区时，还原市→区三级联动
(function initCascade() {
    if (!savedProvince) return;
    var provSel = document.getElementById('preset_province');
    var citySel = document.getElementById('preset_city');
    var distSel = document.getElementById('preset_district');

    // 填充市列表
    var cities = regionData[savedProvince] || {};
    for (var c in cities) {
        citySel.add(new Option(c, c));
    }
    // 填充区列表
    if (savedCity && cities[savedCity]) {
        var districts = cities[savedCity];
        for (var d of districts) {
            distSel.add(new Option(d, d));
        }
    }

    // 选中保存的值
    provSel.value = savedProvince;
    if (savedCity) citySel.value = savedCity;
    if (savedDistrict) distSel.value = savedDistrict;
})();
</script>
</body>
</html>