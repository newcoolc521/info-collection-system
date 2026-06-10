<?php
/**
 * 信息采集系统 - 核心库 v2（多表单版）
 * PHP7.4+ / SQLite3
 */
session_start();
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
date_default_timezone_set('Asia/Shanghai');

define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('ASSETS_DIR', BASE_DIR . '/assets');
define('DS', DIRECTORY_SEPARATOR);
define('DB_FILE', BASE_DIR . '/db/info_collection.db');

// ── SQLite 数据库连接 ──
function get_db(): SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA encoding = "UTF-8"');
    }
    return $db;
}

// ── 初始化数据库 ──
function init_db() {
    if (!file_exists(DB_FILE)) {
        $db = new SQLite3(DB_FILE);
        $db->busyTimeout(5000);
        $sql = file_get_contents(__DIR__ . '/db/init.sql');
        $db->exec($sql);
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("UPDATE users SET password = '$hash' WHERE username = 'admin'");
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    } else {
        // 已有数据库，追加edit_code字段（已安装用户升級用）
        $db = new SQLite3(DB_FILE);
        $db->busyTimeout(5000);
        $cols = $db->query("PRAGMA table_info(submissions)");
        $col_names = [];
        while ($row = $cols->fetchArray(SQLITE3_ASSOC)) { $col_names[] = $row['name']; }
        if (!in_array('edit_code', $col_names)) {
            $db->exec("ALTER TABLE submissions ADD COLUMN edit_code TEXT NOT NULL DEFAULT ''");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_submissions_edit_code ON submissions(form_id, edit_code)");
        }
        // 已有数据库，追加family_codes表（已安装用户升級用）
        $tbl = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='family_codes'")->fetchArray();
        if (!$tbl) {
            $db->exec("CREATE TABLE IF NOT EXISTS family_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER NOT NULL, phone TEXT NOT NULL, edit_code TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime('now', '+8 hours')), FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE, UNIQUE(form_id, phone))");
        }
        // V2.0 升级：forms.spine_db_path
        $form_cols = $db->query("PRAGMA table_info(forms)");
        $form_col_names = [];
        while ($row = $form_cols->fetchArray(SQLITE3_ASSOC)) { $form_col_names[] = $row['name']; }
        if (!in_array('spine_db_path', $form_col_names)) {
            $db->exec("ALTER TABLE forms ADD COLUMN spine_db_path TEXT NOT NULL DEFAULT ''");
        }
        // V2.0 升级：spine_databases 表
        $sp_tbl = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='spine_databases'")->fetchArray();
        if (!$sp_tbl) {
            $db->exec("CREATE TABLE IF NOT EXISTS spine_databases (id INTEGER PRIMARY KEY AUTOINCREMENT, org_name TEXT NOT NULL, db_path TEXT NOT NULL, remark TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT (datetime('now', '+8 hours')))");
        }
        // V2.0 升级：system_config 表
        $sc_tbl = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_config'")->fetchArray();
        if (!$sc_tbl) {
            $db->exec("CREATE TABLE IF NOT EXISTS system_config (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '')");
            $db->exec("INSERT OR IGNORE INTO system_config (key, value) VALUES ('spine_db_root', '')");
        }
    }
}
init_db();

// ── 随机URL Key ──
function generate_url_key(int $len = 12): string {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $key = '';
    for ($i = 0; $i < $len; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $key;
}

// ── 用户相关 ──
function get_user(string $username): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->bindValue(':u', $username);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function get_all_users(): array {
    $db = get_db();
    $result = $db->query('SELECT username, role, name, created_at FROM users ORDER BY created_at');
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[$row['username']] = $row;
    }
    return $users;
}

function create_user(string $username, string $name, string $password): bool {
    $db = get_db();
    $hash = password_hash($password ?: '123456', PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, password, role, name) VALUES (:u, :p, :r, :n)');
    $stmt->bindValue(':u', $username);
    $stmt->bindValue(':p', $hash);
    $stmt->bindValue(':r', 'user');
    $stmt->bindValue(':n', $name);
    return (bool)$stmt->execute();
}

