-- MRS 清点功能数据表迁移
-- 创建日期: 2025-12-22
-- 说明: 为MRS系统添加仓库清点功能所需的数据表

-- --------------------------------------------------------
-- 表1: mrs_count_session (清点任务表)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mrs_count_session`;
CREATE TABLE `mrs_count_session` (
  `session_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '清点任务ID',
  `session_name` VARCHAR(100) NOT NULL COMMENT '清点名称',
  `status` ENUM('counting', 'completed', 'cancelled') DEFAULT 'counting' COMMENT '状态',
  `total_counted` INT DEFAULT 0 COMMENT '已清点箱数',
  `start_time` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '开始时间',
  `end_time` DATETIME(6) DEFAULT NULL COMMENT '结束时间',
  `remark` TEXT COMMENT '备注',
  `created_by` VARCHAR(60) COMMENT '创建人',
  `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`session_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点任务表';

-- --------------------------------------------------------
-- 表2: mrs_count_record (清点记录表 - 箱级)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mrs_count_record`;
CREATE TABLE `mrs_count_record` (
  `record_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `session_id` INT UNSIGNED NOT NULL COMMENT '清点任务ID',
  `box_number` VARCHAR(20) NOT NULL COMMENT '箱号',
  `ledger_id` INT UNSIGNED DEFAULT NULL COMMENT '关联台账ID（系统中存在时）',

  -- 系统数据（快照）
  `system_content` TEXT COMMENT '系统内容备注',
  `system_total_qty` DECIMAL(10,2) DEFAULT NULL COMMENT '系统记录总数量',

  -- 清点数据
  `check_mode` ENUM('box_only', 'with_qty') NOT NULL COMMENT '清点模式：box_only=只确认箱子, with_qty=核对数量',
  `has_multiple_items` TINYINT(1) DEFAULT 0 COMMENT '是否有多件物品（0=单一物品,1=多件物品）',

  -- 匹配状态
  `match_status` ENUM('found', 'not_found', 'matched', 'diff') DEFAULT 'found' COMMENT '匹配状态：found=找到箱子,not_found=系统无此箱,matched=数量一致,diff=数量有差异',
  `is_new_box` TINYINT(1) DEFAULT 0 COMMENT '是否为现场新录入的箱子',
  `remark` TEXT COMMENT '备注',

  `counted_by` VARCHAR(60) COMMENT '清点人',
  `counted_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '清点时间',

  PRIMARY KEY (`record_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_box` (`box_number`),
  KEY `idx_ledger` (`ledger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点记录表';

-- --------------------------------------------------------
-- 表3: mrs_count_record_item (清点记录明细表 - 箱内物品)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mrs_count_record_item`;
CREATE TABLE `mrs_count_record_item` (
  `item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID',
  `record_id` BIGINT UNSIGNED NOT NULL COMMENT '清点记录ID',
  `sku_id` INT UNSIGNED DEFAULT NULL COMMENT 'SKU ID',
  `sku_name` VARCHAR(200) NOT NULL COMMENT 'SKU名称',
  `system_qty` DECIMAL(10,2) DEFAULT NULL COMMENT '系统数量',
  `actual_qty` DECIMAL(10,2) NOT NULL COMMENT '实际数量',
  `diff_qty` DECIMAL(10,2) DEFAULT 0.00 COMMENT '差异数量（actual - system）',
  `unit` VARCHAR(20) DEFAULT '件' COMMENT '单位',
  `remark` TEXT COMMENT '备注',
  `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  PRIMARY KEY (`item_id`),
  KEY `idx_record` (`record_id`),
  KEY `idx_sku` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点记录明细表';

-- --------------------------------------------------------
-- 添加外键约束
-- --------------------------------------------------------

ALTER TABLE `mrs_count_record`
  ADD CONSTRAINT `fk_count_record_session` FOREIGN KEY (`session_id`) REFERENCES `mrs_count_session` (`session_id`) ON DELETE CASCADE;

ALTER TABLE `mrs_count_record_item`
  ADD CONSTRAINT `fk_count_item_record` FOREIGN KEY (`record_id`) REFERENCES `mrs_count_record` (`record_id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- 完成
-- --------------------------------------------------------
