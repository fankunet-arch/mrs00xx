-- ============================================
-- 拆分入库功能测试数据
-- 创建日期: 2025-12-20
-- ============================================

USE `mhdlmskp2kpxguj`;

-- 1. 创建测试用户
INSERT INTO `sys_users` (user_login, user_secret_hash, user_email, user_display_name, user_status)
VALUES ('testadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'test@example.com', '测试管理员', 'active')
ON DUPLICATE KEY UPDATE user_status = 'active';
-- 密码是: password

-- 2. 创建Express测试批次
INSERT INTO `express_batch` (batch_name, created_at, status, total_count, verified_count, counted_count)
VALUES
('2025-TEST-001', NOW(), 'active', 5, 5, 5),
('2025-TEST-002', NOW(), 'active', 3, 3, 3)
ON DUPLICATE KEY UPDATE batch_name = VALUES(batch_name);

SET @batch1 = (SELECT batch_id FROM express_batch WHERE batch_name = '2025-TEST-001' LIMIT 1);
SET @batch2 = (SELECT batch_id FROM express_batch WHERE batch_name = '2025-TEST-002' LIMIT 1);

-- 3. 创建带产品明细的包裹（用于拆分入库测试）
INSERT INTO `express_package` (batch_id, tracking_number, package_status, content_note, counted_at, counted_by, skip_inbound)
VALUES
(@batch1, 'EXP001', 'counted', '奶粉+尿布', NOW(), '测试员', 0),
(@batch1, 'EXP002', 'counted', '零食大礼包', NOW(), '测试员', 0),
(@batch1, 'EXP003', 'counted', '纸巾整箱', NOW(), '测试员', 0),
(@batch2, 'EXP004', 'counted', '饮料+巧克力', NOW(), '测试员', 0),
(@batch2, 'EXP005', 'counted', '日用品套装', NOW(), '测试员', 0)
ON DUPLICATE KEY UPDATE tracking_number = VALUES(tracking_number);

-- 获取包裹ID
SET @pkg1 = (SELECT package_id FROM express_package WHERE tracking_number = 'EXP001' LIMIT 1);
SET @pkg2 = (SELECT package_id FROM express_package WHERE tracking_number = 'EXP002' LIMIT 1);
SET @pkg3 = (SELECT package_id FROM express_package WHERE tracking_number = 'EXP003' LIMIT 1);
SET @pkg4 = (SELECT package_id FROM express_package WHERE tracking_number = 'EXP004' LIMIT 1);
SET @pkg5 = (SELECT package_id FROM express_package WHERE tracking_number = 'EXP005' LIMIT 1);

-- 4. 创建包裹产品明细（这是拆分入库的关键数据）
INSERT INTO `express_package_items` (package_id, product_name, quantity, expiry_date, sort_order)
VALUES
-- EXP001包裹：奶粉+尿布
(@pkg1, '婴儿奶粉900g', 20, '2025-12-31', 0),
(@pkg1, '婴儿尿布L码', 30, NULL, 1),

-- EXP002包裹：零食大礼包
(@pkg2, '零食大礼包', 5, '2025-06-30', 0),

-- EXP003包裹：纸巾（无明细，用于测试整箱入库）
-- 故意不添加明细

-- EXP004包裹：饮料+巧克力
(@pkg4, '可口可乐330ml', 24, '2025-08-31', 0),
(@pkg4, '德芙巧克力礼盒', 10, '2025-07-31', 1),

-- EXP005包裹：日用品套装
(@pkg5, '洗发水500ml', 12, '2025-10-31', 0),
(@pkg5, '沐浴露500ml', 12, '2025-10-31', 1),
(@pkg5, '牙膏120g', 20, '2025-12-31', 2)
ON DUPLICATE KEY UPDATE product_name = VALUES(product_name);

-- 5. 创建SKU分类
INSERT INTO `mrs_category` (category_name, category_code, is_active)
VALUES
('食品饮料', 'FOOD', 1),
('日用百货', 'DAILY', 1),
('母婴用品', 'BABY', 1)
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

SET @cat_food = (SELECT category_id FROM mrs_category WHERE category_code = 'FOOD' LIMIT 1);
SET @cat_daily = (SELECT category_id FROM mrs_category WHERE category_code = 'DAILY' LIMIT 1);
SET @cat_baby = (SELECT category_id FROM mrs_category WHERE category_code = 'BABY' LIMIT 1);

-- 6. 创建SKU商品（用于后续匹配）
INSERT INTO `mrs_sku` (category_id, sku_code, sku_name, brand_name, spec_info, standard_unit, case_unit_name, case_to_standard_qty, status)
VALUES
(@cat_baby, 'BABY-001', '婴儿奶粉900g', '美赞臣', '900g/罐', '罐', '箱', 6.00, 'active'),
(@cat_baby, 'BABY-002', '婴儿尿布L码', '帮宝适', 'L码', '片', '包', 50.00, 'active'),
(@cat_food, 'FOOD-001', '零食大礼包', '三只松鼠', '综合装', '盒', '箱', 10.00, 'active'),
(@cat_food, 'FOOD-002', '可口可乐330ml', '可口可乐', '330ml/罐', '罐', '箱', 24.00, 'active'),
(@cat_food, 'FOOD-003', '德芙巧克力礼盒', '德芙', '礼盒装', '盒', '箱', 12.00, 'active'),
(@cat_daily, 'DAILY-001', '洗发水500ml', '潘婷', '500ml/瓶', '瓶', '箱', 12.00, 'active'),
(@cat_daily, 'DAILY-002', '沐浴露500ml', '舒肤佳', '500ml/瓶', '瓶', '箱', 12.00, 'active'),
(@cat_daily, 'DAILY-003', '牙膏120g', '佳洁士', '120g/支', '支', '箱', 20.00, 'active'),
(@cat_daily, 'DAILY-004', '纸巾整箱', '维达', '200抽/包，10包/箱', '包', '箱', 10.00, 'active')
ON DUPLICATE KEY UPDATE sku_name = VALUES(sku_name);

-- 7. 创建去向类型和去向
INSERT INTO `mrs_destination_types` (type_code, type_name, is_enabled, sort_order)
VALUES
('store', '发往门店', 1, 1),
('warehouse', '仓库调仓', 1, 2),
('return', '退回', 1, 3)
ON DUPLICATE KEY UPDATE type_name = VALUES(type_name);

INSERT INTO `mrs_destinations` (type_code, destination_name, destination_code, is_active, sort_order)
VALUES
('store', '北京朝阳门店', 'BJ-CY-001', 1, 1),
('store', '上海徐汇门店', 'SH-XH-001', 1, 2),
('warehouse', '中央仓库', 'WH-CENTER', 1, 3)
ON DUPLICATE KEY UPDATE destination_name = VALUES(destination_name);

-- 8. 为了测试报表，创建一些历史出库记录

-- 获取SKU ID
SET @sku_milk = (SELECT sku_id FROM mrs_sku WHERE sku_code = 'BABY-001' LIMIT 1);
SET @sku_diaper = (SELECT sku_id FROM mrs_sku WHERE sku_code = 'BABY-002' LIMIT 1);
SET @sku_cola = (SELECT sku_id FROM mrs_sku WHERE sku_code = 'FOOD-002' LIMIT 1);
SET @sku_tissue = (SELECT sku_id FROM mrs_sku WHERE sku_code = 'DAILY-004' LIMIT 1);

-- 创建出库单（SKU系统）
INSERT INTO `mrs_outbound_order` (outbound_code, outbound_date, outbound_type, status, location_name, created_at, updated_at)
VALUES
('OUT-20251201-001', '2025-12-01', 1, 'completed', '北京朝阳门店', NOW(), NOW()),
('OUT-20251205-001', '2025-12-05', 1, 'completed', '上海徐汇门店', NOW(), NOW())
ON DUPLICATE KEY UPDATE outbound_code = VALUES(outbound_code);

SET @out1 = (SELECT outbound_order_id FROM mrs_outbound_order WHERE outbound_code = 'OUT-20251201-001' LIMIT 1);
SET @out2 = (SELECT outbound_order_id FROM mrs_outbound_order WHERE outbound_code = 'OUT-20251205-001' LIMIT 1);

-- 创建出库明细（混合：箱+件）
INSERT INTO `mrs_outbound_order_item` (outbound_order_id, sku_id, sku_name, unit_name, case_unit_name, case_to_standard_qty, outbound_case_qty, outbound_single_qty, total_standard_qty, created_at, updated_at)
VALUES
-- 出库单1：奶粉2箱+3罐，尿布100片
(@out1, @sku_milk, '婴儿奶粉900g', '罐', '箱', 6.00, 2.00, 3.00, 15.00, NOW(), NOW()),
(@out1, @sku_diaper, '婴儿尿布L码', '片', '包', 50.00, 0.00, 100.00, 100.00, NOW(), NOW()),

-- 出库单2：可乐24罐（1箱）
(@out2, @sku_cola, '可口可乐330ml', '罐', '箱', 24.00, 1.00, 0.00, 24.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE sku_name = VALUES(sku_name);

-- 9. 创建包裹台账出库数据（整箱出库）
INSERT INTO `mrs_package_ledger` (batch_name, tracking_number, content_note, box_number, status, inbound_time, outbound_time, created_by)
VALUES
('2025-OLD-001', 'OLD001', '纸巾整箱', '0001', 'shipped', '2025-11-01 10:00:00', '2025-12-03 14:00:00', '测试员'),
('2025-OLD-001', 'OLD002', '纸巾整箱', '0002', 'shipped', '2025-11-01 10:00:00', '2025-12-03 14:00:00', '测试员'),
('2025-OLD-001', 'OLD003', '洗衣液', '0003', 'shipped', '2025-11-05 11:00:00', '2025-12-10 15:00:00', '测试员')
ON DUPLICATE KEY UPDATE tracking_number = VALUES(tracking_number);

-- 验证数据
SELECT '=== 测试数据创建完成 ===' AS status;

SELECT 'Express批次数:' AS item, COUNT(*) AS count FROM express_batch WHERE batch_name LIKE '2025-TEST%';
SELECT 'Express包裹数:' AS item, COUNT(*) AS count FROM express_package WHERE tracking_number LIKE 'EXP%';
SELECT '包裹明细数:' AS item, COUNT(*) AS count FROM express_package_items WHERE package_id IN (SELECT package_id FROM express_package WHERE tracking_number LIKE 'EXP%');
SELECT 'SKU商品数:' AS item, COUNT(*) AS count FROM mrs_sku WHERE sku_code LIKE 'BABY-%' OR sku_code LIKE 'FOOD-%' OR sku_code LIKE 'DAILY-%';
SELECT '出库单数:' AS item, COUNT(*) AS count FROM mrs_outbound_order WHERE outbound_code LIKE 'OUT-%';
SELECT '包裹台账出库:' AS item, COUNT(*) AS count FROM mrs_package_ledger WHERE status = 'shipped';