function update_user(string $username, string $name, ?string $password): bool {
    $db = get_db();
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET name = :n, password = :p WHERE username = :u AND role != "admin"');
        $stmt->bindValue(':p', $hash);
    } else {
        $stmt = $db->prepare('UPDATE users SET name = :n WHERE username = :u AND role != "admin"');
    }
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':u', $username);
    return (bool)$stmt->execute();
}

function delete_user(string $username): bool {
    if ($username === 'admin') return false;
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM users WHERE username = :u AND role != "admin"');
    $stmt->bindValue(':u', $username);
    return (bool)$stmt->execute();
}

function update_password(string $username, string $old, string $new): bool {
    $user = get_user($username);
    if (!$user || !password_verify($old, $user['password'])) return false;
    $db = get_db();
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password = :p WHERE username = :u');
    $stmt->bindValue(':p', $hash);
    $stmt->bindValue(':u', $username);
    return (bool)$stmt->execute();
}

// ── 表单相关（多表单） ──
function get_forms_by_user(string $username): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM forms WHERE username = :u ORDER BY id ASC');
    $stmt->bindValue(':u', $username);
    $result = $stmt->execute();
    $forms = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['fields_config'] = json_decode($row['fields_config'], true) ?: [];
        $forms[] = $row;
    }
    return $forms;
}

function get_form_by_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM forms WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $row['fields_config'] = json_decode($row['fields_config'], true) ?: [];
    }
    return $row ?: null;
}

function get_form_by_url_key(string $url_key): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM forms WHERE url_key = :k');
    $stmt->bindValue(':k', $url_key);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $row['fields_config'] = json_decode($row['fields_config'], true) ?: [];
    }
    return $row ?: null;
}

function create_form(string $username, string $name): int {
    $db = get_db();
    $url_key = generate_url_key();
    // 保证唯一
    while (get_form_by_url_key($url_key)) {
        $url_key = generate_url_key();
    }
    $stmt = $db->prepare('INSERT INTO forms (username, name, url_key) VALUES (:u, :n, :k)');
    $stmt->bindValue(':u', $username);
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':k', $url_key);
    $stmt->execute();
    return $db->lastInsertRowID();
}

function update_form(int $form_id, array $data): bool {
    $db = get_db();
    $stmt = $db->prepare('UPDATE forms SET name=:n, active=:a, start_time=:st, end_time=:et, max_count=:mc, fields_config=:fc, spine_db_path=:sp WHERE id=:id');
    $stmt->bindValue(':n', $data['name'] ?? '未命名');
    $stmt->bindValue(':a', $data['active'] ? 1 : 0);
    $stmt->bindValue(':st', $data['start_time'] ?? '');
    $stmt->bindValue(':et', $data['end_time'] ?? '');
    $stmt->bindValue(':mc', intval($data['max_count'] ?? 0), SQLITE3_INTEGER);
    $stmt->bindValue(':fc', json_encode($data['fields_config'] ?? [], JSON_UNESCAPED_UNICODE));
    $stmt->bindValue(':sp', $data['spine_db_path'] ?? '');
    $stmt->bindValue(':id', $form_id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function delete_form(int $form_id): bool {
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM forms WHERE id = :id');
    $stmt->bindValue(':id', $form_id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function get_form_submissions_count(int $form_id): int {
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM submissions WHERE form_id = :fid');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return intval($row['cnt'] ?? 0);
}

function get_form_submissions(int $form_id, int $page = 1, int $per_page = 20): array {
    $db = get_db();
    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid ORDER BY id DESC LIMIT :lim OFFSET :off');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':lim', $per_page, SQLITE3_INTEGER);
    $stmt->bindValue(':off', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['data'] = json_decode($row['data'], true);
        $subs[] = $row;
    }
    return $subs;
}

function add_form_submission(int $form_id, array $form_data, string $ip, string $edit_code = ''): bool {
    $db = get_db();
    $data_json = json_encode($form_data, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('INSERT INTO submissions (form_id, data, ip, edit_code) VALUES (:fid, :d, :ip, :ec)');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':d', $data_json);
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':ec', $edit_code);
    return (bool)$stmt->execute();
}

function clear_form_submissions(int $form_id): bool {
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM submissions WHERE form_id = :fid');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function get_form_pie_data(int $form_id, string $field_key): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT data FROM submissions WHERE form_id = :fid');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $pie = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data = json_decode($row['data'], true);
        $k = $data[$field_key] ?? '未知';
        if (!isset($pie[$k])) $pie[$k] = 0;
        $pie[$k]++;
    }
    return $pie;
}

function get_form_field_stats(int $form_id, array $fields): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT data FROM submissions WHERE form_id = :fid');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $total = 0;
    $filled = [];
    foreach ($fields as $f) $filled[$f] = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data = json_decode($row['data'], true);
        $total++;
        foreach ($fields as $f) {
            if (!empty($data[$f])) $filled[$f]++;
        }
    }
    return ['total' => $total, 'filled' => $filled];
}

