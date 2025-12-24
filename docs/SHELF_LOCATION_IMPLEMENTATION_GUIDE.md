# MRS系统 - 货架位置管理功能实施指南

**版本**: v1.0
**日期**: 2025-12-23
**状态**: 第一阶段完成 ✅

---

## 📋 概述

本次升级为MRS系统引入了**货架位置管理**功能,支持箱装、散装、混装货物的精确定位。系统采用渐进式实施,第一阶段已完成核心功能。

---

## ✅ 第一阶段已完成功能

### 1. 数据库架构

**新增表**:
- `mrs_shelf_locations` - 货架位置配置表
- `mrs_shelf_location_history` - 货架位置变更历史表

**修改表**:
- `mrs_sku` - 添加 `default_shelf_location` 字段
- `mrs_package_ledger` - 扩展 `warehouse_location` 字段至 150 字符

**新增视图**:
- `mrs_shelf_location_stats` - 货架位置使用统计视图

**新增存储过程**:
- `sp_update_shelf_usage()` - 更新货架使用量

**新增触发器**:
- `trg_ledger_location_change` - 自动记录位置变更历史

### 2. API接口

已创建4个货架位置管理API:

| API文件 | 功能 | 路径 |
|---------|------|------|
| `shelf_location_autocomplete.php` | 自动补全 | `/mrs/index.php?action=api&endpoint=shelf_location_autocomplete` |
| `shelf_locations_list.php` | 列表查询 | `/mrs/index.php?action=api&endpoint=shelf_locations_list` |
| `shelf_location_save.php` | 保存/更新 | `/mrs/index.php?action=api&endpoint=shelf_location_save` |
| `shelf_location_delete.php` | 删除 | `/mrs/index.php?action=api&endpoint=shelf_location_delete` |

### 3. 入库功能增强

**修改文件**:
- `app/mrs/lib/mrs_lib.php` - 函数 `mrs_inbound_packages()` 支持货架位置参数
- `app/mrs/api/inbound_save.php` - API接收并传递货架位置
- `app/mrs/views/inbound_split.php` - 界面添加货架位置输入框和自动补全

**功能特性**:
- ✅ 入库时可选择性填写货架位置
- ✅ 支持批量设置(所有包裹使用相同位置)
- ✅ 实时自动补全建议
- ✅ 获得焦点时显示常用位置

---

## 🚀 部署步骤

### 步骤 1: 数据库迁移

```bash
# 1. 备份当前数据库
mysqldump -u用户名 -p数据库名 > backup_$(date +%Y%m%d).sql

# 2. 执行迁移SQL
mysql -u用户名 -p数据库名 < app/mrs/migrations/add_shelf_locations.sql

# 3. 验证迁移结果
mysql -u用户名 -p数据库名 -e "SELECT * FROM mrs_shelf_locations LIMIT 5;"
```

### 步骤 2: 更新代码文件

确保以下文件已更新:

```
✅ app/mrs/migrations/add_shelf_locations.sql (新增)
✅ app/mrs/api/shelf_location_autocomplete.php (新增)
✅ app/mrs/api/shelf_locations_list.php (新增)
✅ app/mrs/api/shelf_location_save.php (新增)
✅ app/mrs/api/shelf_location_delete.php (新增)
✅ app/mrs/lib/mrs_lib.php (修改)
✅ app/mrs/api/inbound_save.php (修改)
✅ app/mrs/views/inbound_split.php (修改)
```

### 步骤 3: 初始化货架数据

迁移SQL会自动创建初始货架位置数据:
- A货架 (常温区, 3层)
- B货架 (常温区, 3层)
- C货架 (常温区, 3层)
- D货架 (冷藏区, 2层)
- E货架 (冷冻区, 2层)

可根据实际仓库情况调整。

### 步骤 4: 测试功能

1. **测试入库功能**:
   - 访问 `/mrs/index.php?action=inbound_split`
   - 选择批次和包裹
   - 在"货架位置"输入框输入 "A"
   - 验证自动补全功能
   - 提交入库并检查数据库

2. **测试API**:
   ```bash
   # 测试自动补全API
   curl "http://your-domain/mrs/index.php?action=api&endpoint=shelf_location_autocomplete&keyword=A"

   # 测试列表API
   curl "http://your-domain/mrs/index.php?action=api&endpoint=shelf_locations_list"
   ```

3. **验证数据**:
   ```sql
   -- 查看入库的货架位置
   SELECT box_number, content_note, warehouse_location, inbound_time
   FROM mrs_package_ledger
   WHERE warehouse_location IS NOT NULL
   ORDER BY inbound_time DESC
   LIMIT 10;

   -- 查看货架使用统计
   SELECT * FROM mrs_shelf_location_stats;
   ```

---

## 💡 使用说明

### 入库时设置货架位置

