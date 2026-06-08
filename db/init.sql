-- 信息采集系统 SQLite 数据库初始化脚本 v2（支持多表单）
-- 适用于 PHP 7.4+ / SQLite3

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    username TEXT PRIMARY KEY,
    password    TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    name        TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now', '+8 hours'))
);

-- 初始化管理员
INSERT OR IGNORE INTO users (username, password, role, name) VALUES
    ('admin', '', 'admin', '系统管理员');

-- 更新管理员密码（默认：admin123）
UPDATE users SET password = '' WHERE username = 'admin';

-- 多表单表（每个普通用户可创建多个表单）
CREATE TABLE IF NOT EXISTS forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL,
    name TEXT NOT NULL DEFAULT '表单A',
    url_key         TEXT NOT NULL UNIQUE, -- 随机URL标识
    active          INTEGER NOT NULL DEFAULT 0,
    start_time      TEXT NOT NULL DEFAULT '',
    end_time        TEXT NOT NULL DEFAULT '',
    max_count       INTEGER NOT NULL DEFAULT 0,
    -- 字段配置 JSON：{ field_name: { type:'text'|'select', required:0|1, options:[] } }
    fields_config TEXT NOT NULL DEFAULT '{}',
    created_at      TEXT NOT NULL DEFAULT (datetime('now', '+8 hours')),
    -- V2.0 新增：脊柱筛查数据库路径（绑定的脊柱库文件夹完整路径）
    spine_db_path   TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE
);

-- 信息提交记录表
CREATE TABLE IF NOT EXISTS submissions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id     INTEGER NOT NULL,
    data TEXT NOT NULL, -- JSON格式存储所有字段
    submit_time TEXT NOT NULL DEFAULT (datetime('now', '+8 hours')),
    ip          TEXT NOT NULL DEFAULT '',
    edit_code   TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);

-- 索引
CREATE INDEX IF NOT EXISTS idx_forms_username ON forms(username);
CREATE INDEX IF NOT EXISTS idx_forms_url_key ON forms(url_key);
CREATE INDEX IF NOT EXISTS idx_submissions_form_id ON submissions(form_id);
CREATE INDEX IF NOT EXISTS idx_submissions_edit_code ON submissions(form_id, edit_code);
CREATE INDEX IF NOT EXISTS idx_submissions_time ON submissions(submit_time);

-- V2.0 新增：脊柱筛查数据库配置表
CREATE TABLE IF NOT EXISTS spine_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    org_name    TEXT NOT NULL,   -- 机构名称（从脊柱库 Organization.Name 读取）
    db_path     TEXT NOT NULL,  -- 脊柱库文件夹完整路径（如 D:\spine_db\测试公司）
    remark      TEXT NOT NULL DEFAULT '',  -- 备注（如对应哪个表单）
    created_at  TEXT NOT NULL DEFAULT (datetime('now', '+8 hours'))
);

-- V2.0 新增：系统配置表（脊柱库根目录等）
CREATE TABLE IF NOT EXISTS system_config (
    key    TEXT PRIMARY KEY,
    value  TEXT NOT NULL DEFAULT ''
);

-- 初始化脊柱库根目录配置（管理员需在后台设置）
INSERT OR IGNORE INTO system_config (key, value) VALUES ('spine_db_root', '');