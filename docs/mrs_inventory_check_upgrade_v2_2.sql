-- mrs_inventory_check_upgrade_v2_2.sql
-- Purpose: add last_counted_at for mobile inventory check (incremental upgrade only)
-- Safety: idempotent (re-runnable)

SET @db_name := DATABASE();

-- 1) add column last_counted_at if not exists
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'mrs_package_ledger'
    AND COLUMN_NAME = 'last_counted_at'
);

SET @sql_add_col := IF(
  @col_exists = 0,
  'ALTER TABLE `mrs_package_ledger`
     ADD COLUMN `last_counted_at` DATETIME NULL DEFAULT NULL COMMENT ''最后盘点时间/库存确认时间''',
  'SELECT ''skip: column last_counted_at exists'' AS msg'
);

PREPARE stmt FROM @sql_add_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) add index idx_last_counted if not exists
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'mrs_package_ledger'
    AND INDEX_NAME = 'idx_last_counted'
);

SET @sql_add_idx := IF(
  @idx_exists = 0,
  'ALTER TABLE `mrs_package_ledger`
     ADD INDEX `idx_last_counted` (`last_counted_at`)',
  'SELECT ''skip: index idx_last_counted exists'' AS msg'
);

PREPARE stmt2 FROM @sql_add_idx;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