1. 进入"拆分入库"页面
2. 选择批次和需要入库的包裹
3. 在"货架位置"输入框中:
   - **方式1**: 直接输入位置(如 "A货架3层")
   - **方式2**: 输入开头字母(如 "A"),从自动补全中选择
   - **方式3**: 点击输入框,从常用位置中选择
4. 此位置将应用到所有选中的包裹
5. 点击"确认拆分入库"

### 查看货架位置

目前可通过数据库查询:
```sql
-- 按货架位置查询库存
SELECT warehouse_location, COUNT(*) as box_count
FROM mrs_package_ledger
WHERE status = 'in_stock'
AND warehouse_location IS NOT NULL
GROUP BY warehouse_location
ORDER BY warehouse_location;
```

---

## 📊 数据示例

### 货架位置配置示例

| location_id | shelf_code | location_full_name | capacity | zone |
|-------------|------------|-------------------|----------|------|
| 1 | A | A货架 | 100 | 常温区 |
| 2 | A | A货架1层 | 30 | 常温区 |
| 3 | A | A货架2层 | 35 | 常温区 |
| 4 | A | A货架3层 | 35 | 常温区 |

### 入库数据示例

| box_number | content_note | warehouse_location | inbound_time |
|------------|--------------|-------------------|--------------|
| 001 | 糖浆 | A货架3层 | 2025-12-23 10:30:00 |
| 002 | 盐 | B货架1层 | 2025-12-23 10:31:00 |
| 003 | 酱油 | A货架2层 | 2025-12-23 10:32:00 |

---

## ⏭️ 后续阶段规划

### 第二阶段 (待开发)

- [ ] **清点界面增强**
  - 清点时显示当前货架位置
  - 支持快速更新货架位置
  - 记录位置变更历史

- [ ] **库存查询增强**
  - 库存列表显示货架位置列
  - 支持按货架位置筛选
  - 货架位置排序功能

- [ ] **货架位置管理界面**
  - 可视化管理货架配置
  - 查看货架使用率
  - 批量导入货架位置

### 第三阶段 (功能扩展)

- [ ] 货架容量预警
- [ ] 智能推荐货架位置(基于FIFO/FEFO)
- [ ] 货架位置3D可视化
- [ ] 移动端扫码更新位置
- [ ] 与条码打印系统集成

---

## 🔧 维护与管理

### 定期维护任务

```sql
-- 每天执行: 更新货架使用量
CALL sp_update_shelf_usage();

-- 每周执行: 清理无效位置变更历史(保留3个月)
DELETE FROM mrs_shelf_location_history
WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);

-- 每月执行: 查看位置使用率报告
SELECT * FROM mrs_shelf_location_stats
WHERE usage_rate > 80
ORDER BY usage_rate DESC;
```

### 添加新货架

```sql
-- 示例: 添加F货架(常温区, 4层)
INSERT INTO mrs_shelf_locations
(shelf_code, shelf_name, level_number, location_full_name, capacity, zone, sort_order)
VALUES
('F', 'F货架', NULL, 'F货架', 120, '常温区', 60),
('F', 'F货架', 1, 'F货架1层', 30, '常温区', 61),
('F', 'F货架', 2, 'F货架2层', 30, '常温区', 62),
('F', 'F货架', 3, 'F货架3层', 30, '常温区', 63),
('F', 'F货架', 4, 'F货架4层', 30, '常温区', 64);
```

---

## 🐛 故障排查

### 问题1: 自动补全不工作

**原因**: API路由未配置
**解决**: 确保 `index.php` 中API路由正确配置

### 问题2: 货架位置保存失败

**检查项**:
1. 数据库字段是否已扩展至150字符
2. 是否有重复的 `location_full_name`
3. 检查错误日志

```bash
# 查看PHP错误日志
tail -f /var/log/php/error.log

# 查看MySQL错误日志
tail -f /var/log/mysql/error.log
```

### 问题3: 触发器不生效

```sql
-- 检查触发器是否存在
SHOW TRIGGERS LIKE 'mrs_package_ledger';

-- 重新创建触发器
DROP TRIGGER IF EXISTS trg_ledger_location_change;
-- 然后重新执行迁移SQL中的触发器部分
```

---

## 📚 相关文档

- [货架位置功能设计文档](./SHELF_LOCATION_FEATURE.md)
- [MRS系统需求文档](./MRS物料收发管理系统_需求与操作手册.md)
- [数据库Schema](./mrsexp_db_schema_structure_only.sql)

---

## 📞 技术支持

遇到问题请:
1. 查看本文档的"故障排查"章节
2. 检查系统错误日志
3. 联系技术支持团队

---

## 📝 变更日志

| 版本 | 日期 | 内容 |
|------|------|------|
| v1.0 | 2025-12-23 | 第一阶段完成: 数据库、API、入库功能 |

---

**文档结束**
