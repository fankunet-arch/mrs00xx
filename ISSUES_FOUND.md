# 单页面极速收货 - 发现的问题清单

## 🔴 严重问题（阻塞性）

### 问题 1: 数据库连接配置错误
**严重程度**: 🔴 P0 - 严重（阻塞所有功能）
**文件**: `/app/mrs/config_mrs/env_mrs.php:17`
**行号**: 17

**问题描述**:
```php
define('DB_HOST', 'mhdlmskp2kpxguj.mysql.db');
```
数据库主机名无法在本地环境解析，导致所有API调用失败。

**测试结果**:
```
SQLSTATE[HY000] [2002] php_network_getaddresses:
getaddrinfo for mhdlmskp2kpxguj.mysql.db failed:
Temporary failure in name resolution
```

**影响的功能**:
- ❌ 获取批次列表（api.php?route=batch_list）
- ❌ 搜索物料（api.php?route=sku_search）
- ❌ 保存记录（api.php?route=save_record）
- ❌ 加载批次记录（api.php?route=batch_records）

**修复方案**:
1. **本地开发环境**: 使用 `localhost` 或 `127.0.0.1`
2. **生产环境**: 确保主机名可以正确解析或使用IP地址
3. **推荐**: 使用环境变量管理不同环境的配置

---

## 🟡 重要问题

### 问题 2: 候选列表初始化不一致
**严重程度**: 🟡 P1 - 高
**文件**: `/dc_html/mrs/js/receipt.js`
**行号**: 433-467 (initApp函数)

**问题描述**:
外部JS文件版本的 `initApp()` 函数没有调用 `renderCandidates()` 进行初始化，而独立HTML版本有调用。

**代码比较**:
```javascript
// 独立HTML版本 (frontend/receipt.html:288)
renderCandidates();  // ✅ 有调用

// 外部JS版本 (dc_html/mrs/js/receipt.js:433-467)
// ❌ 没有调用 renderCandidates()
```

**影响**:
- 候选列表初始状态不会被隐藏
- 页面加载时可能显示空的候选列表边框（视觉问题）

**修复方案**:
在 `initApp()` 函数的渲染部分添加：
```javascript
// 渲染初始界面
renderBatches();
renderBatchInfo();
renderUnits();
renderCandidates();  // 添加这一行
```

---

### 问题 3: 候选列表缺少默认隐藏样式
**严重程度**: 🟡 P1 - 高
**文件**: `/dc_html/mrs/css/receipt.css`
**行号**: 102-107

**问题描述**:
`.candidate-list` 样式没有设置默认的 `display: none;`，依赖JavaScript动态设置显示隐藏。

**当前CSS**:
```css
.candidate-list {
  margin-top: 10px;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  /* 缺少 display: none; */
}
```

**影响**:
- 如果JavaScript加载失败或延迟，候选列表会始终显示
- 配合问题2，会在页面初始化时显示空边框

**修复方案**:
添加默认隐藏样式：
```css
.candidate-list {
  margin-top: 10px;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  display: none;  /* 添加这一行 */
}
```

---

## 🔵 次要问题

### 问题 4: 操作员信息硬编码
**严重程度**: 🔵 P2 - 中
**文件**: `/dc_html/mrs/js/receipt.js`
**行号**: 404

**问题描述**:
```javascript
operator_name: '操作员', // TODO: 从登录系统获取
```
操作员信息使用硬编码，无法追踪实际操作人员。

**影响**:
- 无法追溯具体操作人员
- 记录的可审计性降低
- 安全性问题

**修复方案**:
1. 实现用户登录系统
2. 从session或cookie获取当前用户信息
3. 传递到前端并用于记录保存

---

### 问题 5: 缺少CSRF防护
**严重程度**: 🔵 P2 - 中
**文件**: `/app/mrs/api/save_record.php`

**问题描述**:
API接口没有CSRF token验证，存在跨站请求伪造风险。

**影响**:
- 安全漏洞
- 可能被恶意利用

**修复方案**:
1. 生成CSRF token并存储在session
2. 前端请求时携带token
3. 后端验证token有效性

---

## 📊 完整问题统计

| 严重程度 | 数量 | 问题列表 |
|---------|-----|---------|
| 🔴 P0 - 严重 | 1 | 数据库连接 |
| 🟡 P1 - 高 | 2 | 候选列表初始化、默认隐藏样式 |
| 🔵 P2 - 中 | 2 | 操作员硬编码、CSRF防护 |
| **总计** | **5** | |

---

## 🎯 修复优先级

### 立即修复（阻塞部署）
1. ✅ 问题1: 数据库连接配置

### 优先修复（影响用户体验）
2. ⏳ 问题2: 候选列表初始化
3. ⏳ 问题3: 默认隐藏样式

### 计划修复（功能完善）
4. 🔜 问题4: 操作员信息
5. 🔜 问题5: CSRF防护

---

## 📝 修复说明

### 为什么问题2和3很重要？
虽然这两个问题不会导致功能完全失效，但会影响用户体验：
- 页面加载时显示不应该出现的空边框
- 两个版本（独立HTML vs PHP集成）行为不一致
- 如果JavaScript加载失败，候选列表会始终可见

### 为什么问题1最严重？
数据库连接失败会导致：
- 100%的后端功能不可用
- 页面无法加载任何数据
- 无法保存任何记录
- **完全阻塞系统使用**

---

**生成时间**: 2025-11-24
**审计人员**: 资深代码审计专家
**下一步**: 按优先级逐个修复问题
