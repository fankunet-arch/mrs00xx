-- 迁移文件：添加数量字段到 express_package 表
-- 创建时间: 2025-12-11
-- 说明: 为快递包裹表添加数量字段（选填）

USE `mhdlmskp2kpxguj`;

-- 添加数量字段
ALTER TABLE `express_package`
ADD COLUMN `quantity` INT UNSIGNED DEFAULT NULL COMMENT '数量（选填）' AFTER `expiry_date`;
