-- MRS 系统测试数据插入脚本
-- 用途：插入测试SKU数据，用于验证搜索功能

USE `mhdlmskp2kpxguj`;

-- 插入测试品类
INSERT INTO `mrs_category` (`category_name`, `category_code`, `created_at`, `updated_at`) VALUES
('糖浆类', 'SYRUP', NOW(6), NOW(6)),
('茶叶类', 'TEA', NOW(6), NOW(6)),
('包材类', 'PACKAGE', NOW(6), NOW(6)),
('原料类', 'RAW', NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE category_name = category_name;

-- 获取品类ID（假设ID是1-4）
SET @category_syrup = (SELECT category_id FROM mrs_category WHERE category_code = 'SYRUP' LIMIT 1);
SET @category_tea = (SELECT category_id FROM mrs_category WHERE category_code = 'TEA' LIMIT 1);
SET @category_package = (SELECT category_id FROM mrs_category WHERE category_code = 'PACKAGE' LIMIT 1);
SET @category_raw = (SELECT category_id FROM mrs_category WHERE category_code = 'RAW' LIMIT 1);

-- 插入测试SKU
INSERT INTO `mrs_sku` (
    `sku_name`,
    `sku_code`,
    `category_id`,
    `brand_name`,
    `standard_unit`,
    `case_unit_name`,
    `case_to_standard_qty`,
    `is_precise_item`,
    `note`,
    `created_at`,
    `updated_at`
) VALUES
-- 糖浆类
('糖浆 A 1L', 'SYP-A-001', @category_syrup, '品牌 A', '瓶', '箱', 10.0000, 1, '精计物料', NOW(6), NOW(6)),
('糖浆 B 1L', 'SYP-B-001', @category_syrup, '品牌 B', '瓶', '箱', 15.0000, 1, '精计物料', NOW(6), NOW(6)),
('糖浆 C 500ml', 'SYP-C-001', @category_syrup, '品牌 C', '瓶', '箱', 20.0000, 1, '精计物料', NOW(6), NOW(6)),
('特调糖浆 D', 'SYP-D-001', @category_syrup, '品牌 D', '瓶', '箱', 12.0000, 1, '精计物料', NOW(6), NOW(6)),

-- 茶叶类
('茉莉银毫', 'TEA-001', @category_tea, '福建茶厂', 'g', '箱', 15000.0000, 1, '500g/包，30包/箱', NOW(6), NOW(6)),
('铁观音', 'TEA-002', @category_tea, '安溪茶厂', 'g', '箱', 10000.0000, 1, '500g/包，20包/箱', NOW(6), NOW(6)),
('龙井茶', 'TEA-003', @category_tea, '杭州茶厂', 'g', '箱', 5000.0000, 1, '250g/包，20包/箱', NOW(6), NOW(6)),

-- 包材类
('90-700注塑细磨砂杯', 'PKG-001', @category_package, '包材供应商A', '个', '箱', 500.0000, 0, '粗计物料', NOW(6), NOW(6)),
('珍珠吸管', 'PKG-002', @category_package, '包材供应商B', '根', '包', 1000.0000, 0, '粗计物料', NOW(6), NOW(6)),
('包装袋 A', 'PKG-003', @category_package, '包材供应商C', '个', '包', 100.0000, 0, '粗计物料', NOW(6), NOW(6)),

-- 原料类
('牛奶', 'RAW-001', @category_raw, '乳品厂', 'L', '箱', 12.0000, 1, '1L/瓶，12瓶/箱', NOW(6), NOW(6)),
('奶油', 'RAW-002', @category_raw, '乳品厂', 'kg', '箱', 10.0000, 1, '1kg/盒，10盒/箱', NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE sku_name = VALUES(sku_name);

-- 插入测试批次
INSERT INTO `mrs_batch` (
    `batch_code`,
    `batch_date`,
    `location_name`,
    `remark`,
    `batch_status`,
    `created_at`,
    `updated_at`
) VALUES
('IN-2025-11-24-001', '2025-11-24', '广州·珠江新城', '测试批次1', 'receiving', NOW(6), NOW(6)),
('IN-2025-11-24-002', '2025-11-24', '广州·万胜围', '测试批次2', 'draft', NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE batch_code = VALUES(batch_code);

-- 显示插入结果
SELECT '测试数据插入完成！' as message;
SELECT CONCAT('插入了 ', COUNT(*), ' 个SKU') as result FROM mrs_sku;
SELECT CONCAT('插入了 ', COUNT(*), ' 个品类') as result FROM mrs_category;
SELECT CONCAT('插入了 ', COUNT(*), ' 个批次') as result FROM mrs_batch;
