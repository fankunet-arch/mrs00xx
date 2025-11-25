-- Migration: Add Inventory Adjustment Table
-- Description: Creates mrs_inventory_adjustment table for inventory adjustment records
-- Purpose: Track manual inventory adjustments (盘点/调整) for reconciliation

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create Inventory Adjustment Table
CREATE TABLE IF NOT EXISTS `mrs_inventory_adjustment` (
  `adjustment_id` INT NOT NULL AUTO_INCREMENT COMMENT 'Adjustment Record ID',
  `sku_id` INT NOT NULL COMMENT 'SKU ID',
  `delta_qty` DECIMAL(10, 2) NOT NULL COMMENT 'Adjustment Quantity (Positive=Surplus, Negative=Deficit)',
  `reason` TEXT DEFAULT NULL COMMENT 'Adjustment Reason',
  `operator_name` VARCHAR(100) NOT NULL COMMENT 'Operator Name',
  `created_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Adjustment Time',
  PRIMARY KEY (`adjustment_id`),
  INDEX `idx_sku_id` (`sku_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Adjustment Records';

SET FOREIGN_KEY_CHECKS = 1;
