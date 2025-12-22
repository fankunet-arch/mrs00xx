-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- 主机： mhdlmskp2kpxguj.mysql.db
-- 生成日期： 2025-12-23 00:26:57
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
  `skip_inbound` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=正常入库, 1=不入库（设备/损坏）',
  `content_note` text COLLATE utf8mb4_unicode_ci COMMENT '内容备注（清点时填写）',
  `expiry_date` date DEFAULT NULL COMMENT '保质期（非生产日期，选填）',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量（选填）',
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
-- 表的结构 `express_package_items`
--

DROP TABLE IF EXISTS `express_package_items`;
CREATE TABLE `express_package_items` (
  `item_id` int UNSIGNED NOT NULL COMMENT '明细ID',
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品名称/内容备注',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量',
  `expiry_date` date DEFAULT NULL COMMENT '保质期',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递包裹产品明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch`
--

DROP TABLE IF EXISTS `mrs_batch`;
CREATE TABLE `mrs_batch` (
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `batch_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '批次编号',
  `batch_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '批次名称',
  `batch_date` date DEFAULT NULL COMMENT '批次日期',
  `batch_status` enum('draft','receiving','pending_merge','confirmed','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT '批次状态',
  `location_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '位置名称',
  `supplier_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '供应商名称',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_confirmed_item`
--

DROP TABLE IF EXISTS `mrs_batch_confirmed_item`;
CREATE TABLE `mrs_batch_confirmed_item` (
  `confirmed_item_id` int UNSIGNED NOT NULL COMMENT '确认项ID',
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `confirmed_case_qty` decimal(10,2) DEFAULT '0.00' COMMENT '确认箱数',
  `confirmed_single_qty` decimal(10,2) DEFAULT '0.00' COMMENT '确认散装数',
  `total_standard_qty` decimal(10,2) DEFAULT '0.00' COMMENT '总标准数量',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次确认项表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_expected_item`
--

DROP TABLE IF EXISTS `mrs_batch_expected_item`;
CREATE TABLE `mrs_batch_expected_item` (
  `expected_item_id` int UNSIGNED NOT NULL COMMENT '预期项ID',
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `expected_case_qty` decimal(10,2) DEFAULT '0.00' COMMENT '预期箱数',
  `expected_single_qty` decimal(10,2) DEFAULT '0.00' COMMENT '预期散装数',
  `total_standard_qty` decimal(10,2) DEFAULT '0.00' COMMENT '总标准数量',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次预期项表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_batch_raw_record`
--

DROP TABLE IF EXISTS `mrs_batch_raw_record`;
CREATE TABLE `mrs_batch_raw_record` (
  `raw_record_id` bigint UNSIGNED NOT NULL COMMENT '原始记录ID',
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `input_sku_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '输入的SKU名称',
  `input_case_qty` decimal(10,2) DEFAULT '0.00' COMMENT '输入箱数',
  `input_single_qty` decimal(10,2) DEFAULT '0.00' COMMENT '输入散装数',
  `physical_box_count` int DEFAULT NULL COMMENT '实际箱数',
  `status` enum('pending','matched','confirmed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT '状态',
  `matched_sku_id` int UNSIGNED DEFAULT NULL COMMENT '匹配的SKU ID',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次原始记录表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_category`
--

DROP TABLE IF EXISTS `mrs_category`;
CREATE TABLE `mrs_category` (
  `category_id` int UNSIGNED NOT NULL COMMENT '分类ID',
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名称',
  `category_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分类编码',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '分类描述',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否有效',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-分类表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_count_record`
--

DROP TABLE IF EXISTS `mrs_count_record`;
CREATE TABLE `mrs_count_record` (
  `record_id` bigint UNSIGNED NOT NULL COMMENT '记录ID',
  `session_id` int UNSIGNED NOT NULL COMMENT '清点任务ID',
  `box_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '箱号',
  `ledger_id` int UNSIGNED DEFAULT NULL COMMENT '关联台账ID（系统中存在时）',
  `system_content` text COLLATE utf8mb4_unicode_ci COMMENT '系统内容备注',
  `system_total_qty` decimal(10,2) DEFAULT NULL COMMENT '系统记录总数量',
  `check_mode` enum('box_only','with_qty') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '清点模式：box_only=只确认箱子, with_qty=核对数量',
  `has_multiple_items` tinyint(1) DEFAULT '0' COMMENT '是否有多件物品（0=单一物品,1=多件物品）',
  `match_status` enum('found','not_found','matched','diff') COLLATE utf8mb4_unicode_ci DEFAULT 'found' COMMENT '匹配状态：found=找到箱子,not_found=系统无此箱,matched=数量一致,diff=数量有差异',
  `is_new_box` tinyint(1) DEFAULT '0' COMMENT '是否为现场新录入的箱子',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `counted_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '清点人',
  `counted_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '清点时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点记录表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_count_record_item`
--

DROP TABLE IF EXISTS `mrs_count_record_item`;
CREATE TABLE `mrs_count_record_item` (
  `item_id` bigint UNSIGNED NOT NULL COMMENT '明细ID',
  `record_id` bigint UNSIGNED NOT NULL COMMENT '清点记录ID',
  `sku_id` int UNSIGNED DEFAULT NULL COMMENT 'SKU ID',
  `sku_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SKU名称',
  `system_qty` decimal(10,2) DEFAULT NULL COMMENT '系统数量',
  `actual_qty` decimal(10,2) NOT NULL COMMENT '实际数量',
  `diff_qty` decimal(10,2) DEFAULT '0.00' COMMENT '差异数量（actual - system）',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '件' COMMENT '单位',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点记录明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_count_session`
--

DROP TABLE IF EXISTS `mrs_count_session`;
CREATE TABLE `mrs_count_session` (
  `session_id` int UNSIGNED NOT NULL COMMENT '清点任务ID',
  `session_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '清点名称',
  `status` enum('counting','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'counting' COMMENT '状态',
  `total_counted` int DEFAULT '0' COMMENT '已清点箱数',
  `start_time` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '开始时间',
  `end_time` datetime(6) DEFAULT NULL COMMENT '结束时间',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-清点任务表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_destinations`
--

DROP TABLE IF EXISTS `mrs_destinations`;
CREATE TABLE `mrs_destinations` (
  `destination_id` int UNSIGNED NOT NULL COMMENT '去向ID',
  `type_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '去向类型代码',
  `destination_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '去向名称',
  `destination_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '去向编码（可选）',
  `contact_person` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `address` text COLLATE utf8mb4_unicode_ci COMMENT '地址',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否有效',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='去向管理表';

-- --------------------------------------------------------

--
-- 替换视图以便查看 `mrs_destination_stats`
-- （参见下面的实际视图）
--
DROP VIEW IF EXISTS `mrs_destination_stats`;
CREATE TABLE `mrs_destination_stats` (
`days_used` bigint
,`destination_id` int unsigned
,`destination_name` varchar(100)
,`last_used_time` datetime
,`total_shipments` bigint
,`type_name` varchar(50)
);

-- --------------------------------------------------------

--
-- 表的结构 `mrs_destination_types`
--

DROP TABLE IF EXISTS `mrs_destination_types`;
CREATE TABLE `mrs_destination_types` (
  `type_id` int UNSIGNED NOT NULL COMMENT '类型ID',
  `type_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型代码 (return, warehouse, store)',
  `type_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型名称 (退回、仓库调仓、发往门店)',
  `is_enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='去向类型配置表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory`
--

DROP TABLE IF EXISTS `mrs_inventory`;
CREATE TABLE `mrs_inventory` (
  `inventory_id` bigint UNSIGNED NOT NULL COMMENT '库存ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `current_qty` decimal(10,2) DEFAULT '0.00' COMMENT '当前库存数量',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '件' COMMENT '单位',
  `last_updated_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '最后更新时间',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存主表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory_adjustment`
--

DROP TABLE IF EXISTS `mrs_inventory_adjustment`;
CREATE TABLE `mrs_inventory_adjustment` (
  `adjustment_id` int UNSIGNED NOT NULL COMMENT '调整记录ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `delta_qty` decimal(10,2) NOT NULL COMMENT '调整数量（正数为盘盈，负数为盘亏）',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整原因',
  `operator_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存调整记录表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_inventory_transaction`
--

DROP TABLE IF EXISTS `mrs_inventory_transaction`;
CREATE TABLE `mrs_inventory_transaction` (
  `transaction_id` bigint UNSIGNED NOT NULL COMMENT '流水ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `transaction_type` enum('inbound','outbound','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类型',
  `transaction_subtype` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '交易子类型（surplus盘盈/deficit盘亏等）',
  `quantity_change` decimal(10,2) NOT NULL COMMENT '数量变化（正数为增加，负数为减少）',
  `quantity_before` decimal(10,2) NOT NULL COMMENT '变化前数量',
  `quantity_after` decimal(10,2) NOT NULL COMMENT '变化后数量',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '件' COMMENT '单位',
  `batch_id` int UNSIGNED DEFAULT NULL COMMENT '关联批次ID',
  `outbound_order_id` int UNSIGNED DEFAULT NULL COMMENT '关联出库单ID',
  `adjustment_id` int UNSIGNED DEFAULT NULL COMMENT '关联调整记录ID',
  `operator_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存流水表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_outbound_order`
--

DROP TABLE IF EXISTS `mrs_outbound_order`;
CREATE TABLE `mrs_outbound_order` (
  `outbound_order_id` int UNSIGNED NOT NULL COMMENT '出库单ID',
  `outbound_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '出库单号',
  `outbound_date` date NOT NULL COMMENT '出库日期',
  `outbound_type` tinyint DEFAULT '1' COMMENT '出库类型（1=销售出库，2=调拨出库，3=退货出库等）',
  `location_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '目的地位置',
  `recipient_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收货人',
  `recipient_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收货电话',
  `recipient_address` text COLLATE utf8mb4_unicode_ci COMMENT '收货地址',
  `status` enum('draft','confirmed','shipped','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT '状态',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-出库单主表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_outbound_order_item`
--

DROP TABLE IF EXISTS `mrs_outbound_order_item`;
CREATE TABLE `mrs_outbound_order_item` (
  `outbound_order_item_id` int UNSIGNED NOT NULL COMMENT '出库单明细ID',
  `outbound_order_id` int UNSIGNED NOT NULL COMMENT '出库单ID',
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `sku_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SKU名称（冗余字段）',
  `unit_name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '件' COMMENT '单位名称',
  `case_unit_name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '箱' COMMENT '箱单位名称',
  `case_to_standard_qty` decimal(10,2) DEFAULT '1.00' COMMENT '每箱标准数量',
  `outbound_case_qty` decimal(10,2) DEFAULT '0.00' COMMENT '出库箱数',
  `outbound_single_qty` decimal(10,2) DEFAULT '0.00' COMMENT '出库散装数',
  `total_standard_qty` decimal(10,2) DEFAULT '0.00' COMMENT '总标准数量',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-出库单明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_package_items`
--

DROP TABLE IF EXISTS `mrs_package_items`;
CREATE TABLE `mrs_package_items` (
  `item_id` int UNSIGNED NOT NULL COMMENT '明细ID',
  `ledger_id` bigint UNSIGNED NOT NULL COMMENT '台账ID',
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品名称/内容备注',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量',
  `expiry_date` date DEFAULT NULL COMMENT '保质期',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS台账产品明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_package_ledger`
--

DROP TABLE IF EXISTS `mrs_package_ledger`;
CREATE TABLE `mrs_package_ledger` (
  `ledger_id` bigint UNSIGNED NOT NULL COMMENT '台账ID (主键)',
  `batch_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '批次名称',
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '快递单号',
  `content_note` text COLLATE utf8mb4_unicode_ci COMMENT '内容备注',
  `box_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '箱号',
  `warehouse_location` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '仓库位置',
  `spec_info` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格备注',
  `expiry_date` date DEFAULT NULL COMMENT '保质期（非生产日期，选填）',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量（选填，参考用途）',
  `status` enum('in_stock','shipped','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock' COMMENT '状态',
  `inbound_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '入库时间',
  `outbound_time` datetime DEFAULT NULL COMMENT '出库时间',
  `destination_id` int UNSIGNED DEFAULT NULL COMMENT '出库去向ID',
  `destination_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '去向备注',
  `void_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '损耗原因',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `updated_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '更新人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS 包裹台账表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_sku`
--

DROP TABLE IF EXISTS `mrs_sku`;
CREATE TABLE `mrs_sku` (
  `sku_id` int UNSIGNED NOT NULL COMMENT 'SKU ID',
  `category_id` int UNSIGNED DEFAULT NULL COMMENT '分类ID',
  `sku_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SKU编码',
  `sku_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SKU名称',
  `brand_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '品牌名称',
  `spec_info` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格信息',
  `standard_unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '件' COMMENT '标准单位（件、个、瓶等）',
  `case_unit_name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '箱' COMMENT '箱单位名称',
  `case_to_standard_qty` decimal(10,2) DEFAULT '1.00' COMMENT '每箱标准数量',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT '状态',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-SKU商品表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_usage_log`
--

DROP TABLE IF EXISTS `mrs_usage_log`;
CREATE TABLE `mrs_usage_log` (
  `id` int UNSIGNED NOT NULL COMMENT '记录ID',
  `ledger_id` int UNSIGNED DEFAULT NULL COMMENT '包裹台账ID（关联 mrs_package_ledger）',
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
  `outbound_type` enum('partial','whole') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'partial' COMMENT '出货类型：partial=拆零出货, whole=整箱出货',
  `deduct_qty` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '出货数量（标准单位件数）',
  `destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '目的地（门店名称）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '出货时间',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作员',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一出货记录表（拆零+整箱）';

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
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_skip_inbound` (`skip_inbound`);

--
-- 表的索引 `express_package_items`
--
ALTER TABLE `express_package_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_package_id` (`package_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- 表的索引 `mrs_batch`
--
ALTER TABLE `mrs_batch`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uk_batch_code` (`batch_code`),
  ADD KEY `idx_batch_status` (`batch_status`),
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
  ADD UNIQUE KEY `uk_batch_sku` (`batch_id`,`sku_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_sku_id` (`sku_id`);

--
-- 表的索引 `mrs_batch_raw_record`
--
ALTER TABLE `mrs_batch_raw_record`
  ADD PRIMARY KEY (`raw_record_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_matched_sku_id` (`matched_sku_id`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `mrs_category`
--
ALTER TABLE `mrs_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_category_name` (`category_name`),
  ADD KEY `idx_category_code` (`category_code`);

--
-- 表的索引 `mrs_count_record`
--
ALTER TABLE `mrs_count_record`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_box` (`box_number`),
  ADD KEY `idx_ledger` (`ledger_id`);

--
-- 表的索引 `mrs_count_record_item`
--
ALTER TABLE `mrs_count_record_item`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_record` (`record_id`),
  ADD KEY `idx_sku` (`sku_id`);

--
-- 表的索引 `mrs_count_session`
--
ALTER TABLE `mrs_count_session`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  ADD PRIMARY KEY (`destination_id`),
  ADD KEY `idx_type_code` (`type_code`),
  ADD KEY `idx_active` (`is_active`);

--
-- 表的索引 `mrs_destination_types`
--
ALTER TABLE `mrs_destination_types`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `uk_type_code` (`type_code`);

--
-- 表的索引 `mrs_inventory`
--
ALTER TABLE `mrs_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `uk_sku_id` (`sku_id`);

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
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `mrs_outbound_order`
--
ALTER TABLE `mrs_outbound_order`
  ADD PRIMARY KEY (`outbound_order_id`),
  ADD UNIQUE KEY `uk_outbound_code` (`outbound_code`),
  ADD KEY `idx_outbound_date` (`outbound_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_outbound_type` (`outbound_type`);

--
-- 表的索引 `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  ADD PRIMARY KEY (`outbound_order_item_id`),
  ADD KEY `idx_outbound_order_id` (`outbound_order_id`),
  ADD KEY `idx_sku_id` (`sku_id`);

--
-- 表的索引 `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_ledger_id` (`ledger_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_product_lookup` (`product_name`,`expiry_date`);

--
-- 表的索引 `mrs_package_ledger`
--
ALTER TABLE `mrs_package_ledger`
  ADD PRIMARY KEY (`ledger_id`),
  ADD UNIQUE KEY `uk_batch_tracking` (`batch_name`,`tracking_number`),
  ADD UNIQUE KEY `uk_batch_box` (`batch_name`,`box_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_content_note` (`content_note`(50)),
  ADD KEY `idx_batch_name` (`batch_name`),
  ADD KEY `idx_inbound_time` (`inbound_time`),
  ADD KEY `idx_outbound_time` (`outbound_time`),
  ADD KEY `idx_destination` (`destination_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_product_status` (`status`);

--
-- 表的索引 `mrs_sku`
--
ALTER TABLE `mrs_sku`
  ADD PRIMARY KEY (`sku_id`),
  ADD UNIQUE KEY `uk_sku_code` (`sku_code`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_sku_name` (`sku_name`),
  ADD KEY `idx_brand_name` (`brand_name`);

--
-- 表的索引 `mrs_usage_log`
--
ALTER TABLE `mrs_usage_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_name`),
  ADD KEY `idx_destination` (`destination`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ledger_id` (`ledger_id`);

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
-- 使用表AUTO_INCREMENT `express_package_items`
--
ALTER TABLE `express_package_items`
  MODIFY `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch`
--
ALTER TABLE `mrs_batch`
  MODIFY `batch_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '批次ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_confirmed_item`
--
ALTER TABLE `mrs_batch_confirmed_item`
  MODIFY `confirmed_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '确认项ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_expected_item`
--
ALTER TABLE `mrs_batch_expected_item`
  MODIFY `expected_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '预期项ID';

--
-- 使用表AUTO_INCREMENT `mrs_batch_raw_record`
--
ALTER TABLE `mrs_batch_raw_record`
  MODIFY `raw_record_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '原始记录ID';

--
-- 使用表AUTO_INCREMENT `mrs_category`
--
ALTER TABLE `mrs_category`
  MODIFY `category_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分类ID';

--
-- 使用表AUTO_INCREMENT `mrs_count_record`
--
ALTER TABLE `mrs_count_record`
  MODIFY `record_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID';

--
-- 使用表AUTO_INCREMENT `mrs_count_record_item`
--
ALTER TABLE `mrs_count_record_item`
  MODIFY `item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_count_session`
--
ALTER TABLE `mrs_count_session`
  MODIFY `session_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '清点任务ID';

--
-- 使用表AUTO_INCREMENT `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  MODIFY `destination_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '去向ID';

--
-- 使用表AUTO_INCREMENT `mrs_destination_types`
--
ALTER TABLE `mrs_destination_types`
  MODIFY `type_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '类型ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory`
--
ALTER TABLE `mrs_inventory`
  MODIFY `inventory_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '库存ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory_adjustment`
--
ALTER TABLE `mrs_inventory_adjustment`
  MODIFY `adjustment_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '调整记录ID';

--
-- 使用表AUTO_INCREMENT `mrs_inventory_transaction`
--
ALTER TABLE `mrs_inventory_transaction`
  MODIFY `transaction_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '流水ID';

--
-- 使用表AUTO_INCREMENT `mrs_outbound_order`
--
ALTER TABLE `mrs_outbound_order`
  MODIFY `outbound_order_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '出库单ID';

--
-- 使用表AUTO_INCREMENT `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  MODIFY `outbound_order_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '出库单明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  MODIFY `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_package_ledger`
--
ALTER TABLE `mrs_package_ledger`
  MODIFY `ledger_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '台账ID (主键)';

--
-- 使用表AUTO_INCREMENT `mrs_sku`
--
ALTER TABLE `mrs_sku`
  MODIFY `sku_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'SKU ID';

--
-- 使用表AUTO_INCREMENT `mrs_usage_log`
--
ALTER TABLE `mrs_usage_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID';

--
-- 使用表AUTO_INCREMENT `sys_users`
--
ALTER TABLE `sys_users`
  MODIFY `user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户唯一ID (主键)';

-- --------------------------------------------------------

--
-- 视图结构 `mrs_destination_stats`
--
DROP TABLE IF EXISTS `mrs_destination_stats`;

DROP VIEW IF EXISTS `mrs_destination_stats`;
CREATE ALGORITHM=UNDEFINED DEFINER=`mhdlmskp2kpxguj`@`%` SQL SECURITY DEFINER VIEW `mrs_destination_stats`  AS SELECT `d`.`destination_id` AS `destination_id`, `d`.`destination_name` AS `destination_name`, `dt`.`type_name` AS `type_name`, count(`l`.`ledger_id`) AS `total_shipments`, count(distinct cast(`l`.`outbound_time` as date)) AS `days_used`, max(`l`.`outbound_time`) AS `last_used_time` FROM ((`mrs_destinations` `d` left join `mrs_destination_types` `dt` on((`d`.`type_code` = `dt`.`type_code`))) left join `mrs_package_ledger` `l` on(((`d`.`destination_id` = `l`.`destination_id`) and (`l`.`status` = 'shipped')))) WHERE (`d`.`is_active` = 1) GROUP BY `d`.`destination_id`, `d`.`destination_name`, `dt`.`type_name` ORDER BY `total_shipments` DESC ;

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
-- 限制表 `express_package_items`
--
ALTER TABLE `express_package_items`
  ADD CONSTRAINT `fk_item_package` FOREIGN KEY (`package_id`) REFERENCES `express_package` (`package_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_batch_confirmed_item`
--
ALTER TABLE `mrs_batch_confirmed_item`
  ADD CONSTRAINT `fk_confirmed_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_confirmed_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_batch_expected_item`
--
ALTER TABLE `mrs_batch_expected_item`
  ADD CONSTRAINT `fk_expected_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_expected_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_batch_raw_record`
--
ALTER TABLE `mrs_batch_raw_record`
  ADD CONSTRAINT `fk_raw_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_raw_sku` FOREIGN KEY (`matched_sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE SET NULL;

--
-- 限制表 `mrs_count_record`
--
ALTER TABLE `mrs_count_record`
  ADD CONSTRAINT `fk_count_record_session` FOREIGN KEY (`session_id`) REFERENCES `mrs_count_session` (`session_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_count_record_item`
--
ALTER TABLE `mrs_count_record_item`
  ADD CONSTRAINT `fk_count_item_record` FOREIGN KEY (`record_id`) REFERENCES `mrs_count_record` (`record_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  ADD CONSTRAINT `fk_destination_type` FOREIGN KEY (`type_code`) REFERENCES `mrs_destination_types` (`type_code`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- 限制表 `mrs_inventory`
--
ALTER TABLE `mrs_inventory`
  ADD CONSTRAINT `fk_inventory_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_inventory_adjustment`
--
ALTER TABLE `mrs_inventory_adjustment`
  ADD CONSTRAINT `fk_adjustment_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_inventory_transaction`
--
ALTER TABLE `mrs_inventory_transaction`
  ADD CONSTRAINT `fk_transaction_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_outbound_order_item`
--
ALTER TABLE `mrs_outbound_order_item`
  ADD CONSTRAINT `fk_outbound_item_order` FOREIGN KEY (`outbound_order_id`) REFERENCES `mrs_outbound_order` (`outbound_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_outbound_item_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE RESTRICT;

--
-- 限制表 `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  ADD CONSTRAINT `fk_item_ledger` FOREIGN KEY (`ledger_id`) REFERENCES `mrs_package_ledger` (`ledger_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_sku`
--
ALTER TABLE `mrs_sku`
  ADD CONSTRAINT `fk_sku_category` FOREIGN KEY (`category_id`) REFERENCES `mrs_category` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
