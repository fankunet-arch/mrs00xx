-- Migration: Add production_time_days field to mrs_sku table
-- Date: 2026-01-16
-- Description: 添加生产时间字段，用于记录定制产品的生产所需天数
-- Author: Claude

-- 添加生产时间字段（以天为单位）
-- 位置：在 shelf_life_months 字段之后
ALTER TABLE `mrs_sku`
ADD COLUMN `production_time_days` INT UNSIGNED DEFAULT NULL
COMMENT '生产时间（天）- 用于定制产品'
AFTER `shelf_life_months`;

-- 创建索引以便于查询定制产品
CREATE INDEX `idx_production_time` ON `mrs_sku` (`production_time_days`);
