-- 将现有库存商品名称迁移到SKU表
-- 文件路径: docs/migrations/migrate_products_to_sku_20260112.sql

USE `mhdlmskp2kpxguj`;

-- 从 mrs_package_items 表中提取所有唯一的产品名称并插入到 mrs_sku 表
-- 使用 INSERT IGNORE 避免重复插入
INSERT INTO `mrs_sku` (
    `sku_code`,
    `sku_name_cn`,
    `sku_name`,  -- 兼容旧字段
    `status`,
    `standard_unit`,
    `case_unit_name`,
    `case_to_standard_qty`,
    `created_at`,
    `updated_at`
)
SELECT DISTINCT
    CONCAT('AUTO-', LPAD(FLOOR(RAND() * 999999), 6, '0')) as sku_code,  -- 自动生成SKU编码
    pi.product_name as sku_name_cn,
    pi.product_name as sku_name,  -- 兼容旧字段
    'active' as status,
    '件' as standard_unit,
    '箱' as case_unit_name,
    1.00 as case_to_standard_qty,
    NOW() as created_at,
    NOW() as updated_at
FROM `mrs_package_items` pi
WHERE pi.product_name IS NOT NULL
  AND TRIM(pi.product_name) != ''
  AND NOT EXISTS (
      SELECT 1
      FROM `mrs_sku` s
      WHERE COALESCE(s.sku_name_cn, s.sku_name) = pi.product_name
  )
ORDER BY pi.product_name;

-- 显示迁移结果
SELECT
    '迁移完成' as message,
    COUNT(*) as total_sku_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM `mrs_sku`;
