<?php
/**
 * 脊柱库自动扫描脚本
 * 用途：定时任务自动扫描脊柱库目录，发现新库后自动登记
 * 使用方式：
 *   - Windows Task Scheduler: php D:\path\to\auto_scan.php
 *   - Linux Cron: php /path/to/auto_scan.php
 *
 * 安全说明：
 *   - 此脚本无需登录即可执行，建议在 Task Scheduler 中运行
 *   - 仅做扫描和新增登记，不删除已有记录
 */
require_once __DIR__ . '/lib.php';

// 设置运行超时
set_time_limit(30);

$root = get_spine_db_root();
if (!$root) {
    file_put_contents(__DIR__ . '/data/auto_scan.log', date('Y-m-d H:i:s') . " SKIP: spine_db_root not configured\n", FILE_APPEND);
    exit(0);
}

if (!is_dir($root)) {
    file_put_contents(__DIR__ . '/data/auto_scan.log', date('Y-m-d H:i:s') . " ERROR: root dir not found: {$root}\n", FILE_APPEND);
    exit(1);
}

// 执行自动扫描
$added = auto_scan_and_update_spine();
set_last_auto_scan();

$logMsg = date('Y-m-d H:i:s') . " SCAN: found {$added} new spine dbs in {$root}\n";
file_put_contents(__DIR__ . '/data/auto_scan.log', $logMsg, FILE_APPEND);

echo $logMsg;
exit(0);