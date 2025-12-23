-- ============================================================================
-- MRS系统 - 货架位置管理功能
-- 迁移文件: add_shelf_locations.sql
-- 版本: v1.0
-- 创建日期: 2025-12-23
-- 说明: 添加货架位置管理相关表和字段
-- ============================================================================

-- 设置字符集
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 步骤 1: 创建货架位置配置表
-- ============================================================================

DROP TABLE IF EXISTS `mrs_shelf_locations`;
CREATE TABLE `mrs_shelf_locations` (
  `location_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '位置ID',
  `shelf_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '货架编号 (如: A, B, C)',
  `shelf_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '货架名称 (如: A货架)',
  `level_number` tinyint DEFAULT NULL COMMENT '层数 (NULL表示不分层)',
  `location_full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '完整位置名称 (如: A货架3层)',
  `capacity` int DEFAULT NULL COMMENT '容量(箱) - 可选',
  `current_usage` int DEFAULT 0 COMMENT '当前使用量 - 自动计算',
  `zone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '区域 (如: 常温区、冷藏区) - 可选',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `sort_order` int DEFAULT 0 COMMENT '排序',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `uk_location_full` (`location_full_name`),
  KEY `idx_shelf_code` (`shelf_code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_zone` (`zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-货架位置配置表';

-- ============================================================================
-- 步骤 2: 插入初始货架位置数据
-- ============================================================================

-- A货架 (3层)
INSERT INTO `mrs_shelf_locations`
(`shelf_code`, `shelf_name`, `level_number`, `location_full_name`, `capacity`, `zone`, `sort_order`)
VALUES
('A', 'A货架', NULL, 'A货架', 100, '常温区', 10),
('A', 'A货架', 1, 'A货架1层', 30, '常温区', 11),
('A', 'A货架', 2, 'A货架2层', 35, '常温区', 12),
('A', 'A货架', 3, 'A货架3层', 35, '常温区', 13);

-- B货架 (3层)
INSERT INTO `mrs_shelf_locations`
(`shelf_code`, `shelf_name`, `level_number`, `location_full_name`, `capacity`, `zone`, `sort_order`)
VALUES
('B', 'B货架', NULL, 'B货架', 100, '常温区', 20),
('B', 'B货架', 1, 'B货架1层', 30, '常温区', 21),
('B', 'B货架', 2, 'B货架2层', 35, '常温区', 22),
('B', 'B货架', 3, 'B货架3层', 35, '常温区', 23);

-- C货架 (3层)
INSERT INTO `mrs_shelf_locations`
(`shelf_code`, `shelf_name`, `level_number`, `location_full_name`, `capacity`, `zone`, `sort_order`)
VALUES
('C', 'C货架', NULL, 'C货架', 100, '常温区', 30),
('C', 'C货架', 1, 'C货架1层', 30, '常温区', 31),
('C', 'C货架', 2, 'C货架2层', 35, '常温区', 32),
('C', 'C货架', 3, 'C货架3层', 35, '常温区', 33);

-- D货架 (冷藏区, 2层)
INSERT INTO `mrs_shelf_locations`
(`shelf_code`, `shelf_name`, `level_number`, `location_full_name`, `capacity`, `zone`, `sort_order`)
VALUES
('D', 'D货架', NULL, 'D货架', 60, '冷藏区', 40),
('D', 'D货架', 1, 'D货架1层', 30, '冷藏区', 41),
('D', 'D货架', 2, 'D货架2层', 30, '冷藏区', 42);

-- E货架 (冷冻区, 2层)
INSERT INTO `mrs_shelf_locations`
(`shelf_code`, `shelf_name`, `level_number`, `location_full_name`, `capacity`, `zone`, `sort_order`)
VALUES
('E', 'E货架', NULL, 'E货架', 60, '冷冻区', 50),
('E', 'E货架', 1, 'E货架1层', 30, '冷冻区', 51),
('E', 'E货架', 2, 'E货架2层', 30, '冷冻区', 52);

-- ============================================================================
-- 步骤 3: 修改 mrs_sku 表 - 添加默认货架位置字段
-- ============================================================================

-- 检查字段是否已存在
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mrs_sku'
    AND COLUMN_NAME = 'default_shelf_location'
);

-- 如果字段不存在则添加
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `mrs_sku`
     ADD COLUMN `default_shelf_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''默认货架位置'' AFTER `spec_info`,
     ADD KEY `idx_default_shelf` (`default_shelf_location`)',
    'SELECT "字段 default_shelf_location 已存在" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 步骤 4: 修改 mrs_package_ledger 表 - 扩展 warehouse_location 字段
-- ============================================================================

-- 扩展字段长度并添加索引
ALTER TABLE `mrs_package_ledger`
MODIFY COLUMN `warehouse_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '货架位置';

-- 检查索引是否已存在
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mrs_package_ledger'
    AND INDEX_NAME = 'idx_warehouse_location'
);

-- 如果索引不存在则添加
SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `mrs_package_ledger` ADD KEY `idx_warehouse_location` (`warehouse_location`)',
    'SELECT "索引 idx_warehouse_location 已存在" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 步骤 5: 创建货架位置使用统计视图
-- ============================================================================

