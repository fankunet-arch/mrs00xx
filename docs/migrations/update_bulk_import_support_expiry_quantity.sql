-- 迁移文件：批量导入功能支持有效期和数量字段
-- 创建时间: 2025-12-11
-- 说明: 验证批量导入功能所需的字段是否存在，如不存在则添加
-- 功能: 批量导入支持 单号|有效期|数量 格式

USE `mhdlmskp2kpxguj`;

-- 检查并添加保质期字段（如果不存在）
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'mhdlmskp2kpxguj'
    AND TABLE_NAME = 'express_package'
    AND COLUMN_NAME = 'expiry_date'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `express_package` ADD COLUMN `expiry_date` DATE DEFAULT NULL COMMENT ''保质期（非生产日期，选填）'' AFTER `content_note`;',
    'SELECT ''字段 expiry_date 已存在，跳过'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加数量字段（如果不存在）
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'mhdlmskp2kpxguj'
    AND TABLE_NAME = 'express_package'
    AND COLUMN_NAME = 'quantity'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `express_package` ADD COLUMN `quantity` INT UNSIGNED DEFAULT NULL COMMENT ''数量（选填，参考用途）'' AFTER `expiry_date`;',
    'SELECT ''字段 quantity 已存在，跳过'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 验证字段是否存在
SELECT
    COLUMN_NAME AS '字段名',
    COLUMN_TYPE AS '字段类型',
    IS_NULLABLE AS '允许空值',
    COLUMN_DEFAULT AS '默认值',
    COLUMN_COMMENT AS '注释'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'mhdlmskp2kpxguj'
  AND TABLE_NAME = 'express_package'
  AND COLUMN_NAME IN ('expiry_date', 'quantity')
ORDER BY ORDINAL_POSITION;
