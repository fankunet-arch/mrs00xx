<?php
/**
 * MRS SKU Upgrade Tool
 * 文件路径: dc_html/mrs/upgrade_sku_names.php
 * 说明: 一次性升级脚本，用于将 mrs_package_ledger 中的产品名称导入到 mrs_sku 表中
 */

// 定义系统入口标识
define('MRS_ENTRY', true);

// 定义项目根目录
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// 加载配置
require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';

// 页面样式
echo '<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>MRS SKU Data Upgrade</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; padding: 20px; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; color: #007bff; }
        .log-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; margin-bottom: 20px; }
        .log-item { margin-bottom: 5px; border-bottom: 1px dashed #eee; padding-bottom: 2px; }
        .log-success { color: green; }
        .log-info { color: #666; }
        .log-skip { color: #e6a23c; }
        .log-error { color: red; font-weight: bold; }
        .summary { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; padding: 15px; border-radius: 4px; font-weight: bold; text-align: center; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚀 MRS SKU 数据升级工具</h1>
';

try {
    $pdo = get_mrs_db_connection();

    // 1. 获取现有SKU名称，避免重复
    echo '<div class="log-box">';
    echo '<div class="log-item log-info">正在读取现有SKU数据...</div>';

    $existing_skus = [];
    $stmt = $pdo->query("SELECT sku_name_cn FROM mrs_sku WHERE sku_name_cn IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $existing_skus[$row['sku_name_cn']] = true;
    }

    echo '<div class="log-item log-info">发现现有SKU数量: ' . count($existing_skus) . '</div>';

    // 2. 从 mrs_package_ledger 获取 distinct content_note
    echo '<div class="log-item log-info">正在分析历史台账数据 (mrs_package_ledger)...</div>';

    $source_stmt = $pdo->query("
        SELECT DISTINCT content_note
        FROM mrs_package_ledger
        WHERE content_note IS NOT NULL
        AND content_note != ''
    ");

    $imported_count = 0;
    $skipped_count = 0;
    $error_count = 0;

    // 3. 循环插入
    $insert_stmt = $pdo->prepare("
        INSERT INTO mrs_sku (sku_name_cn, created_at, updated_at)
        VALUES (:name, NOW(), NOW())
    ");

    while ($row = $source_stmt->fetch()) {
        $raw_name = trim($row['content_note']);

        // 截取长度 (varchar 200)
        $name = mb_substr($raw_name, 0, 200, 'UTF-8');

        if (empty($name)) {
            continue;
        }

        if (isset($existing_skus[$name])) {
            $skipped_count++;
            // echo '<div class="log-item log-skip">跳过已存在: ' . htmlspecialchars($name) . '</div>';
        } else {
            try {
                $insert_stmt->execute([':name' => $name]);
                $imported_count++;
                $existing_skus[$name] = true; // 防止本次循环中重复
                echo '<div class="log-item log-success">导入成功: ' . htmlspecialchars($name) . '</div>';
            } catch (Exception $e) {
                $error_count++;
                echo '<div class="log-item log-error">导入失败 [' . htmlspecialchars($name) . ']: ' . $e->getMessage() . '</div>';
            }
        }
    }

    echo '</div>'; // End log-box

    // 4. Summary
    echo '<div class="summary">';
    echo '✅ 升级完成!<br>';
    echo '新增导入: ' . $imported_count . ' 条<br>';
    echo '跳过重复: ' . $skipped_count . ' 条<br>';
    if ($error_count > 0) {
        echo '失败数量: ' . $error_count . ' 条<br>';
    }
    echo '</div>';

    echo '<div style="text-align: center;">
            <a href="/mrs/ap/index.php?action=sku_manage" class="btn">前往 SKU 管理页面</a>
          </div>';

} catch (PDOException $e) {
    echo '</div><div style="color: red; padding: 20px;">数据库连接错误: ' . $e->getMessage() . '</div>';
}

echo '</div></body></html>';
