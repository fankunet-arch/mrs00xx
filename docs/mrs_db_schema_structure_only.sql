-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- 主机： mhdlmskp2kpxguj.mysql.db
-- 生成日期： 2025-11-29 00:23:56
-- 服务器版本： 8.4.6-6
-- PHP 版本： 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `mhdlmskp2kpxguj`
--
CREATE DATABASE IF NOT EXISTS `mhdlmskp2kpxguj` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `mhdlmskp2kpxguj`;

-- --------------------------------------------------------

--
-- 表的结构 `express_batch`
--

DROP TABLE IF EXISTS `express_batch`;
CREATE TABLE `express_batch` (
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `batch_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '批次名称（手工录入）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `status` enum('active','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '批次状态',
  `total_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '总包裹数',
  `verified_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已核实数',
  `counted_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已清点数',
  `adjusted_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已调整数',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递批次表';

-- --------------------------------------------------------

--
-- 表的结构 `express_operation_log`
--

DROP TABLE IF EXISTS `express_operation_log`;
CREATE TABLE `express_operation_log` (
  `log_id` int UNSIGNED NOT NULL COMMENT '日志ID',
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `operation_type` enum('verify','count','adjust') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `operation_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  `operator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '旧状态',
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '新状态',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- --------------------------------------------------------

--
-- 表的结构 `express_package`
--

DROP TABLE IF EXISTS `express_package`;
CREATE TABLE `express_package` (
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '快递单号',
  `package_status` enum('pending','verified','counted','adjusted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '包裹状态',
  `content_note` text COLLATE utf8mb4_unicode_ci COMMENT '内容备注（清点时填写）',
  `adjustment_note` text COLLATE utf8mb4_unicode_ci COMMENT '调整备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `verified_at` datetime DEFAULT NULL COMMENT '核实时间',
  `counted_at` datetime DEFAULT NULL COMMENT '清点时间',
  `adjusted_at` datetime DEFAULT NULL COMMENT '调整时间',
  `verified_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '核实人',
  `counted_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '清点人',
  `adjusted_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整人'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递包裹表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch`
--

DROP TABLE IF EXISTS `mrs_batch`;
CREATE TABLE `mrs_batch` (
  `batch_id` bigint UNSIGNED NOT NULL COMMENT '主键：批次ID',
  `batch_code` varchar(64) NOT NULL COMMENT '展示编号，如 IN-2025-11-23-001',
  `batch_date` date NOT NULL COMMENT '收货日期',
  `location_name` varchar(128) NOT NULL COMMENT '收货地点名称',
  `remark` text COMMENT '备注',
  `batch_status` varchar(32) NOT NULL COMMENT '状态: draft / receiving / pending_merge / confirmed / posted',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='收货批次主表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_confirmed_item`
--

DROP TABLE IF EXISTS `mrs_batch_confirmed_item`;
CREATE TABLE `mrs_batch_confirmed_item` (
  `confirmed_item_id` bigint UNSIGNED NOT NULL COMMENT '主键：确认入库项ID',
  `batch_id` bigint UNSIGNED NOT NULL COMMENT '外键：所属批次ID',
  `sku_id` bigint UNSIGNED NOT NULL COMMENT '外键：SKU ID (必须为具体品牌SKU)',
  `total_standard_qty` decimal(10,4) NOT NULL COMMENT '按标准单位的最终入库数量',
  `confirmed_case_qty` decimal(10,4) NOT NULL COMMENT '最终确认箱数',
  `confirmed_single_qty` decimal(10,4) NOT NULL COMMENT '最终确认散件数',
  `diff_against_expected` decimal(10,4) DEFAULT NULL COMMENT '与预计差异（按标准单位）',
  `is_over_received` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否超收 (1=是, 0=否)',
  `is_under_received` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否少收 (1=是, 0=否)',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='后台合并确认后的入库结果';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_expected_item`
--

DROP TABLE IF EXISTS `mrs_batch_expected_item`;
CREATE TABLE `mrs_batch_expected_item` (
  `expected_item_id` bigint UNSIGNED NOT NULL COMMENT '主键：预计清单行ID',
  `batch_id` bigint UNSIGNED NOT NULL COMMENT '外键：所属批次ID',
  `sku_id` bigint UNSIGNED DEFAULT NULL COMMENT '外键：SKU ID (允许NULL或指向通用SKU)',
  `expected_qty` decimal(10,4) NOT NULL COMMENT '预计数量',
  `expected_unit` varchar(32) NOT NULL COMMENT '预计单位，如 bottle / box',
  `note` text COMMENT '备注',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='收货批次的预计清单行';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_raw_record`
--

DROP TABLE IF EXISTS `mrs_batch_raw_record`;
CREATE TABLE `mrs_batch_raw_record` (
  `raw_record_id` bigint UNSIGNED NOT NULL COMMENT '主键：原始记录ID',
  `batch_id` bigint UNSIGNED NOT NULL COMMENT '外键：所属批次ID',
  `sku_id` bigint UNSIGNED DEFAULT NULL COMMENT '外键：SKU ID (可为“品牌未区分”SKU)',
  `input_sku_name` varchar(255) DEFAULT NULL COMMENT '手动输入的物料名称（当sku_id为NULL时使用）',
  `qty` decimal(10,4) NOT NULL COMMENT '现场录入数量',
  `unit_name` varchar(32) NOT NULL COMMENT '现场录入单位',
  `operator_name` varchar(128) NOT NULL COMMENT '操作人名称',
  `recorded_at` datetime(6) NOT NULL COMMENT '现场录入时间 (UTC)',
  `note` text COMMENT '备注',
  `processing_status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT '处理状态: pending=待处理, confirmed=已确认入库, deleted=已删除',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='前台现场每一条原始收货记录（支持SKU关联或自由文本输入）';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_category`
--

DROP TABLE IF EXISTS `mrs_category`;
CREATE TABLE `mrs_category` (
  `category_id` int UNSIGNED NOT NULL COMMENT '主键：品类ID',
  `category_name` varchar(64) NOT NULL COMMENT '品类名称，如 syrup, cup, bag',
  `category_code` varchar(32) DEFAULT NULL COMMENT '可选短码',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='品类（商品大类）';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory`
--

DROP TABLE IF EXISTS `mrs_inventory`;
CREATE TABLE `mrs_inventory` (
  `inventory_id` bigint UNSIGNED NOT NULL COMMENT '主键：库存ID',
  `sku_id` bigint UNSIGNED NOT NULL COMMENT '外键：SKU ID',
  `current_qty` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '当前库存数量（标准单位）',
  `unit` varchar(32) NOT NULL COMMENT '单位',
  `last_transaction_id` bigint UNSIGNED DEFAULT NULL COMMENT '最后一次交易ID',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='当前库存快照表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory_adjustment`
--

DROP TABLE IF EXISTS `mrs_inventory_adjustment`;
CREATE TABLE `mrs_inventory_adjustment` (
  `adjustment_id` int NOT NULL COMMENT 'Adjustment Record ID',
  `sku_id` int NOT NULL COMMENT 'SKU ID',
  `delta_qty` decimal(10,2) NOT NULL COMMENT 'Adjustment Quantity (Positive=Surplus, Negative=Deficit)',
  `reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Adjustment Reason',
  `operator_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Operator Name',
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Adjustment Time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Adjustment Records';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory_transaction`
--

DROP TABLE IF EXISTS `mrs_inventory_transaction`;
CREATE TABLE `mrs_inventory_transaction` (
  `transaction_id` bigint UNSIGNED NOT NULL COMMENT '主键：流水ID',
  `sku_id` bigint UNSIGNED NOT NULL COMMENT '外键：SKU ID',
  `transaction_type` varchar(32) NOT NULL COMMENT '交易类型: inbound=入库, outbound=出库, adjustment=盘点调整',
  `transaction_subtype` varchar(32) DEFAULT NULL COMMENT '子类型: batch_receipt=批次收货, picking=领用, transfer=调拨, return=退货, scrap=报废, surplus=盘盈, deficit=盘亏',
  `quantity_change` decimal(10,4) NOT NULL COMMENT '数量变化（正数=增加，负数=减少）',
  `quantity_after` decimal(10,4) NOT NULL COMMENT '变动后库存数量',
  `unit` varchar(32) NOT NULL COMMENT '单位',
  `batch_id` bigint UNSIGNED DEFAULT NULL COMMENT '关联批次ID（入库时）',
  `outbound_order_id` int DEFAULT NULL COMMENT '关联出库单ID（出库时）',
  `adjustment_id` int DEFAULT NULL COMMENT '关联调整记录ID（调整时）',
  `raw_record_id` bigint UNSIGNED DEFAULT NULL COMMENT '关联原始收货记录ID（入库确认时）',
  `operator_name` varchar(128) NOT NULL COMMENT '操作人名称',
  `remark` text COMMENT '备注',
  `transaction_date` datetime(6) NOT NULL COMMENT '交易时间 (UTC)',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='库存流水明细表（完整历史记录）';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_outbound_order`
--

DROP TABLE IF EXISTS `mrs_outbound_order`;
CREATE TABLE `mrs_outbound_order` (
  `outbound_order_id` int NOT NULL,
  `outbound_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Outbound Order Code',
  `outbound_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:Picking, 2:Transfer, 3:Return, 4:Scrap',
  `outbound_date` date NOT NULL COMMENT 'Outbound Date',
  `status` enum('draft','confirmed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'Order Status',
  `location_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source/Destination Name',
  `warehouse_id` int DEFAULT NULL COMMENT 'Reserved for Warehouse ID',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT 'Remarks',
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `mrs_outbound_order_item`
--

DROP TABLE IF EXISTS `mrs_outbound_order_item`;
CREATE TABLE `mrs_outbound_order_item` (
  `outbound_order_item_id` int NOT NULL,
  `outbound_order_id` int NOT NULL,
  `sku_id` int NOT NULL,
  `sku_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot of SKU Name',
  `unit_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot of Unit Name',
  `case_unit_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot of Case Unit Name',
  `case_to_standard_qty` decimal(10,4) DEFAULT '1.0000' COMMENT 'Snapshot of Case Spec',
  `outbound_case_qty` decimal(10,2) DEFAULT '0.00' COMMENT 'Outbound Case Quantity',
  `outbound_single_qty` decimal(10,2) DEFAULT '0.00' COMMENT 'Outbound Single Quantity',
  `total_standard_qty` int NOT NULL DEFAULT '0' COMMENT 'Total Standard Quantity (Integer)',
  `remark` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `mrs_sku`
--

DROP TABLE IF EXISTS `mrs_sku`;
CREATE TABLE `mrs_sku` (
  `sku_id` bigint UNSIGNED NOT NULL COMMENT '主键：SKU ID',
  `category_id` int UNSIGNED NOT NULL COMMENT '外键：品类ID',
  `brand_name` varchar(128) NOT NULL COMMENT '品牌名，如 Brand A',
  `sku_name` varchar(255) NOT NULL COMMENT '展示名，如 Syrup A 1L',
  `sku_code` varchar(64) NOT NULL COMMENT '内部编码',
  `is_precise_item` tinyint UNSIGNED NOT NULL DEFAULT '1' COMMENT '1=精计物料, 0=粗计物料',
  `standard_unit` varchar(32) NOT NULL COMMENT '最小标准单位，如 bottle, gram',
  `case_unit_name` varchar(32) DEFAULT NULL COMMENT '箱单位名称，如 box',
  `case_to_standard_qty` decimal(10,4) DEFAULT NULL COMMENT '1箱 = X标准单位',
  `pack_unit_name` varchar(32) DEFAULT NULL COMMENT '包/捆单位名称，如 pack',
  `pack_to_standard_qty` decimal(10,4) DEFAULT NULL COMMENT '1包/捆 = X标准单位',
  `note` text COMMENT '备注',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'SKU Status: active=上架, inactive=下架',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间 (UTC)',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间 (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='品牌 SKU 主数据 (含状态管理)';

-- --------------------------------------------------------

--
-- 表的结构 `sys_users`
--

DROP TABLE IF EXISTS `sys_users`;
CREATE TABLE `sys_users` (
  `user_id` bigint UNSIGNED NOT NULL COMMENT '用户唯一ID (主键)',
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户登录名 (不可变, 用于登录)',
  `user_secret_hash` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户密码的哈希值 (用于验证)',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户电子邮箱 (可用于通知和找回密码)',
  `user_display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户显示名称 (在界面上展示的名字)',
  `user_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'pending' COMMENT '用户账户状态 (例如: active, suspended, pending, deleted)',
  `user_registered_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '用户注册时间 (UTC)',
  `user_last_login_at` datetime(6) DEFAULT NULL COMMENT '用户最后登录时间 (UTC)',
  `user_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='系统用户表';

--
-- 转储表的索引
--

--
-- 表的索引 `express_batch`
--
ALTER TABLE `express_batch`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uk_batch_name` (`batch_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `express_operation_log`
--
ALTER TABLE `express_operation_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_package_id` (`package_id`),
  ADD KEY `idx_operation_type` (`operation_type`),
  ADD KEY `idx_operation_time` (`operation_time`);

--
-- 表的索引 `express_package`
--
ALTER TABLE `express_package`
  ADD PRIMARY KEY (`package_id`),
  ADD UNIQUE KEY `uk_tracking_batch` (`tracking_number`,`batch_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_package_status` (`package_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `mrs_batch`
--
ALTER TABLE `mrs_batch`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uk_batch_code` (`batch_code`),
  ADD KEY `idx_batch_date` (`batch_date`);

--
-- 表的索引 `mrs_batch_confirmed_item`
--
ALTER TABLE `mrs_batch_confirmed_item`
  ADD PRIMARY KEY (`confirmed_item_id`),
  ADD UNIQUE KEY `uk_batch_sku` (`batch_id`,`sku_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_sku_id` (`sku_id`);

--
-- 表的索引 `mrs_batch_expected_item`
--
ALTER TABLE `mrs_batch_expected_item`
  ADD PRIMARY KEY (`expected_item_id`),
  ADD KEY `idx_batch_sku` (`batch_id`,`sku_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_sku_id` (`sku_id`);

--
-- 表的索引 `mrs_batch_raw_record`
--
ALTER TABLE `mrs_batch_raw_record`
  ADD PRIMARY KEY (`raw_record_id`),
  ADD KEY `idx_batch_sku` (`batch_id`,`sku_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_sku_id` (`sku_id`),
  ADD KEY `idx_input_sku_name` (`input_sku_name`),
  ADD KEY `idx_processing_status` (`processing_status`);

--
-- 表的索引 `mrs_category`
--
ALTER TABLE `mrs_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_category_name` (`category_name`);

--
-- 表的索引 `mrs_inventory`
--
ALTER TABLE `mrs_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `uk_sku_id` (`sku_id`),
  ADD KEY `idx_current_qty` (`current_qty`);

--
-- 表的索引 `mrs_inventory_adjustment`
--
ALTER TABLE `mrs_inventory_adjustment`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD KEY `idx_sku_id` (`sku_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `mrs_inventory_transaction`
--
ALTER TABLE `mrs_inventory_transaction`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_sku_id` (`sku_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_outbound_order_id` (`outbound_order_id`),
  ADD KEY `idx_adjustment_id` (`adjustment_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- 表的索引 `mrs_outbound_order`
--
ALTER TABLE `mrs_outbound_order`
  ADD PRIMARY KEY (`outbound_order_id`),
  ADD UNIQUE KEY `uk_outbound_code` (`outbound_code`),
  ADD KEY `idx_outbound_date` (`outbound_date`),
  ADD KEY `idx_outbound_status` (`status`);

--
-- 表的索引 `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  ADD PRIMARY KEY (`outbound_order_item_id`),
  ADD KEY `fk_outbound_order` (`outbound_order_id`);

--
-- 表的索引 `mrs_sku`
--
ALTER TABLE `mrs_sku`
  ADD PRIMARY KEY (`sku_id`),
  ADD UNIQUE KEY `uk_sku_code` (`sku_code`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `sys_users`
--
ALTER TABLE `sys_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_user_login` (`user_login`),
  ADD UNIQUE KEY `uk_user_email` (`user_email`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `express_batch`
--
ALTER TABLE `express_batch`
  MODIFY `batch_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '批次ID';

--
-- 使用表AUTO_INCREMENT `express_operation_log`
--
ALTER TABLE `express_operation_log`
  MODIFY `log_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID';

--
-- 使用表AUTO_INCREMENT `express_package`
--
ALTER TABLE `express_package`
  MODIFY `package_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '包裹ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch`
--
ALTER TABLE `mrs_batch`
  MODIFY `batch_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：批次ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_confirmed_item`
--
ALTER TABLE `mrs_batch_confirmed_item`
  MODIFY `confirmed_item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：确认入库项ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_expected_item`
--
ALTER TABLE `mrs_batch_expected_item`
  MODIFY `expected_item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：预计清单行ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_raw_record`
--
ALTER TABLE `mrs_batch_raw_record`
  MODIFY `raw_record_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：原始记录ID';

--
-- 使用表AUTO_INCREMENT `mrs_category`
--
ALTER TABLE `mrs_category`
  MODIFY `category_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：品类ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory`
--
ALTER TABLE `mrs_inventory`
  MODIFY `inventory_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：库存ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory_adjustment`
--
ALTER TABLE `mrs_inventory_adjustment`
  MODIFY `adjustment_id` int NOT NULL AUTO_INCREMENT COMMENT 'Adjustment Record ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory_transaction`
--
ALTER TABLE `mrs_inventory_transaction`
  MODIFY `transaction_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：流水ID';

--
-- 使用表AUTO_INCREMENT `mrs_outbound_order`
--
ALTER TABLE `mrs_outbound_order`
  MODIFY `outbound_order_id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  MODIFY `outbound_order_item_id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `mrs_sku`
--
ALTER TABLE `mrs_sku`
  MODIFY `sku_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键：SKU ID';

--
-- 使用表AUTO_INCREMENT `sys_users`
--
ALTER TABLE `sys_users`
  MODIFY `user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户唯一ID (主键)';

--
-- 限制导出的表
--

--
-- 限制表 `express_operation_log`
--
ALTER TABLE `express_operation_log`
  ADD CONSTRAINT `fk_log_package` FOREIGN KEY (`package_id`) REFERENCES `express_package` (`package_id`) ON DELETE CASCADE;

--
-- 限制表 `express_package`
--
ALTER TABLE `express_package`
  ADD CONSTRAINT `fk_package_batch` FOREIGN KEY (`batch_id`) REFERENCES `express_batch` (`batch_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  ADD CONSTRAINT `fk_outbound_order` FOREIGN KEY (`outbound_order_id`) REFERENCES `mrs_outbound_order` (`outbound_order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