// ── 辅助函数 ──
function is_login(): bool { return isset($_SESSION['uid']); }
function uid(): string { return $_SESSION['uid'] ?? ''; }
function role(): string { return $_SESSION['role'] ?? ''; }

function require_login(string $redirect = '../login.php') {
    if (!is_login()) redirect($redirect);
}

function require_role(string $r, string $redirect = '../login.php') {
    require_login($redirect);
    if (role() !== $r) redirect($redirect);
}

function redirect(string $url) {
    header("Location: $url");
    exit;
}

function json_exit(array $data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }

function is_mobile(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(mobile|android|iphone|ipad|ipod|iemobile|opera mini|blackberry|windows phone|symbian)/i', $ua);
}

function get_client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = explode(',', $ip)[0];
    return trim($ip);
}

function rate_limit_check(string $key, int $seconds = 60, int $max = 3): bool {
    $cache_file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now = time();
    $data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : ['ts' => $now, 'cnt' => 0];
    if ($now - $data['ts'] > $seconds) {
        $data = ['ts' => $now, 'cnt' => 1];
    } else {
        $data['cnt']++;
    }
    file_put_contents($cache_file, json_encode($data));
    return $data['cnt'] <= $max;
}

function load_region_data(): array {
    $file = BASE_DIR . '/data_source/region.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function get_provinces(): array {
    return array_keys(load_region_data());
}

function get_cities(string $province): array {
    $data = load_region_data();
    return array_keys($data[$province] ?? []);
}

function get_districts(string $province, string $city): array {
    $data = load_region_data();
    return $data[$province][$city] ?? [];
}

function load_json_data_source(string $filename): array {
    $file = BASE_DIR . '/data_source/' . $filename;
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}
// ── 单条提交记录操作 ──
function get_submission_by_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM submissions WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $row['data'] = json_decode($row['data'], true);
    return $row;
}

// 获取或创建家庭修改码（同一手机号+表单共用一个码）
function get_or_create_family_edit_code(int $form_id, string $phone): string {
    $db = get_db();
    $stmt = $db->prepare('SELECT edit_code FROM family_codes WHERE form_id = :fid AND phone = :phone');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':phone', $phone);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) return $row['edit_code'];
    // 不存在则创建
    $edit_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt2 = $db->prepare('INSERT INTO family_codes (form_id, phone, edit_code) VALUES (:fid, :phone, :ec)');
    $stmt2->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt2->bindValue(':phone', $phone);
    $stmt2->bindValue(':ec', $edit_code);
    $stmt2->execute();
    return $edit_code;
}

