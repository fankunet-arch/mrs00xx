# MRS系统 - 货架位置管理功能设计文档

**文档版本**: v1.0
**编写日期**: 2025-12-23
**设计目标**: 引入货架位置管理,支持箱装、散装、混装货物的精确定位

---

## 1. 需求分析

### 1.1 业务需求

**核心需求**:
- 支持货架号管理,例如 "A货架"、"A货架3层"
- 支持箱装货物(一箱一种或混装多种货物)
- 支持散装货物直接存放于货架
- 操作简单便捷,对系统改动最小

**使用场景**:
1. **入库时**: 录入货物并指定货架位置
2. **清点时**: 更新/确认货架位置
3. **查询时**: 按货架位置查找货物
4. **出库时**: 根据货架位置快速定位货物

### 1.2 设计原则

1. **最小改动**: 利用现有 `warehouse_location` 字段,避免大规模数据库改动
2. **简单易用**: 提供自动补全功能,快速输入常用货架位置
3. **灵活性**: 支持自定义货架编号规则,不强制固定格式
4. **向后兼容**: 不影响现有功能,货架位置为可选项

---

## 2. 数据模型设计

### 2.1 新增表: mrs_shelf_locations

**用途**: 管理货架位置配置,提供自动补全数据源

```sql
CREATE TABLE `mrs_shelf_locations` (
  `location_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '位置ID',
  `shelf_code` varchar(20) NOT NULL COMMENT '货架编号 (如: A, B, C)',
  `shelf_name` varchar(100) NOT NULL COMMENT '货架名称 (如: A货架)',
  `level_number` tinyint DEFAULT NULL COMMENT '层数 (NULL表示不分层)',
  `location_full_name` varchar(150) NOT NULL COMMENT '完整位置名称 (如: A货架3层)',
  `capacity` int DEFAULT NULL COMMENT '容量(箱) - 可选',
  `current_usage` int DEFAULT 0 COMMENT '当前使用量 - 自动计算',
  `zone` varchar(50) DEFAULT NULL COMMENT '区域 (如: 常温区、冷藏区) - 可选',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `sort_order` int DEFAULT 0 COMMENT '排序',
  `remark` text COMMENT '备注',
  `created_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` datetime(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `uk_location_full` (`location_full_name`),
  KEY `idx_shelf_code` (`shelf_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-货架位置配置表';
```

**示例数据**:
```sql
INSERT INTO mrs_shelf_locations (shelf_code, shelf_name, level_number, location_full_name) VALUES
('A', 'A货架', NULL, 'A货架'),
('A', 'A货架', 1, 'A货架1层'),
('A', 'A货架', 2, 'A货架2层'),
('A', 'A货架', 3, 'A货架3层'),
('B', 'B货架', NULL, 'B货架'),
('B', 'B货架', 1, 'B货架1层'),
('B', 'B货架', 2, 'B货架2层');
```

### 2.2 修改表: mrs_sku

**添加字段**: 默认货架位置

```sql
ALTER TABLE `mrs_sku`
ADD COLUMN `default_shelf_location` varchar(150) DEFAULT NULL COMMENT '默认货架位置' AFTER `spec_info`,
ADD KEY `idx_shelf_location` (`default_shelf_location`);
```

**用途**:
- 记录SKU的默认存储位置
- 入库时自动建议货架位置
- 便于新员工快速定位

### 2.3 利用现有字段: mrs_package_ledger.warehouse_location

**现状**: 已存在但未充分利用
```sql
`warehouse_location` varchar(50) DEFAULT NULL COMMENT '仓库位置'
```

**改进**: 扩展字段长度,与货架位置表关联
```sql
ALTER TABLE `mrs_package_ledger`
MODIFY COLUMN `warehouse_location` varchar(150) DEFAULT NULL COMMENT '货架位置',
ADD KEY `idx_warehouse_location` (`warehouse_location`);
```

---

## 3. 功能设计

### 3.1 货架位置管理界面

**路径**: `/mrs/shelf_locations`

**功能**:
- 查看所有货架位置列表
- 添加/编辑/删除货架位置
- 批量导入货架位置(支持Excel/CSV)
- 查看每个货架的使用率

**界面布局**:
```
+--------------------------------------------------+
| 货架位置管理                    [+ 新增货架位置] |
+--------------------------------------------------+
| 搜索: [_____]  区域: [全部▼]  状态: [全部▼]     |
+--------------------------------------------------+
| 货架编号 | 货架名称 | 层数 | 使用率 | 操作      |
|---------|---------|------|--------|------------|
| A       | A货架   |  -   | 15/50  | 编辑 删除  |
| A       | A货架1层|  1   | 8/20   | 编辑 删除  |
| A       | A货架2层|  2   | 5/15   | 编辑 删除  |
| A       | A货架3层|  3   | 2/15   | 编辑 删除  |
+--------------------------------------------------+
```

### 3.2 入库时指定货架位置

**修改文件**: `app/mrs/actions/inbound_split.php` 和 `app/mrs/views/inbound_split.php`

**改进点**:
1. 入库表单增加"货架位置"输入框
2. 支持自动补全(基于 mrs_shelf_locations 表)
3. 如果SKU有默认货架位置,自动填充
4. 支持批量设置(所有包裹使用相同货架位置)

**界面示例**:
```html
<div class="form-group">
    <label>货架位置 (可选)</label>
    <input type="text"
           id="shelf_location"
           class="form-control"
           placeholder="例如: A货架3层"
           autocomplete="off">
    <small class="text-muted">提示: 开始输入会显示建议位置</small>
</div>

<!-- 批量设置选项 -->
<div class="form-check">
    <input type="checkbox" id="apply_to_all" class="form-check-input">
    <label for="apply_to_all">将此位置应用到所有包裹</label>
</div>
```

### 3.3 清点时更新货架位置

**修改文件**: `app/mrs/views/count_operations.php`

**改进点**:
1. 清点记录表增加"货架位置"列
2. 扫描箱号后,显示当前货架位置
3. 支持快速更新货架位置
4. 记录位置变更历史

**界面示例**:
```
+--------------------------------------------------+
| 清点箱号: [_____] [扫描]                         |
+--------------------------------------------------+
| 箱号: 001                                        |
| 内容: 糖浆 x 10                                  |
| 当前位置: A货架3层                               |
|                                                  |
| 更新位置: [_____] (留空则不更新)                 |
|                                                  |
| [确认清点] [标记异常]                            |
+--------------------------------------------------+
```

### 3.4 库存查询增强

**修改文件**: `app/mrs/views/inventory_list.php`

**改进点**:
1. 库存列表增加"货架位置"列
2. 支持按货架位置筛选
3. 支持货架位置排序
4. 在详情页显示货架位置历史

**界面示例**:
```
+--------------------------------------------------+
| 库存查询                                         |
+--------------------------------------------------+
| 产品名称: [_____]  货架位置: [_____]  [搜索]     |
+--------------------------------------------------+
| 箱号 | 产品名称 | 数量 | 货架位置 | 入库时间    |
|------|---------|------|---------|-------------|
| 001  | 糖浆    | 10   | A货架3层 | 2025-12-20 |
| 002  | 糖浆    | 12   | A货架2层 | 2025-12-19 |
| 003  | 盐      | 50   | B货架1层 | 2025-12-18 |
+--------------------------------------------------+
```

---

## 4. API设计

### 4.1 货架位置管理API

#### 4.1.1 获取货架位置列表
```
GET /mrs/api/shelf_locations_list.php
参数:
  - keyword: 搜索关键词(可选)
  - zone: 区域筛选(可选)
  - is_active: 状态筛选(可选)

响应:
{
  "success": true,
  "data": [
    {
      "location_id": 1,
      "location_full_name": "A货架3层",
      "shelf_code": "A",
      "level_number": 3,
      "current_usage": 15,
      "capacity": 50
    }
  ]
}
```

#### 4.1.2 货架位置自动补全
```
GET /mrs/api/shelf_location_autocomplete.php
参数:
  - keyword: 搜索关键词

响应:
{
  "success": true,
  "suggestions": [
    "A货架",
    "A货架1层",
    "A货架2层",
    "A货架3层"
  ]
}
```

#### 4.1.3 保存货架位置
```
POST /mrs/api/shelf_location_save.php
参数:
  - location_id: 位置ID(编辑时提供)
  - shelf_code: 货架编号
  - shelf_name: 货架名称
  - level_number: 层数
  - capacity: 容量
  - zone: 区域

响应:
{
  "success": true,
  "location_id": 1,
  "message": "货架位置保存成功"
}
```

### 4.2 入库API修改

**文件**: `app/mrs/api/inbound_save.php`

**修改点**:
```php
// 接收货架位置参数
$shelf_location = trim($input['shelf_location'] ?? '');

// 入库时保存货架位置
$stmt = $pdo->prepare("
    INSERT INTO mrs_package_ledger
    (batch_name, tracking_number, box_number, content_note, warehouse_location, ...)
    VALUES (?, ?, ?, ?, ?, ...)
");
// warehouse_location 保存货架位置
```

### 4.3 清点API修改

**文件**: `app/mrs/api/count_save_record.php`

**修改点**:
```php
// 接收货架位置更新
$new_shelf_location = trim($_POST['shelf_location'] ?? '');

// 如果提供了新位置,更新台账
if (!empty($new_shelf_location) && $ledger_id) {
    $update_stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET warehouse_location = ?
        WHERE ledger_id = ?
    ");
    $update_stmt->execute([$new_shelf_location, $ledger_id]);
}
```

---

## 5. 实施计划

### 5.1 阶段一: 数据库准备 (Day 1)

**任务**:
- [ ] 创建 mrs_shelf_locations 表
- [ ] 修改 mrs_sku 表,添加 default_shelf_location 字段
- [ ] 修改 mrs_package_ledger 表,扩展 warehouse_location 字段
- [ ] 创建测试数据(A-E货架,每个货架3层)

**SQL文件**: `app/mrs/migrations/add_shelf_locations.sql`

### 5.2 阶段二: API开发 (Day 2)

**任务**:
- [ ] 开发货架位置管理API
  - `shelf_locations_list.php` - 列表查询
  - `shelf_location_autocomplete.php` - 自动补全
  - `shelf_location_save.php` - 保存/更新
  - `shelf_location_delete.php` - 删除
- [ ] 修改入库API支持货架位置
- [ ] 修改清点API支持货架位置更新

### 5.3 阶段三: 前端界面开发 (Day 3-4)

**任务**:
- [ ] 创建货架位置管理页面
  - `views/shelf_location_manage.php` - 管理界面
  - `actions/shelf_location_manage.php` - 页面控制器
- [ ] 修改入库界面
  - 添加货架位置输入框
  - 实现自动补全功能
  - 添加批量设置选项
- [ ] 修改清点界面
  - 显示当前货架位置
  - 支持位置更新
- [ ] 修改库存查询界面
  - 添加货架位置列
  - 添加货架位置筛选

### 5.4 阶段四: 测试与优化 (Day 5)

**任务**:
- [ ] 功能测试
  - 货架位置管理
  - 入库流程
  - 清点流程
  - 库存查询
- [ ] 性能测试
  - 自动补全响应速度
  - 大数据量查询
- [ ] 用户体验优化
  - 界面调整
  - 交互优化

---

## 6. 使用示例

### 6.1 场景1: 入库时指定货架位置

**步骤**:
1. 操作员进入"快递入库"页面
2. 选择批次和包裹
3. 在"货架位置"输入框输入 "A"
4. 系统自动显示建议: "A货架", "A货架1层", "A货架2层", "A货架3层"
5. 选择 "A货架3层"
6. 勾选"应用到所有包裹"
7. 点击"确认入库"
8. 系统保存所有包裹的货架位置为 "A货架3层"

### 6.2 场景2: 清点时更新货架位置

**步骤**:
1. 操作员进入"库存清点"页面
2. 扫描箱号 "001"
3. 系统显示: 当前位置 "A货架3层"
4. 操作员发现位置有误,输入新位置 "B货架1层"
5. 点击"确认清点"
6. 系统更新该箱的货架位置为 "B货架1层"

### 6.3 场景3: 按货架位置查询

**步骤**:
1. 操作员进入"库存查询"页面
2. 在"货架位置"搜索框输入 "A货架"
3. 系统显示所有在A货架的货物
4. 操作员可以进一步筛选 "A货架3层"
5. 系统显示A货架3层的所有货物明细

---

## 7. 数据字典

### 7.1 mrs_shelf_locations 表

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| location_id | int | 主键ID | 1 |
| shelf_code | varchar(20) | 货架编号 | A |
| shelf_name | varchar(100) | 货架名称 | A货架 |
| level_number | tinyint | 层数(NULL=不分层) | 3 |
| location_full_name | varchar(150) | 完整位置名 | A货架3层 |
| capacity | int | 容量(可选) | 50 |
| current_usage | int | 当前使用量 | 15 |
| zone | varchar(50) | 区域(可选) | 常温区 |
| is_active | tinyint(1) | 是否启用 | 1 |
| sort_order | int | 排序 | 0 |

---

## 8. 注意事项

### 8.1 兼容性

- 货架位置功能为**可选功能**,不影响现有业务流程
- 未填写货架位置的包裹仍可正常入库、出库
- 旧数据 warehouse_location 为NULL,不影响查询

### 8.2 性能优化

- 为 warehouse_location 字段添加索引
- 货架位置自动补全使用缓存机制
- 大数据量查询使用分页

### 8.3 数据规范

- 货架位置名称建议统一格式: "货架编号+层数"
- 避免使用特殊字符
- 建议最大长度不超过50个字符

---

## 9. 后续扩展

### 9.1 V2.0 功能规划

- [ ] 货架位置3D可视化
- [ ] 货架容量预警
- [ ] 智能推荐货架位置(基于FIFO/FEFO)
- [ ] 货架位置导航(路径规划)
- [ ] 移动端扫码更新位置

### 9.2 集成计划

- [ ] 与条码打印系统集成
- [ ] 与手持PDA设备集成
- [ ] 与ERP系统对接

---

## 10. 附录

### 10.1 参考文档

- MRS系统需求文档: `docs/MRS物料收发管理系统_需求与操作手册.md`
- 数据库设计文档: `docs/mrsexp_db_schema_structure_only.sql`

### 10.2 修订历史

| 版本 | 日期 | 修订人 | 说明 |
|------|------|--------|------|
| v1.0 | 2025-12-23 | System | 初始版本 |

---

**文档结束**
