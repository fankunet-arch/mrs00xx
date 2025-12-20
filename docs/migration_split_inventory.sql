-- ============================================
-- 包裹拆分入库功能 - 数据库迁移脚本
-- 创建日期: 2025-12-20
-- 说明: 创建统一出库报表视图，整合SKU系统和包裹台账系统的出库数据
-- ============================================

USE `mhdlmskp2kpxguj`;

-- ============================================
-- 1. 创建统一出库报表视图
-- ============================================

DROP VIEW IF EXISTS `vw_unified_outbound_report`;

CREATE VIEW `vw_unified_outbound_report` AS

-- SKU系统出库（支持箱+件）
SELECT
    DATE(o.outbound_date) AS outbound_date,
    i.sku_name AS product_name,
    SUM(i.outbound_case_qty) AS case_qty,
    SUM(i.outbound_single_qty) AS single_qty,
    i.case_unit_name,
    i.unit_name,
    'sku_system' AS source_type,
    o.location_name AS destination,
    o.outbound_code AS reference_code
FROM mrs_outbound_order o
INNER JOIN mrs_outbound_order_item i ON o.outbound_order_id = i.outbound_order_id
WHERE o.status IN ('confirmed', 'shipped', 'completed')
GROUP BY DATE(o.outbound_date), i.sku_name, i.case_unit_name, i.unit_name, o.location_name, o.outbound_code

UNION ALL

-- 包裹台账系统出库（整箱）
SELECT
    DATE(outbound_time) AS outbound_date,
    content_note AS product_name,
    COUNT(*) AS case_qty,
    0 AS single_qty,
    '箱' AS case_unit_name,
    '件' AS unit_name,
    'package_system' AS source_type,
    COALESCE(d.destination_name, destination_note) AS destination,
    box_number AS reference_code
FROM mrs_package_ledger l
LEFT JOIN mrs_destinations d ON l.destination_id = d.destination_id
WHERE l.status = 'shipped'
GROUP BY DATE(outbound_time), content_note, d.destination_name, destination_note, box_number;

-- ============================================
-- 2. 创建入库统计视图（可选，用于报表）
-- ============================================

DROP VIEW IF EXISTS `vw_unified_inbound_report`;

CREATE VIEW `vw_unified_inbound_report` AS

-- SKU系统入库
SELECT
    b.batch_date AS inbound_date,
    b.batch_name,
    ci.sku_id,
    s.sku_name AS product_name,
    ci.confirmed_case_qty AS case_qty,
    ci.confirmed_single_qty AS single_qty,
    s.case_unit_name,
    s.standard_unit AS unit_name,
    'sku_system' AS source_type
FROM mrs_batch b
INNER JOIN mrs_batch_confirmed_item ci ON b.batch_id = ci.batch_id
INNER JOIN mrs_sku s ON ci.sku_id = s.sku_id
WHERE b.batch_status IN ('confirmed', 'closed')

UNION ALL

-- 包裹台账系统入库
SELECT
    DATE(inbound_time) AS inbound_date,
    batch_name,
    NULL AS sku_id,
    content_note AS product_name,
    COUNT(*) AS case_qty,
    0 AS single_qty,
    '箱' AS case_unit_name,
    '件' AS unit_name,
    'package_system' AS source_type
FROM mrs_package_ledger
WHERE status IN ('in_stock', 'shipped')
GROUP BY DATE(inbound_time), batch_name, content_note;

-- ============================================
-- 验证视图创建成功
-- ============================================

SELECT 'vw_unified_outbound_report view created successfully' AS status
FROM INFORMATION_SCHEMA.VIEWS
WHERE TABLE_SCHEMA = 'mhdlmskp2kpxguj'
  AND TABLE_NAME = 'vw_unified_outbound_report'
LIMIT 1;

SELECT 'vw_unified_inbound_report view created successfully' AS status
FROM INFORMATION_SCHEMA.VIEWS
WHERE TABLE_SCHEMA = 'mhdlmskp2kpxguj'
  AND TABLE_NAME = 'vw_unified_inbound_report'
LIMIT 1;
