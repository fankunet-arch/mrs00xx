-- Migration: Add SKU Status Field
-- Description: Add status field to mrs_sku table for product lifecycle management
-- Purpose: Allow SKUs to be marked as active/inactive (上架/下架)

SET NAMES utf8mb4;

-- Add status column to mrs_sku table
ALTER TABLE `mrs_sku`
ADD COLUMN `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'SKU Status: active=上架, inactive=下架'
AFTER `note`;

-- Add index for status queries
ALTER TABLE `mrs_sku`
ADD INDEX `idx_status` (`status`);

-- Update comment
ALTER TABLE `mrs_sku` COMMENT = '品牌 SKU 主数据 (含状态管理)';
