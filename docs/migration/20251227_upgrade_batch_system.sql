-- ========================================
-- 批次系统升级迁移脚本
-- 创建日期: 2025-12-27
-- 描述: EXP批次系统升级 - 自动生成3位数批次编号(000-999)
-- ========================================

-- 使用正确的数据库
USE `mhdlmskp2kpxguj`;

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ========================================
-- 步骤1: 添加 batch_cycle 字段
-- ========================================
ALTER TABLE `express_batch`
ADD COLUMN `batch_cycle` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '批次周期（用于区分循环使用的相同编号）'
AFTER `batch_name`;

-- ========================================
-- 步骤2: 创建临时表用于批次重命名映射
-- ========================================
DROP TEMPORARY TABLE IF EXISTS tmp_batch_mapping;
CREATE TEMPORARY TABLE tmp_batch_mapping (
    old_batch_name VARCHAR(100),
    new_batch_name VARCHAR(100),
    batch_id INT UNSIGNED,
    first_counted_at DATETIME,
    batch_created_at DATETIME,
    sort_priority INT,
    new_batch_number INT
);

-- ========================================
-- 步骤3: 填充临时映射表
-- 第一梯队: 有清点记录的批次（按第一个包裹清点时间排序）
-- 第二梯队: 无清点记录的批次（按批次创建时间排序）
-- ========================================

-- 插入第一梯队数据（至少有一个包裹已清点）
INSERT INTO tmp_batch_mapping (old_batch_name, batch_id, first_counted_at, batch_created_at, sort_priority)
SELECT
    b.batch_name,
    b.batch_id,
    MIN(p.counted_at) as first_counted_at,
    b.created_at as batch_created_at,
    1 as sort_priority
FROM express_batch b
INNER JOIN express_package p ON b.batch_id = p.batch_id
WHERE p.counted_at IS NOT NULL
GROUP BY b.batch_id, b.batch_name, b.created_at;

-- 插入第二梯队数据（所有包裹都未清点）
INSERT INTO tmp_batch_mapping (old_batch_name, batch_id, first_counted_at, batch_created_at, sort_priority)
SELECT
    b.batch_name,
    b.batch_id,
    NULL as first_counted_at,
    b.created_at as batch_created_at,
    2 as sort_priority
FROM express_batch b
WHERE NOT EXISTS (
    SELECT 1 FROM express_package p
    WHERE p.batch_id = b.batch_id
    AND p.counted_at IS NOT NULL
);

-- ========================================
-- 步骤4: 分配新的3位数批次编号
-- ========================================

SET @batch_number = -1;

UPDATE tmp_batch_mapping
SET new_batch_number = (@batch_number := @batch_number + 1),
    new_batch_name = LPAD(@batch_number, 3, '0')
ORDER BY
    sort_priority ASC,
    CASE
        WHEN sort_priority = 1 THEN first_counted_at
        ELSE batch_created_at
    END ASC;

-- ========================================
-- 步骤5: 验证数据（检查是否有冲突）
-- ========================================

-- 检查是否所有批次都已分配编号
SELECT COUNT(*) as total_batches FROM tmp_batch_mapping;
SELECT COUNT(*) as assigned_batches FROM tmp_batch_mapping WHERE new_batch_name IS NOT NULL;

-- ========================================
-- 步骤6: 更新 express_batch 表
-- 保存原批次名到notes，更新为新编号
-- ========================================

UPDATE express_batch b
INNER JOIN tmp_batch_mapping m ON b.batch_id = m.batch_id
SET
    b.notes = m.old_batch_name,
    b.batch_name = m.new_batch_name,
    b.batch_cycle = 1;

-- ========================================
-- 步骤7: 更新 MRS 系统的 mrs_package_ledger 表
-- 同步批次名称到新的3位数编号
-- ========================================

UPDATE mrs_package_ledger l
INNER JOIN tmp_batch_mapping m ON l.batch_name = m.old_batch_name
SET l.batch_name = m.new_batch_name;

-- ========================================
-- 步骤8: 删除旧的唯一约束，添加新的复合唯一约束
-- ========================================

-- 删除旧的单字段唯一约束
ALTER TABLE `express_batch`
DROP INDEX `uk_batch_name`;

-- 添加新的复合唯一约束 (batch_name + batch_cycle)
ALTER TABLE `express_batch`
ADD UNIQUE KEY `uk_batch_name_cycle` (`batch_name`, `batch_cycle`);

-- ========================================
-- 步骤9: 验证迁移结果
-- ========================================

-- 显示迁移统计信息
SELECT
    '迁移统计' as info,
    COUNT(*) as total_batches,
    COUNT(DISTINCT batch_name) as unique_batch_names,
    MIN(batch_name) as min_batch_number,
    MAX(batch_name) as max_batch_number
FROM express_batch;

-- 显示前10个批次的迁移结果
SELECT
    batch_id,
    batch_name as new_batch_number,
    batch_cycle,
    notes as old_batch_name,
    created_at
FROM express_batch
ORDER BY batch_name ASC
LIMIT 10;

-- 验证MRS系统数据同步
SELECT
    '受影响的MRS台账记录数' as info,
    COUNT(*) as count
FROM mrs_package_ledger
WHERE batch_name REGEXP '^[0-9]{3}$';

-- ========================================
-- 步骤10: 清理临时表
-- ========================================
DROP TEMPORARY TABLE IF EXISTS tmp_batch_mapping;

-- ========================================
-- 完成迁移
-- ========================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ========================================
-- 迁移完成提示
-- ========================================
SELECT '========================================' as '';
SELECT '批次系统升级迁移完成！' as '提示';
SELECT '请检查上面的统计信息，确保数据迁移正确' as '提示';
SELECT '========================================' as '';
