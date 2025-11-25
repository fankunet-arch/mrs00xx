-- Migration: Create Outbound Management Tables
-- Description: Creates mrs_outbound_order and mrs_outbound_order_item tables

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create Outbound Order Table
CREATE TABLE IF NOT EXISTS `mrs_outbound_order` (
  `outbound_order_id` INT NOT NULL AUTO_INCREMENT,
  `outbound_code` VARCHAR(50) DEFAULT NULL COMMENT 'Outbound Order Code',
  `outbound_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1:Picking, 2:Transfer, 3:Return, 4:Scrap',
  `outbound_date` DATE NOT NULL COMMENT 'Outbound Date',
  `status` ENUM('draft', 'confirmed', 'cancelled') NOT NULL DEFAULT 'draft' COMMENT 'Order Status',
  `location_name` VARCHAR(100) DEFAULT NULL COMMENT 'Source/Destination Name',
  `warehouse_id` INT DEFAULT NULL COMMENT 'Reserved for Warehouse ID',
  `remark` TEXT DEFAULT NULL COMMENT 'Remarks',
  `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`outbound_order_id`),
  UNIQUE KEY `uk_outbound_code` (`outbound_code`),
  INDEX `idx_outbound_date` (`outbound_date`),
  INDEX `idx_outbound_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Outbound Order Item Table
CREATE TABLE IF NOT EXISTS `mrs_outbound_order_item` (
  `outbound_order_item_id` INT NOT NULL AUTO_INCREMENT,
  `outbound_order_id` INT NOT NULL,
  `sku_id` INT NOT NULL,
  `sku_name` VARCHAR(255) NOT NULL COMMENT 'Snapshot of SKU Name',
  `unit_name` VARCHAR(50) DEFAULT NULL COMMENT 'Snapshot of Unit Name',
  `case_unit_name` VARCHAR(50) DEFAULT NULL COMMENT 'Snapshot of Case Unit Name',
  `case_to_standard_qty` DECIMAL(10, 4) DEFAULT 1.0000 COMMENT 'Snapshot of Case Spec',
  `outbound_case_qty` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Outbound Case Quantity',
  `outbound_single_qty` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Outbound Single Quantity',
  `total_standard_qty` INT NOT NULL DEFAULT 0 COMMENT 'Total Standard Quantity (Integer)',
  `remark` TEXT DEFAULT NULL,
  `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`outbound_order_item_id`),
  CONSTRAINT `fk_outbound_order` FOREIGN KEY (`outbound_order_id`) REFERENCES `mrs_outbound_order` (`outbound_order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
