# SKU搜索功能完全不可用 - 根本原因分析

## 🔍 问题定位

### 问题 1: 管理台<物料档案 (SKU)>页面搜索功能不可用

#### 现象
用户点击"搜索"按钮后，页面没有任何反应，搜索功能完全不可用。

#### 根本原因分析

**文件**: `/dc_html/mrs/js/backend.js:303-311`

```javascript
// ❌ 错误的实现
async function loadSkus() {
  const result = await api.getSkus();  // 没有传递任何参数！
  if (result.success) {
    appState.skus = result.data;
    renderSkus();
  } else {
    showAlert('danger', '加载SKU列表失败: ' + result.message);
  }
}
```

**问题点**：
1. ✅ HTML页面有搜索输入框（ID: `catalog-filter-search`）
2. ✅ HTML页面有品类筛选（ID: `catalog-filter-category`）
3. ✅ HTML页面有类型筛选（ID: `catalog-filter-type`）
4. ❌ **但是 `loadSkus()` 函数完全没有读取这些输入框的值**
5. ❌ **`api.getSkus()` 被调用时没有传递任何过滤参数**
6. ✅ 后端API `backend_skus.php` 可以正确处理 `search`, `category_id`, `is_precise_item` 参数
7. ❌ **前端根本没有发送这些参数**

#### 数据流分析

```
用户操作流程:
1. 用户在 <input id="catalog-filter-search"> 输入"糖浆"
2. 用户点击 <button onclick="loadSkus()">搜索</button>
3. loadSkus() 被调用
4. api.getSkus() 被调用，参数为空对象 {}
5. 后端收到请求: api.php?route=backend_skus (没有search参数)
6. 后端返回所有SKU（因为没有筛选条件）
7. 前端显示所有SKU（不是搜索结果）

预期流程:
1. 用户输入"糖浆"
2. 点击搜索
3. loadSkus() 读取输入框值
4. api.getSkus({search: "糖浆"}) 被调用
5. 后端收到: api.php?route=backend_skus&search=糖浆
6. 后端执行搜索逻辑
7. 返回匹配的SKU
```

---

### 问题 2: <单页面极速收货>页面搜索功能不可用

#### 现象
用户在物料搜索框输入关键词后，候选列表不显示或显示空结果。

#### 需要检查的点

**文件**: `/dc_html/mrs/js/receipt.js:211-269`

```javascript
async function renderCandidates(keyword = '') {
  const lower = keyword.trim().toLowerCase();

  // ... 省略重置逻辑

  if (!lower) {
    dom.candidateList.innerHTML = '';
    dom.candidateList.style.display = 'none';
    return;
  }

  // 从 API 获取搜索结果
  const results = await api.searchSku(lower);  // ✅ 正确传递keyword

  // ... 渲染逻辑
}
```

**对应的API调用**：
```javascript
async searchSku(keyword) {
  try {
    const response = await fetch(`api.php?route=sku_search&keyword=${encodeURIComponent(keyword)}`);
    // ...
  } catch (error) {
    console.error('API 错误:', error);
    return [];
  }
}
```

**后端API**: `/app/mrs/api/sku_search.php:22`
```php
$keyword = $_GET['keyword'] ?? '';
if (empty($keyword)) {
    json_response(true, [], '关键词为空');  // ⚠️ 返回空数组
}
```

#### 潜在问题

1. ✅ 前端正确传递keyword参数
2. ✅ 后端正确接收keyword参数
3. ⚠️ **但是如果数据库连接失败，搜索会返回空数组**
4. ⚠️ **如果数据库中没有SKU数据，搜索也会返回空**

---

## 📊 问题优先级

| 序号 | 问题 | 严重程度 | 影响范围 |
|------|------|----------|----------|
| 1 | 管理台SKU搜索不传参数 | 🔴 P0 严重 | 管理台完全无法搜索 |
| 2 | 数据库连接未测试 | 🔴 P0 严重 | 所有功能不可用 |
| 3 | 收货页面搜索依赖数据 | 🟡 P1 高 | 如果无数据则无法搜索 |

---

## 🎯 修复方案

### 修复 1: 管理台SKU搜索功能

