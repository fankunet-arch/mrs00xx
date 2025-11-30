-- Migration: Add physical_box_count to mrs_batch_raw_record
-- Purpose: store actual physical box count for dynamic correction and averaging
ALTER TABLE mrs_batch_raw_record
    ADD COLUMN IF NOT EXISTS physical_box_count DECIMAL(10,2) DEFAULT NULL COMMENT '录入时的物理箱数';
