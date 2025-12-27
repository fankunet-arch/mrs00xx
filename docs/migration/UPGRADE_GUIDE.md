# 批次系统升级指南

**版本**: 1.0
**日期**: 2025-12-27
**目的**: EXP批次系统升级 - 自动生成3位数批次编号(000-999)

---

## 升级概述

本次升级将EXP系统的批次管理从手工输入批次名称改为自动生成3位数编号（000-999循环），并对老数据进行清洗，确保MRS系统数据一致性。

### 主要变更

1. **批次创建流程**：
   - **旧流程**：创建批次 → 输入批次名称 → 创建 → 录入快递单号
   - **新流程**：创建批次 → 录入快递单号（或批量导入）→ 点击"创建" → 自动生成批次编号

2. **批次编号规则**：
   - 格式：3位数字（000-999）
   - 循环使用：999后重新从000开始
   - 使用 `batch_cycle` 字段区分不同周期的相同编号

3. **批次编辑**：
   - 批次编号不可修改
   - 可修改：状态、备注

4. **数据清洗**：
   - 老数据按第一个包裹清点时间排序分配新编号
   - 原批次名称保留到 `notes` 字段

---

## 升级前准备

### 1. 数据库备份

**⚠️ 重要：升级前务必备份数据库！**

```bash
# 备份数据库
mysqldump -u your_username -p mhdlmskp2kpxguj > backup_before_upgrade_$(date +%Y%m%d_%H%M%S).sql

# 验证备份文件
ls -lh backup_before_upgrade_*.sql
```

### 2. 检查当前数据状态

```sql
-- 查看当前批次数量
SELECT COUNT(*) as total_batches FROM express_batch;

-- 查看批次示例
SELECT batch_id, batch_name, created_at, total_count
FROM express_batch
ORDER BY created_at DESC
LIMIT 10;

-- 查看MRS台账引用的批次
SELECT DISTINCT batch_name
FROM mrs_package_ledger
ORDER BY batch_name;
```

---

## 升级步骤

### 步骤1: 执行数据库迁移脚本

在phpMyAdmin中执行以下脚本：

**文件位置**: `docs/migration/20251227_upgrade_batch_system.sql`

1. 登录phpMyAdmin
2. 选择数据库 `mhdlmskp2kpxguj`
3. 点击"SQL"标签
4. 复制粘贴迁移脚本内容
5. 点击"执行"

**预期结果**：
- ✓ 添加 `batch_cycle` 字段成功
- ✓ 老数据按规则分配新编号（000开始）
- ✓ MRS系统数据同步完成
- ✓ 显示迁移统计信息

### 步骤2: 验证迁移结果

```sql
-- 1. 检查batch_cycle字段是否添加成功
SHOW COLUMNS FROM express_batch LIKE 'batch_cycle';

-- 2. 检查批次编号格式
SELECT batch_id, batch_name, batch_cycle, notes as original_name, created_at
FROM express_batch
ORDER BY CAST(batch_name AS UNSIGNED), batch_cycle
LIMIT 20;

-- 3. 验证编号唯一性
SELECT batch_name, batch_cycle, COUNT(*) as count
FROM express_batch
GROUP BY batch_name, batch_cycle
HAVING count > 1;
-- 应该返回空结果

-- 4. 检查MRS数据同步
SELECT COUNT(*) as synced_count
FROM mrs_package_ledger
WHERE batch_name REGEXP '^[0-9]{3}$';

-- 5. 检查数据完整性
SELECT
    (SELECT COUNT(*) FROM express_batch) as exp_batches,
    (SELECT COUNT(DISTINCT batch_name) FROM mrs_package_ledger) as mrs_unique_batches;
```

### 步骤3: 部署新代码

升级已修改的文件：
- `app/express/lib/express_lib.php`
- `app/express/views/batch_create.php`
- `app/express/views/batch_edit.php`
- `app/express/api/batch_create_save.php`
- `app/express/api/batch_edit_save.php`

---

## 功能测试

### 测试1: 创建新批次

1. 登录EXP系统
2. 点击"创建批次"
3. 录入快递单号（每行一个）：
   ```
   SF1234567890
   YT9876543210
   JD1122334455
   ```
4. 点击"创建"

**预期结果**：
- ✓ 自动生成批次编号（如 "001"）
- ✓ 成功导入3个快递单号
- ✓ 显示"批次 001 创建成功！导入 3 个快递单号"