**需要修改**: `/dc_html/mrs/js/backend.js:303-311`

```javascript
// ❌ 修复前
async function loadSkus() {
  const result = await api.getSkus();
  // ...
}

// ✅ 修复后
async function loadSkus() {
  // 读取筛选条件
  const filters = {
    search: document.getElementById('catalog-filter-search')?.value || '',
    category_id: document.getElementById('catalog-filter-category')?.value || '',
    is_precise_item: document.getElementById('catalog-filter-type')?.value || ''
  };

  // 传递筛选参数
  const result = await api.getSkus(filters);

  if (result.success) {
    appState.skus = result.data;
    renderSkus();
  } else {
    showAlert('danger', '加载SKU列表失败: ' + result.message);
  }
}
```

### 修复 2: 确保API正确构建URL

**需要检查**: `/dc_html/mrs/js/backend.js:137-140`

```javascript
async getSkus(filters = {}) {
  const params = new URLSearchParams(filters);
  return await this.call(`api.php?route=backend_skus&${params}`);
}
```

**问题**: 空字符串参数会被URLSearchParams包含

**修复**: 过滤空值参数

```javascript
async getSkus(filters = {}) {
  // 过滤空值
  const cleanFilters = Object.fromEntries(
    Object.entries(filters).filter(([_, value]) => value !== '' && value !== null && value !== undefined)
  );
  const params = new URLSearchParams(cleanFilters);
  const url = `api.php?route=backend_skus${params.toString() ? '&' + params : ''}`;
  return await this.call(url);
}
```

### 修复 3: 测试数据库连接

创建测试脚本验证数据库连接和数据：

```php
<?php
define('MRS_ENTRY', true);
require_once './app/mrs/config_mrs/env_mrs.php';

try {
    $pdo = get_db_connection();
    echo "✅ 数据库连接成功\n";

    // 测试查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_sku");
    $count = $stmt->fetchColumn();
    echo "✅ SKU数量: $count\n";

    if ($count == 0) {
        echo "⚠️ 警告: 数据库中没有SKU数据，搜索将返回空结果\n";
    }

} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}
```

---

## 🧪 测试验证

### 测试场景 1: 管理台SKU搜索

1. 打开管理台: `https://[Domain]/mrs/backend.php`
2. 点击"物料档案(SKU)"菜单
3. 在搜索框输入"糖浆"
4. 点击"搜索"按钮
5. **预期结果**: 只显示包含"糖浆"的SKU
6. **修复前**: 显示所有SKU（因为没有传递搜索参数）

### 测试场景 2: 单页面极速收货搜索

1. 打开收货页面: `https://[Domain]/mrs/`
2. 在"物料搜索"框输入"糖浆"
3. **预期结果**: 显示候选列表
4. **修复前可能情况**:
   - 数据库连接失败 → 无候选列表
   - 数据库无数据 → 显示"未找到匹配的物料"

---

## 🔑 关键发现

### 为什么修复多次仍然失败？

1. **之前的修复重点是数据库连接配置**，添加了环境变量支持
2. **但没有发现前端根本没有传递搜索参数**
3. **即使数据库连接正常，搜索功能也不会工作**，因为：
   - 前端调用 `api.getSkus()` 时参数为空
   - 后端收到的请求没有 `search` 参数
   - 后端执行查询时 `WHERE 1=1`（没有搜索条件）
   - 返回所有SKU，而不是搜索结果

### 这是一个典型的"前后端对接"问题

- ✅ 后端API实现正确，可以处理搜索参数
- ✅ 后端SQL逻辑正确，可以执行LIKE搜索
- ❌ **前端调用时没有传递参数**
- ❌ **导致搜索功能完全不可用**

---

## 📝 总结

**根本原因**: 前端JavaScript代码的 `loadSkus()` 函数没有读取搜索输入框的值，也没有将这些值传递给API调用。

**影响范围**: 管理台<物料档案(SKU)>页面的搜索功能100%不可用。

**修复难度**: 低（仅需修改一个函数）

**测试难度**: 中（需要数据库连接和测试数据）

---

生成时间: 2025-11-24
分析人员: 资深代码审计专家
