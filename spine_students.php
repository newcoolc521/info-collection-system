<?php
/**
 * 脊柱筛查结果查询 - Step2 学生选择
 * V2.0
 */
require_once __DIR__ . '/lib.php';

$url_key = trim($_GET['k'] ?? '');
$t = trim($_GET['t'] ?? '');
if (!$url_key || !$t) { http_response_code(403); exit('参数错误'); }

$form = get_form_by_url_key($url_key);
if (!$form) { http_response_code(404); exit('表单不存在'); }

$spine_db_path = $form['spine_db_path'] ?? '';

// 验证 token
$parts = explode('|', base64_decode($t));
if (count($parts) !== 2) { http_response_code(403); exit('验证信息无效'); }
list($phone, $edit_code) = $parts;

$result = get_all_submissions_by_phone_and_code($phone, $edit_code, $form['id']);
$students = $result['submissions'];
if (!$result['found']) { http_response_code(403); exit('验证失败，请重新查询'); }

// 过滤有身份证的
$valid = array_filter($students, function($s) {
    $id = $s['data']['学生身份证号'] ?? $s['data']['身份证号'] ?? '';
    return $id !== '';
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>选择学生</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh}
.wrap{max-width:480px;margin:0 auto;padding:0 0 40px}
.hdr{background:#fff;text-align:center;padding:24px 20px;border-radius:0 0 20px 20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
.hdr h2{font-size:20px;color:#1a1a1a;margin-bottom:4px}
.hdr p{font-size:13px;color:#999}
.card{background:#fff;border-radius:12px;margin:0 16px 14px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.tips{background:#e6f7ff;border:1px solid #91d5ff;border-radius:8px;padding:12px;font-size:13px;color:#333;line-height:1.6}
.tips strong{color:#1890ff}
.sc{border:1px solid #e8e8e8;border-radius:10px;padding:14px;margin-bottom:10px;cursor:pointer;transition:all .2s;background:#fafafa}
.sc:hover{border-color:#1890ff;background:#f0f7ff}
.sc.on{border-color:#1890ff;background:#e6f7ff}
.sc .sn{font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:4px}
.sc .si{font-size:12px;color:#888;margin-bottom:2px}
.sc .sid{font-size:11px;color:#bbb;font-family:monospace}
.rdo{display:none}
.btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;-webkit-appearance:none;background:#1890ff;color:#fff;text-align:center;display:block}

.footer{text-align:center;font-size:12px;color:#ccc;padding:16px 0}
</style>
</head>
<body>
<div class="wrap">
<div class="hdr">
  <h2>选择学生</h2>
  <p>请选择要查看脊柱筛查结果的学生</p>
</div>

<div class="card">
  <div class="tips"><strong>提示：</strong>请选择您要查看脊柱筛查结果的学生</div>

    <?php if (empty($valid)): ?>
    <div style="padding:16px 0;text-align:center;color:#999;font-size:14px">未找到有效学生记录</div>
    <?php else: ?>
    <?php foreach ($valid as $i => $s): ?>
      <?php
        $id = $s['data']['学生身份证号'] ?? $s['data']['身份证号'] ?? '';
        $name = $s['data']['学生姓名'] ?? $s['data']['姓名'] ?? '未知';
        $grade = $s['data']['年级'] ?? '-';
        $cls = $s['data']['班级'] ?? '';
        $link = 'spine_view.php?k=' . urlencode($url_key) . '&t=' . urlencode($t) . '&idx=' . $i;
      ?>
      <a href="<?= $link ?>" class="sc" style="display:block;text-decoration:none">
        <div class="sn">◉ <?= htmlspecialchars($name) ?></div>
        <div class="si"><?= htmlspecialchars($grade) ?> <?= htmlspecialchars($cls) ?></div>
        <div class="sid"><?= mask_idcard($id) ?></div>
      </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="footer">
  <a href="spine_verify.php?k=<?= htmlspecialchars($url_key) ?>" style="color:#999;font-size:13px">← 重新验证身份</a>
</div>
</div>

</body>
</html>