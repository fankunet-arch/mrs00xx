-- Migration: Add input_sku_name field to mrs_batch_raw_record
-- Purpose: Store manually input material names when sku_id is null
-- Date: 2025-11-24
-- Author: Code Audit Fix

USE `mhdlmskp2kpxguj`;

-- Add input_sku_name column
ALTER TABLE `mrs_batch_raw_record`
ADD COLUMN `input_sku_name` VARCHAR(255) NULL COMMENT '手动输入的物料名称（当sku_id为NULL时使用）'
AFTER `sku_id`;

-- Create index for searching by input name
CREATE INDEX `idx_input_sku_name` ON `mrs_batch_raw_record` (`input_sku_name`);

-- Update comment
ALTER TABLE `mrs_batch_raw_record`
COMMENT = '前台现场每一条原始收货记录（支持SKU关联或自由文本输入）';