### 测试2: 编辑批次

1. 进入批次详情页
2. 点击"编辑"
3. 尝试修改批次编号（应为禁用状态）
4. 修改备注和状态
5. 保存

**预期结果**：
- ✓ 批次编号显示为只读（灰色背景）
- ✓ 可以修改备注和状态
- ✓ 保存成功

### 测试3: 批量导入

1. 创建新批次
2. 使用批量导入格式：
   ```
   SF111|2025-12-31|5
   YT222|2025-11-30|3
   JD333
   ```
3. 创建

**预期结果**：
- ✓ 自动生成下一个批次编号（如 "002"）
- ✓ 正确解析有效期和数量
- ✓ 导入成功

### 测试4: 批次列表

1. 查看批次列表
2. 确认所有批次显示3位数编号

**预期结果**：
- ✓ 新批次显示3位数编号（000-999）
- ✓ 老批次也已更新为3位数编号
- ✓ 列表排序正常

### 测试5: MRS系统关联

1. 登录MRS系统
2. 查看包裹台账
3. 确认batch_name显示为3位数编号

**预期结果**：
- ✓ MRS台账中的批次名称已同步为3位数编号
- ✓ 查询功能正常
- ✓ 无数据丢失

---

## 回滚方案

如果升级出现问题，可以回滚到升级前状态：

```bash
# 1. 恢复数据库备份
mysql -u your_username -p mhdlmskp2kpxguj < backup_before_upgrade_YYYYMMDD_HHMMSS.sql

# 2. 恢复旧版本代码
git checkout HEAD~1 app/express/

# 3. 重启服务（如需要）
```

---

## 常见问题

### Q1: 迁移脚本执行失败怎么办？

**A**: 检查错误信息，常见原因：
- 数据库连接权限不足
- 表结构已被修改
- 数据冲突

解决方法：
1. 检查数据库用户权限
2. 恢复备份，重新执行
3. 联系技术支持

### Q2: 老批次的编号分配顺序不符合预期？

**A**: 编号分配规则：
- 第一梯队：有清点记录的批次，按第一个包裹的 `counted_at` 升序
- 第二梯队：无清点记录的批次，按批次 `created_at` 升序

如需调整，可以手工修改SQL脚本中的排序逻辑。

### Q3: 达到999后会怎样？

**A**: 系统会自动循环到000，并将 `batch_cycle` 加1。例如：
- 第一轮: 000-999 (batch_cycle=1)
- 第二轮: 000-999 (batch_cycle=2)

### Q4: 如何查看批次的原始名称？

**A**: 原始批次名称已保存在 `notes` 字段，可以在批次详情或编辑页面查看。

---

## 技术细节

### 数据库schema变更

```sql
-- 添加字段
ALTER TABLE `express_batch`
ADD COLUMN `batch_cycle` INT UNSIGNED NOT NULL DEFAULT 1
COMMENT '批次周期（用于区分循环使用的相同编号）'
AFTER `batch_name`;

-- 修改唯一约束
ALTER TABLE `express_batch`
DROP INDEX `uk_batch_name`;

ALTER TABLE `express_batch`
ADD UNIQUE KEY `uk_batch_name_cycle` (`batch_name`, `batch_cycle`);
```

### 批次编号生成算法

```php
function express_generate_next_batch_number($pdo) {
    // 获取当前最大编号和周期
    $stmt = $pdo->prepare("
        SELECT batch_name, batch_cycle
        FROM express_batch
        ORDER BY batch_cycle DESC, batch_name DESC
        LIMIT 1
    ");
    $stmt->execute();
    $last_batch = $stmt->fetch();

    if (!$last_batch) {
        return ['batch_name' => '000', 'batch_cycle' => 1];
    }

    $current_number = intval($last_batch['batch_name']);
    $current_cycle = intval($last_batch['batch_cycle']);

    if ($current_number < 999) {
        $next_number = $current_number + 1;
        $next_cycle = $current_cycle;
    } else {
        $next_number = 0;
        $next_cycle = $current_cycle + 1;
    }

    return [
        'batch_name' => str_pad($next_number, 3, '0', STR_PAD_LEFT),
        'batch_cycle' => $next_cycle
    ];
}
```

---

## 联系支持

如果升级过程中遇到任何问题，请联系技术支持。

**升级完成后请保留本文档和备份文件至少30天。**