// 获取某手机号+表单下所有提交记录（用于修改页展示列表）
function get_submissions_by_phone(string $phone, int $form_id): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid AND edit_code = :ec ORDER BY id DESC');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':ec', $db->query("SELECT edit_code FROM family_codes WHERE form_id = $form_id AND phone = '$phone'")->fetchArray()['edit_code'] ?? '');
    $result = $stmt->execute();
    $subs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['data'] = json_decode($row['data'], true);
        $subs[] = $row;
    }
    return $subs;
}

function get_submission_by_edit_code(string $phone, string $edit_code, int $form_id): ?array {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid AND edit_code = :ec ORDER BY id DESC LIMIT 1');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':ec', $edit_code);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $row['data'] = json_decode($row['data'], true);
    return $row;
}

/**
 * 获取指定手机号+修改码的所有提交记录（用于多学生选择）
 */
/**
 * 获取指定手机号+修改码的所有提交记录（用于多学生选择）
 * 返回值：
 *   ['found'=>true,  'submissions'=>[...]]  验证通过，有记录
 *   ['found'=>false, 'reason'=>'not_uploaded']  手机号未上传过数据
 *   ['found'=>false, 'reason'=>'wrong_code']     修改码错误
 *
 * 验证逻辑：
 *   1. edit_code 必须在 submissions 表中存在（证明该码有效）
 *   2. 该 edit_code 对应的提交记录中，至少有一条的 phone 字段匹配
 *   3. 返回该手机号对应的所有提交记录（支持多种 phone 字段 key）
 */
function get_all_submissions_by_phone_and_code(string $phone, string $edit_code, int $form_id): array {
    $db = get_db();

    // 先找出该表单中所有 edit_code 匹配的提交
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = :fid AND edit_code = :ec ORDER BY id DESC');
    $stmt->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $stmt->bindValue(':ec', $edit_code);
    $result = $stmt->execute();
    $matched = [];
    $phone_found = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['data'] = json_decode($row['data'], true);
        $sub_phone = $row['data']['监护人电话'] ?? $row['data']['联系电话'] ?? $row['data']['电话'] ?? '';
        if ($sub_phone === $phone) {
            $matched[] = $row;
        }
        // 记录该 phone 是否在任意一条 edit_code 匹配的记录中出现过
        if ($sub_phone === $phone) {
            $phone_found = true;
        }
    }

    // edit_code 在 submissions 中存在，但 phone 不匹配 → 修改码错误
    $any_with_code = $db->prepare('SELECT 1 FROM submissions WHERE form_id = :fid AND edit_code = :ec LIMIT 1');
    $any_with_code->bindValue(':fid', $form_id, SQLITE3_INTEGER);
    $any_with_code->bindValue(':ec', $edit_code);
    $has_this_code = $any_with_code->execute()->fetchArray() !== false;

    if ($has_this_code && !$phone_found) {
        return ['found' => false, 'reason' => 'wrong_code', 'submissions' => []];
    }

    // edit_code 不存在 → 数据未上传（手机号从未提交过）
    if (!$has_this_code) {
        return ['found' => false, 'reason' => 'not_uploaded', 'submissions' => []];
    }

    // 有记录
    return ['found' => true, 'submissions' => $matched];
}

