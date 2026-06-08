<?php
/**
 * 脊柱筛查结果查询 - Step1 身份验证
 * V2.0
 */
require_once __DIR__ . '/lib.php';

$url_key = trim($_GET['k'] ?? '');
if (!$url_key) { http_response_code(403); exit('缺少表单标识'); }

$form = get_form_by_url_key($url_key);
if (!$form) { http_response_code(404); exit('表单不存在'); }

$spine_db_path = $form['spine_db_path'] ?? '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $edit_code = trim($_POST['edit_code'] ?? '');
    if (!$phone || !$edit_code) {
        $error = '请填写手机号和修改码';
    } else {
        // 直接验证 edit_code 匹配 + phone 匹配
        $result = get_all_submissions_by_phone_and_code($phone, $edit_code, $form['id']);
        if (!$result['found']) {
            if ($result['reason'] === 'not_uploaded') {
                $error = '数据信息未上传，请联系学校确认';
            } else {
                $error = '手机号或修改码错误';
            }
        } elseif (empty($result['submissions'])) {
            $error = '数据信息未上传，请联系学校确认';
        } else {
            // 验证通过，跳转到学生选择页（用 URL 参数携带身份）
            $encoded = base64_encode($phone . '|' . $edit_code);
            header('Location: spine_students.php?k=' . urlencode($url_key) . '&t=' . urlencode($encoded));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>脊柱筛查结果查询</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh}
.wrap{max-width:480px;margin:0 auto;padding:0 0 40px}
.hdr{background:#fff;text-align:center;padding:28px 20px 20px;border-radius:0 0 20px 20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
.hdr h2{font-size:22px;color:#1a1a1a;margin-bottom:6px}
.hdr p{font-size:13px;color:#999}
.card{background:#fff;border-radius:12px;margin:0 16px 14px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.tips{background:#e6f7ff;border:1px solid #91d5ff;border-radius:8px;padding:12px;font-size:13px;color:#333;line-height:1.7}
.tips strong{color:#1890ff}
.err{background:#fff1f0;border:1px solid #ffccc7;border-radius:8px;padding:12px;font-size:13px;color:#ff4d4f;margin-bottom:14px}
.lbl{display:block;font-size:13px;color:#666;margin-bottom:6px;font-weight:600}
input{width:100%;padding:12px;border:1px solid #d9d9d9;border-radius:8px;font-size:15px;outline:none;-webkit-appearance:none;box-sizing:border-box}
input:focus{border-color:#1890ff;box-shadow:0 0 0 2px rgba(24,144,255,.1)}
.btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;-webkit-appearance:none;display:block;text-align:center;background:#1890ff;color:#fff}
.btn:active{background:#096dd9}
</style>
</head>
<body>
<div class="wrap">
<div class="hdr">
  <h2>🦴 脊柱筛查结果查询</h2>
  <p>请输入手机号和修改码验证身份</p>
</div>

<?php if ($error): ?>
<div style="margin:0 16px 14px"><div class="err">✗ <?= htmlspecialchars($error) ?></div></div>
<?php endif; ?>

<div class="card">
  <div class="tips"><strong>查询说明：</strong>请填写您提交信息时使用的<strong>手机号</strong>，以及提交成功时获得的<strong>修改码</strong>进行验证</div>
  <form method="post" style="margin-top:14px">
    <div style="margin-bottom:12px">
      <label class="lbl">手机号</label>
      <input type="tel" name="phone" placeholder="请输入11位手机号" maxlength="11" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>
    <div style="margin-bottom:14px">
      <label class="lbl">修改码</label>
      <input type="text" name="edit_code" placeholder="请输入修改码" maxlength="10" required value="<?= htmlspecialchars($_POST['edit_code'] ?? '') ?>">
    </div>
    <button type="submit" class="btn">🔍 下一步</button>
  </form>
</div>
</div>
</body>
</html>