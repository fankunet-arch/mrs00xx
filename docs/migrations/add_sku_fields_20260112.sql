-- SKU管理功能扩展 - 添加所需字段
-- 创建时间: 2026-01-12

USE `mhdlmskp2kpxguj`;

-- 扩展 mrs_sku 表结构
ALTER TABLE `mrs_sku`
  -- 重命名现有字段为中文名称
  CHANGE COLUMN `sku_name` `sku_name_cn` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SKU中文名称',

  -- 添加西班牙语名称
  ADD COLUMN `sku_name_es` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SKU西班牙语名称' AFTER `sku_name_cn`,

  -- 添加产品类别（包材/原物料/半成品/成品）
  ADD COLUMN `product_category` enum('packaging','raw_material','semi_finished','finished_product') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品类别：packaging=包材，raw_material=原物料，semi_finished=半成品，finished_product=成品' AFTER `category_id`,

  -- 添加条码
  ADD COLUMN `barcode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品条码' AFTER `sku_code`,

  -- 添加保质期效（几个月）
  ADD COLUMN `shelf_life_months` int UNSIGNED DEFAULT NULL COMMENT '保质期效（月）' AFTER `spec_info`,

  -- 添加供货商所属国家
  ADD COLUMN `supplier_country` enum('china','spain') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '供货商所属国家：china=中国，spain=西班牙' AFTER `shelf_life_months`;

-- 添加索引以提高查询性能
ALTER TABLE `mrs_sku`
  ADD INDEX `idx_product_category` (`product_category`),
  ADD INDEX `idx_barcode` (`barcode`),
  ADD INDEX `idx_supplier_country` (`supplier_country`),
  ADD INDEX `idx_sku_name_cn` (`sku_name_cn`(100)),
  ADD INDEX `idx_sku_name_es` (`sku_name_es`(100));

-- 完成