function update_submission(int $id, array $form_data): bool {
    $db = get_db();
    $data_json = json_encode($form_data, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('UPDATE submissions SET data = :d WHERE id = :id');
    $stmt->bindValue(':d', $data_json);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function delete_submission(int $id): bool {
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM submissions WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

// ── 防刷限流：更严格的IP限制（基于日期） ──
function ip_limit_check(string $ip, int $form_id, int $max_per_day = 10): bool {
    $today = date('Y-m-d');
    $cache_file = sys_get_temp_dir() . '/ip_daily_' . md5($ip . '_' . $form_id . '_' . $today) . '.json';
    $now = time();
    $data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : ['ts' => $now, 'cnt' => 0];
    if ($now - $data['ts'] > 86400) {
        $data = ['ts' => $now, 'cnt' => 1];
    } else {
        $data['cnt']++;
    }
    file_put_contents($cache_file, json_encode($data));
    return $data['cnt'] <= $max_per_day;
}

// ── 验证码生成（简单数学题） ──
function generate_captcha(): array {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $op = ($a + $b <= 15) ? '+' : '-';
    $answer = ($op === '+') ? ($a + $b) : ($a - $b);
    $question = "{$a}{$op}{$b}=?";
    $key = bin2hex(random_bytes(8));
    $_SESSION['captcha'] = ['key' => $key, 'answer' => $answer, 'exp' => time() + 600];
    return ['key' => $key, 'question' => $question];
}

function verify_captcha(string $key, string $answer): bool {
    if (!isset($_SESSION['captcha']) || $_SESSION['captcha']['key'] !== $key) return false;
    if (time() > $_SESSION['captcha']['exp']) return false;
    $ok = (strval($_SESSION['captcha']['answer']) === strval($answer));
    unset($_SESSION['captcha']);
    return $ok;
}

// ══════════════════════════════════════════════════════════════
// V2.0 脊柱筛查数据库相关函数
// ══════════════════════════════════════════════════════════════

/**
 * 获取脊柱库根目录配置
 */
function get_spine_db_root(): string {
    $db = get_db();
    $stmt = $db->prepare('SELECT value FROM system_config WHERE key = :k');
    $stmt->bindValue(':k', 'spine_db_root');
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['value'] : '';
}

/**
 * 保存脊柱库根目录配置
 */
function set_spine_db_root(string $path): bool {
    $db = get_db();
    $stmt = $db->prepare('INSERT OR REPLACE INTO system_config (key, value) VALUES (:k, :v)');
    $stmt->bindValue(':k', 'spine_db_root');
    $stmt->bindValue(':v', $path);
    return (bool)$stmt->execute();
}

/**
 * 自动扫描脊柱库并更新登记（无人工干预）
 * @return int 返回新增登记数量
 */
function auto_scan_and_update_spine(): int {
    $found = scan_spine_databases();
    $added = 0;
    foreach ($found as $f) {
        if (!spine_database_exists_by_path($f['db_path'])) {
            add_spine_database($f['org_name'], $f['db_path'], 'auto');
            $added++;
        }
    }
    return $added;
}

/**
 * 获取最后自动扫描时间
 */
function get_last_auto_scan(): string {
    $db = get_db();
    $stmt = $db->prepare('SELECT value FROM system_config WHERE key = :k');
    $stmt->bindValue(':k', 'last_auto_scan');
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['value'] : '';
}

/**
 * 更新最后自动扫描时间
 */
function set_last_auto_scan(): bool {
    $db = get_db();
    $stmt = $db->prepare('INSERT OR REPLACE INTO system_config (key, value) VALUES (:k, :v)');
    $stmt->bindValue(':k', 'last_auto_scan');
    $stmt->bindValue(':v', date('Y-m-d H:i:s'));
    return (bool)$stmt->execute();
}
/**
 * 扫描脊柱库根目录，返回所有发现的脊柱库信息
 * @return array [{org_name, db_path, db_file}, ...]
 */
function scan_spine_databases(): array {
    $root = get_spine_db_root();
    if (!$root || !is_dir($root)) return [];

    $result = [];
    $entries = scandir($root);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $folderPath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($folderPath)) continue;
        $dbFile = rtrim($folderPath, '/\\') . DIRECTORY_SEPARATOR . 'SpinalMeasurement_Pro.db';
        if (!file_exists($dbFile)) continue;

        // 读取 Organization.Name
        $orgName = try_get_org_name($dbFile);
        if (!$orgName) $orgName = $entry; // fallback to folder name

        $result[] = [
            'org_name' => $orgName,
            'folder_name' => $entry,
            'db_path' => $folderPath,
            'db_file' => $dbFile,
        ];
    }
    return $result;
}

/**
 * 尝试从脊柱库读取 Organization.Name
 */
function try_get_org_name(string $dbFile): ?string {
    $db = @new SQLite3($dbFile);
    if (!$db) return null;
    $db->busyTimeout(3000);
    $result = $db->query("SELECT Name FROM Organization LIMIT 1");
    if ($result === false) { $db->close(); return null; }
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row ? $row['Name'] : null;
}

/**
 * 获取脊柱库完整路径（含文件名）
 */
function get_spine_db_file(string $folderPath): string {
    return rtrim($folderPath, '/\\') . DIRECTORY_SEPARATOR . 'SpinalMeasurement_Pro.db';
}

/**
 * 根据身份证号从指定脊柱库查询筛查结果
 * @param string $idCard 身份证号
 * @param string $dbFolderPath 脊柱库文件夹路径
 * @return array ['print_reports' => [], 'detection_results' => [], 'found' => bool]
 */
function get_spine_results_by_idcard(string $idCard, string $dbFolderPath): array {
    $dbFile = get_spine_db_file($dbFolderPath);
    if (!file_exists($dbFile)) return ['found' => false, 'error' => '脊柱库文件不存在', 'print_reports' => [], 'user_info' => null];

    $db = @new SQLite3($dbFile);
    if (!$db) return ['found' => false, 'error' => '无法打开脊柱库', 'print_reports' => [], 'user_info' => null];
    $db->busyTimeout(5000);

    // 查询 User 表获取学生信息（SType/DeptGrade/WorkGroupClass等）
    $userInfo = null;
    $stmtU = $db->prepare('SELECT * FROM User WHERE UserId = :uid AND IsDel = 0 LIMIT 1');
    $stmtU->bindValue(':uid', $idCard);
    $resultU = $stmtU->execute();
    $userRow = $resultU->fetchArray(SQLITE3_ASSOC);
    if ($userRow) $userInfo = $userRow;

    // 查询 PrintReport（只查未删除的，按日期时间倒序只取最新一条）
    $printReports = [];
    $stmt = $db->prepare('SELECT * FROM PrintReport WHERE UserId = :uid AND IsDel = 0 ORDER BY PrintDate DESC LIMIT 1');
    $stmt->bindValue(':uid', $idCard);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $printReports[] = $row;
    }

    $db->close();

    $found = count($printReports) > 0;
    return [
        'found' => $found,
        'print_reports' => $printReports,
        'user_info' => $userInfo,
    ];
}

/**
 * 脊柱库列表管理：获取已登记的脊柱库
 */
function get_spine_databases(): array {
    $db = get_db();
    $result = $db->query('SELECT * FROM spine_databases ORDER BY id ASC');
    $list = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $list[] = $row;
    }
    return $list;
}

function add_spine_database(string $orgName, string $dbPath, string $remark = ''): int {
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO spine_databases (org_name, db_path, remark) VALUES (:n, :p, :r)');
    $stmt->bindValue(':n', $orgName);
    $stmt->bindValue(':p', $dbPath);
    $stmt->bindValue(':r', $remark);
    $stmt->execute();
    return $db->lastInsertRowID();
}

function delete_spine_database(int $id): bool {
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM spine_databases WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function spine_database_exists_by_path(string $dbPath): bool {
    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM spine_databases WHERE db_path = :p LIMIT 1');
    $stmt->bindValue(':p', $dbPath);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row !== false;
}

/**
 * 身份证号脱敏显示（如显示后4位）
 */
function mask_idcard(string $idCard): string {
    if (strlen($idCard) >= 8) {
        return str_repeat('*', strlen($idCard) - 8) . substr($idCard, -8);
    }
    return $idCard;
}
