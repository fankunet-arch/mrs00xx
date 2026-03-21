-- ============================================================
-- Stock Planning System (SPS) 备货规划系统
-- 数据库迁移脚本
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1. 独立用户表
CREATE TABLE IF NOT EXISTS `sps_users` (
  `user_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL UNIQUE COMMENT '登录用户名',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt密码',
  `display_name` VARCHAR(100) NOT NULL COMMENT '显示名称',
  `role`         ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SPS独立用户表';

-- 2. 部门表（预置寿司房/热厨/跑堂）
CREATE TABLE IF NOT EXISTS `sps_departments` (
  `dept_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_name`  VARCHAR(50)  NOT NULL UNIQUE COMMENT '部门名称',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

INSERT IGNORE INTO `sps_departments` (`dept_name`, `sort_order`) VALUES
  ('寿司房', 1),
  ('热厨',   2),
  ('跑堂',   3);

-- 3. 用户-部门关联（多对多）
CREATE TABLE IF NOT EXISTS `sps_user_departments` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `dept_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_dept` (`user_id`, `dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 供货商表
CREATE TABLE IF NOT EXISTS `sps_suppliers` (
  `supplier_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(100) NOT NULL UNIQUE,
  `contact_name`  VARCHAR(100) DEFAULT NULL,
  `contact_phone` VARCHAR(50)  DEFAULT NULL,
  `remark`        TEXT         DEFAULT NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供货商表';

-- 5. 商品表
CREATE TABLE IF NOT EXISTS `sps_products` (
  `product_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_cn`     VARCHAR(200) NOT NULL COMMENT '中文名称',
  `name_es`     VARCHAR(200) DEFAULT NULL COMMENT '西班牙语名称',
  `supplier_id` INT UNSIGNED DEFAULT NULL COMMENT '供货商',
  `unit`        VARCHAR(20)  NOT NULL DEFAULT '件' COMMENT '默认单位',
  `sort_order`  INT NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品表';

-- 6. 商品-部门关联（多对多）
CREATE TABLE IF NOT EXISTS `sps_product_departments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `dept_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_dept` (`product_id`, `dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 采购轮次表
CREATE TABLE IF NOT EXISTS `sps_rounds` (
  `round_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_year`     INT NOT NULL COMMENT '年份',
  `round_month`    INT NOT NULL COMMENT '月份1-12',
  `order_in_month` INT NOT NULL DEFAULT 1 COMMENT '本月第几次',
  `label`          VARCHAR(50)  NOT NULL COMMENT '如：2月 第1次',
  `status`         ENUM('open','completed') NOT NULL DEFAULT 'open',
  `remark`         TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`round_id`),
  UNIQUE KEY `uq_round` (`round_year`, `round_month`, `order_in_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购轮次表';

-- 8. 各部门填报明细（staff填写）
CREATE TABLE IF NOT EXISTS `sps_round_entries` (
  `entry_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_id`   INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `dept_id`    INT UNSIGNED NOT NULL,
  `qty`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit`       VARCHAR(20)  NOT NULL DEFAULT '件',
  `updated_by` INT UNSIGNED DEFAULT NULL COMMENT '最后更新的用户ID',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  UNIQUE KEY `uq_entry` (`round_id`, `product_id`, `dept_id`),
  KEY `idx_round` (`round_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='各部门采购填报明细';

-- 9. 管理员采购状态（每轮每商品）
CREATE TABLE IF NOT EXISTS `sps_round_purchase` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_id`        INT UNSIGNED NOT NULL,
  `product_id`      INT UNSIGNED NOT NULL,
  `purchase_status` ENUM('pending','purchased','out_of_stock') NOT NULL DEFAULT 'pending',
  `remark`          VARCHAR(255) DEFAULT NULL,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase` (`round_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购状态跟踪';
