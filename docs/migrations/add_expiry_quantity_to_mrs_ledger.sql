-- 迁移文件：为 mrs_package_ledger 表添加有效期和数量字段
-- 创建时间: 2025-12-11
-- 说明: 从 express_package 表同步有效期和数量字段到 MRS 台账表

USE `mhdlmskp2kpxguj`;

-- 添加保质期字段到台账表
ALTER TABLE `mrs_package_ledger`
ADD COLUMN `expiry_date` DATE DEFAULT NULL COMMENT '保质期（非生产日期，选填）' AFTER `spec_info`;

-- 添加数量字段到台账表
ALTER TABLE `mrs_package_ledger`
ADD COLUMN `quantity` INT UNSIGNED DEFAULT NULL COMMENT '数量（选填，参考用途）' AFTER `expiry_date`;

-- 为新字段添加索引（可选，如果需要根据保质期查询）
ALTER TABLE `mrs_package_ledger`
ADD KEY `idx_expiry_date` (`expiry_date`);
