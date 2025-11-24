<?php
/**
 * MRS 物料收发管理系统 - API: 保存收货记录
 * 文件路径: app/mrs/api/save_record.php
 * 说明: 保存前台收货原始记录
 */

// 定义入口常量
define('MRS_ENTRY', true);

// 加载配置和库文件
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 只接受 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, null, '仅支持POST请求');
    }

    // 获取 POST JSON 数据
    $input = get_json_input();

    if ($input === null) {
        json_response(false, null, '无效的JSON数据');
    }

    // 验证必填字段
    $required_fields = ['batch_id', 'qty', 'unit_name', 'operator_name'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    // [FIX] 验证数量必须为数字
    if (!is_numeric($input['qty'])) {
        json_response(false, null, '数量必须为有效数字');
    }

    // 验证批次是否存在
    $batch = get_batch_by_id($input['batch_id']);
    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // 验证批次状态是否允许编辑
    if (!is_batch_editable($batch['batch_status'])) {
        json_response(false, null, '批次状态不允许添加记录');
    }

    // 准备数据
    $record_data = [
        'batch_id' => $input['batch_id'],
        'sku_id' => $input['sku_id'] ?? null,
        'qty' => $input['qty'],
        'unit_name' => $input['unit_name'],
        'operator_name' => $input['operator_name'],
        'recorded_at' => date('Y-m-d H:i:s.u'),
        'note' => $input['note'] ?? ''
    ];

    // 保存记录
    $record_id = save_raw_record($record_data);

    if ($record_id === false) {
        json_response(false, null, '保存失败');
    }

    // 记录日志
    mrs_log('保存收货记录成功', 'INFO', [
        'record_id' => $record_id,
        'batch_id' => $input['batch_id'],
        'sku_id' => $input['sku_id'] ?? null,
        'qty' => $input['qty'],
        'unit_name' => $input['unit_name']
    ]);

    // 返回成功响应
    json_response(true, ['record_id' => $record_id], '保存成功');

} catch (Exception $e) {
    mrs_log('保存记录API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}