DROP VIEW IF EXISTS `mrs_shelf_location_stats`;
CREATE VIEW `mrs_shelf_location_stats` AS
SELECT
    sl.location_id,
    sl.shelf_code,
    sl.shelf_name,
    sl.level_number,
    sl.location_full_name,
    sl.capacity,
    sl.zone,
    COUNT(pl.ledger_id) AS current_usage,
    CASE
        WHEN sl.capacity IS NOT NULL AND sl.capacity > 0
        THEN ROUND((COUNT(pl.ledger_id) / sl.capacity) * 100, 2)
        ELSE NULL
    END AS usage_rate,
    sl.is_active
FROM
    mrs_shelf_locations sl
LEFT JOIN
    mrs_package_ledger pl ON sl.location_full_name = pl.warehouse_location
    AND pl.status = 'in_stock'
GROUP BY
    sl.location_id,
    sl.shelf_code,
    sl.shelf_name,
    sl.level_number,
    sl.location_full_name,
    sl.capacity,
    sl.zone,
    sl.is_active
ORDER BY
    sl.sort_order;

-- ============================================================================
-- 步骤 6: 创建货架位置变更日志表 (可选,用于追踪位置变更历史)
-- ============================================================================

DROP TABLE IF EXISTS `mrs_shelf_location_history`;
CREATE TABLE `mrs_shelf_location_history` (
  `history_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '历史记录ID',
  `ledger_id` bigint UNSIGNED NOT NULL COMMENT '台账ID',
  `box_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '箱号',
  `old_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原位置',
  `new_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '新位置',
  `change_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '变更原因 (入库/清点/调整)',
  `operator` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '变更时间',
  PRIMARY KEY (`history_id`),
  KEY `idx_ledger` (`ledger_id`),
  KEY `idx_box` (`box_number`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-货架位置变更历史表';

-- ============================================================================
-- 步骤 7: 创建存储过程 - 更新货架位置使用量
-- ============================================================================

DROP PROCEDURE IF EXISTS `sp_update_shelf_usage`;

DELIMITER $$

CREATE PROCEDURE `sp_update_shelf_usage`()
BEGIN
    -- 更新所有货架位置的当前使用量
    UPDATE mrs_shelf_locations sl
    SET current_usage = (
        SELECT COUNT(*)
        FROM mrs_package_ledger pl
        WHERE pl.warehouse_location = sl.location_full_name
        AND pl.status = 'in_stock'
    );

    SELECT '货架使用量更新完成' AS message;
END$$

DELIMITER ;

-- ============================================================================
-- 步骤 8: 创建触发器 - 自动记录位置变更历史
-- ============================================================================

DROP TRIGGER IF EXISTS `trg_ledger_location_change`;

DELIMITER $$

CREATE TRIGGER `trg_ledger_location_change`
AFTER UPDATE ON `mrs_package_ledger`
FOR EACH ROW
BEGIN
    -- 如果货架位置发生变更,记录历史
    IF OLD.warehouse_location != NEW.warehouse_location OR
       (OLD.warehouse_location IS NULL AND NEW.warehouse_location IS NOT NULL) OR
       (OLD.warehouse_location IS NOT NULL AND NEW.warehouse_location IS NULL) THEN

        INSERT INTO mrs_shelf_location_history
        (ledger_id, box_number, old_location, new_location, change_reason, operator)
        VALUES
        (NEW.ledger_id, NEW.box_number, OLD.warehouse_location, NEW.warehouse_location,
         '位置更新', NEW.updated_by);
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- 步骤 9: 数据验证
-- ============================================================================

-- 验证货架位置表
SELECT '货架位置数据统计:' AS info;
SELECT
    shelf_code,
    COUNT(*) AS total_levels,
    SUM(capacity) AS total_capacity
FROM mrs_shelf_locations
WHERE is_active = 1
GROUP BY shelf_code
ORDER BY shelf_code;

-- 验证字段修改
SELECT '表结构验证:' AS info;
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND (
    (TABLE_NAME = 'mrs_sku' AND COLUMN_NAME = 'default_shelf_location')
    OR
    (TABLE_NAME = 'mrs_package_ledger' AND COLUMN_NAME = 'warehouse_location')
)
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
-- 步骤 10: 完成
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

SELECT '迁移完成! 货架位置管理功能已成功部署。' AS message;

-- ============================================================================
-- 使用说明:
--
-- 1. 执行本SQL文件: mysql -u用户名 -p数据库名 < add_shelf_locations.sql
-- 2. 执行存储过程更新使用量: CALL sp_update_shelf_usage();
-- 3. 查看货架统计: SELECT * FROM mrs_shelf_location_stats;
-- 4. 查看位置变更历史: SELECT * FROM mrs_shelf_location_history ORDER BY created_at DESC LIMIT 100;
--
-- 回滚说明 (如需回滚,请谨慎执行):
-- DROP TABLE IF EXISTS mrs_shelf_locations;
-- DROP TABLE IF EXISTS mrs_shelf_location_history;
-- DROP VIEW IF EXISTS mrs_shelf_location_stats;
-- DROP PROCEDURE IF EXISTS sp_update_shelf_usage;
-- DROP TRIGGER IF EXISTS trg_ledger_location_change;
-- ALTER TABLE mrs_sku DROP COLUMN default_shelf_location;
-- ALTER TABLE mrs_package_ledger MODIFY COLUMN warehouse_location varchar(50);
-- ============================================================================
