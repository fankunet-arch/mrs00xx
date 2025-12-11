-- 迁移文件：添加保质期和数量字段到 express_package 表
-- 创建时间: 2025-12-11
-- 说明: 为快递包裹表添加保质期和数量字段（选填）

USE `mhdlmskp2kpxguj`;

-- 添加保质期字段
ALTER TABLE `express_package`
ADD COLUMN `expiry_date` DATE DEFAULT NULL COMMENT '保质期（非生产日期，选填）' AFTER `content_note`;

-- 添加数量字段
ALTER TABLE `express_package`
ADD COLUMN `quantity` INT UNSIGNED DEFAULT NULL COMMENT '数量（选填）' AFTER `expiry_date`;

-- 为新字段添加索引（可选，如果需要根据保质期查询）
ALTER TABLE `express_package`
ADD KEY `idx_expiry_date` (`expiry_date`);
