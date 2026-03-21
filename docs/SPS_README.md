# SPS 备货规划系统 · 技术说明书

> 文件路径：`docs/SPS_README.md`
> 最后更新：2026-03-21
> 版本：v1.0

---

## 目录

1. [系统简介](#1-系统简介)
2. [技术栈与依赖](#2-技术栈与依赖)
3. [目录结构](#3-目录结构)
4. [数据库设计](#4-数据库设计)
5. [系统架构](#5-系统架构)
6. [部署步骤](#6-部署步骤)
7. [用户角色与权限](#7-用户角色与权限)
8. [用户使用场景](#8-用户使用场景)
9. [核心业务流程](#9-核心业务流程)
10. [API 接口说明](#10-api-接口说明)
11. [配置说明](#11-配置说明)
12. [常见问题与运维](#12-常见问题与运维)

---

## 1. 系统简介

**SPS（Stock Planning System）备货规划系统**是一套面向餐厅的采购协同工具。

### 解决的问题

传统流程中，各部门（寿司房、热厨、跑堂）通过微信或口头告知采购数量，信息分散、易遗漏。SPS 将这一流程数字化：

- 各部门 Staff 在手机/电脑上填报本部门所需食材数量
- 管理员在一个视图中看到全部汇总，并标记采购状态
- 每次采购完成后系统自动开启下一轮，历史数据保留可查

### 系统边界

| 特性 | 说明 |
|------|------|
| 独立于 MRS | 共用同一 MySQL 数据库，但表名以 `sps_` 开头，会话与 MRS 完全隔离 |
| 无需框架 | 原生 PHP + PDO，零外部依赖 |
| 前后台分离 URL | 后台 `/sps/ap/`，前台 `/sps/` |

---

## 2. 技术栈与依赖

| 层次 | 技术 |
|------|------|
| 后端语言 | PHP 8.0+ |
| 数据库 | MySQL 5.7+ / MariaDB 10.4+（与 MRS 共库） |
| 数据库驱动 | PDO（PDO_MySQL） |
| 前端 | 原生 HTML + CSS + JavaScript（无框架） |
| 会话 | PHP 原生 Session（独立 Session Name：`SPS_SESSION`） |
| 密码加密 | PHP `password_hash()` / `password_verify()`（bcrypt） |

**无需安装任何 Composer 包或 npm 依赖。**

---

## 3. 目录结构

```
mrs00xx/
├── app/
│   └── sps/                          # SPS 应用逻辑层（不对外暴露）
│       ├── bootstrap.php             # 启动文件：加载配置、建立DB连接、启动Session
│       ├── config_sps/
│       │   └── env_sps.php           # 数据库配置、常量定义、公共函数
│       ├── api/
│       │   ├── backend/              # 管理员操作接口（POST → JSON）
│       │   │   ├── do_login.php      # 后台登录处理
│       │   │   ├── do_logout.php     # 后台登出
│       │   │   ├── round_create.php  # 创建采购轮次
│       │   │   ├── round_complete.php# 完成轮次 + 自动开启下一轮
│       │   │   ├── purchase_status.php # 更新商品采购状态
│       │   │   ├── product_save.php  # 保存商品（新增/编辑）
│       │   │   ├── product_delete.php# 删除商品（有记录时拒绝）
│       │   │   ├── supplier_save.php # 保存供货商
│       │   │   ├── user_save.php     # 保存用户
│       │   │   ├── dept_save.php     # 保存部门
│       │   │   └── dept_breakdown.php# 获取商品各部门明细
│       │   └── frontend/             # Staff 操作接口
│       │       ├── do_login.php      # 前台登录处理
│       │       ├── do_logout.php     # 前台登出
│       │       └── entry_save.php    # 保存填报数量（自动保存）
│       └── views/
│           ├── shared/
│           │   └── sidebar_backend.php # 后台侧边栏导航组件
│           ├── backend/              # 管理员页面（PHP 渲染）
│           │   ├── login.php         # 后台登录页
│           │   ├── dashboard.php     # 仪表盘
│           │   ├── rounds.php        # 轮次列表
│           │   ├── round_detail.php  # 采购视图（核心页面）
│           │   ├── products.php      # 商品列表
│           │   ├── product_edit.php  # 商品编辑/新增
│           │   ├── suppliers.php     # 供货商管理
│           │   ├── departments.php   # 部门管理
│           │   └── users.php         # 用户管理
│           └── frontend/             # Staff 页面
│               ├── login.php         # 前台登录页
│               └── entry.php         # 填报页（核心页面）
│
├── dc_html/
│   └── sps/                          # Web 根目录（对外暴露）
│       ├── index.php                 # 前台路由入口
│       ├── ap/
│       │   └── index.php             # 后台路由入口
│       └── assets/
│           └── css/
│               └── sps.css           # 全局样式
│
└── docs/
    └── migrations/
        └── create_sps_tables.sql     # 数据库建表脚本（一次性执行）
```

### 访问路径对照

| 访问 URL | 实际入口文件 |
|----------|-------------|
| `/sps/ap/index.php?action=dashboard` | `dc_html/sps/ap/index.php` → `app/sps/views/backend/dashboard.php` |
| `/sps/index.php?action=entry` | `dc_html/sps/index.php` → `app/sps/views/frontend/entry.php` |
| `/sps/ap/index.php?action=product_save` (POST) | `dc_html/sps/ap/index.php` → `app/sps/api/backend/product_save.php` |

---

## 4. 数据库设计

### 表关系概览

```
sps_users ──────────────────── sps_user_departments ──── sps_departments
    │                                                          │
    │ (updated_by)                                             │
    ▼                                                          │
sps_round_entries ◄──────── sps_rounds                        │
    │                           │                             │
    │ (product_id)              │ (round_id)                  │
    ▼                           ▼                             ▼
sps_products ──── sps_product_departments ──────────── sps_departments
    │
    └──── sps_suppliers
              │
    sps_round_purchase (round_id + product_id → 采购状态)
```

### 各表说明

#### `sps_users` - 用户表

| 字段 | 类型 | 说明 |
|------|------|------|
| `user_id` | INT UNSIGNED PK | 自增主键 |
| `username` | VARCHAR(50) UNIQUE | 登录用户名 |
| `password_hash` | VARCHAR(255) | bcrypt 密码哈希 |
| `display_name` | VARCHAR(100) | 页面显示名 |
| `role` | ENUM('admin','staff') | 角色 |
| `status` | ENUM('active','inactive') | 启用状态 |

#### `sps_departments` - 部门表

预置三个部门：寿司房（1）、热厨（2）、跑堂（3）。支持后台增删。

| 字段 | 说明 |
|------|------|
| `dept_id` | 主键 |
| `dept_name` | 部门名称（唯一） |
| `sort_order` | 排序权重（越小越靠前） |

#### `sps_user_departments` - 用户-部门关联

多对多。一个 staff 可属于多个部门，填报时分别显示各部门的商品。

#### `sps_suppliers` - 供货商表

| 字段 | 说明 |
|------|------|
| `supplier_name` | 唯一 |
| `contact_name` | 联系人（可选） |
| `contact_phone` | 电话（可选） |
| `sort_order` | 在采购视图中的分组排列顺序 |

#### `sps_products` - 商品表

| 字段 | 说明 |
|------|------|
| `name_cn` | 中文名称 |
| `name_es` | 西班牙语名称（可选，方便西班牙语采购员） |
| `supplier_id` | 关联供货商 |
| `unit` | **固定采购单位**（如：公斤、瓶、箱），填报和汇总时统一显示 |
| `sort_order` | 在填报页中的显示顺序 |

#### `sps_product_departments` - 商品-部门关联

控制哪些商品对哪些部门可见。Staff 只看到自己部门的商品。

#### `sps_rounds` - 采购轮次表

| 字段 | 说明 |
|------|------|
| `round_year` / `round_month` | 年月（用于计算本月第几次） |
| `order_in_month` | 本月第几次（从1开始） |
| `label` | 显示标签，如 `2月 第1次` |
| `status` | `open`（进行中）/ `completed`（已完成） |

**约束**：同一时间只能有一个 `open` 状态的轮次（由应用层保证）。

#### `sps_round_entries` - 各部门填报明细

| 字段 | 说明 |
|------|------|
| 唯一键 | `(round_id, product_id, dept_id)` |
| `qty` | 填报数量（DECIMAL(10,2)，支持小数） |
| `unit` | 冗余存储商品单位（历史快照） |
| `updated_by` | 最后保存的用户 ID |

#### `sps_round_purchase` - 采购状态跟踪

每轮每商品一条记录，管理员维护。

| `purchase_status` 值 | 含义 |
|---------------------|------|
| `pending` | 待采购（默认） |
| `purchased` | 已采购 |
| `out_of_stock` | 缺货（仅作历史记录，下轮重新填报） |

---

## 5. 系统架构

### 请求处理流程

```
浏览器请求
    │
    ▼
dc_html/sps/[ap/]index.php   ← Web 对外入口（路由器）
    │
    ├── 定义常量 SPS_ENTRY=true
    ├── 定义 PROJECT_ROOT
    └── require bootstrap.php
            │
            ├── require config_sps/env_sps.php  ← 常量、函数、DB连接
            ├── sps_get_db()                     ← 建立 PDO 单例
            └── sps_session_start()             ← 启动独立 Session
                    │
                    ▼
                鉴权检查（sps_require_admin / sps_require_login）
                    │
            ┌───────┴────────┐
            │                │
        页面请求           API 请求
            │                │
    app/sps/views/...  app/sps/api/...
    （渲染 HTML）      （返回 JSON）
```

### 会话隔离

SPS 使用独立的 Session 名称 `SPS_SESSION`（与 MRS 的 `PHPSESSID` 不冲突）：

```
Cookie: SPS_SESSION=xxxx   ← SPS 专属
Cookie: PHPSESSID=yyyy     ← MRS 专属（互不干扰）
```

Session 有效期 2 小时（`SPS_SESSION_TIMEOUT = 7200`）。

### 安全措施

- 所有入口文件检查 `SPS_ENTRY` 常量（防止直接访问 `app/` 目录下的文件）
- 所有数据库操作使用 PDO 预处理语句（防 SQL 注入）
- 密码使用 bcrypt 哈希存储
- Session Cookie 设置 `HttpOnly=true`、`SameSite=Strict`
- 操作前验证用户归属（Staff 只能操作自己部门的数据）

---

## 6. 部署步骤

### 6.1 首次部署

**第一步：执行数据库迁移**

登录 MySQL，执行建表脚本：

```sql
USE mhdlmskp2kpxguj;
SOURCE /path/to/mrs00xx/docs/migrations/create_sps_tables.sql;
```

或通过 phpMyAdmin 导入 `docs/migrations/create_sps_tables.sql`。

脚本会创建 9 张表，并预插入三个部门（寿司房/热厨/跑堂）。脚本使用 `CREATE TABLE IF NOT EXISTS`，**重复执行安全**。

---

**第二步：创建第一个管理员账号**

在 PHP 中生成密码哈希：

```php
<?php echo password_hash('你的密码', PASSWORD_BCRYPT); ?>
```

然后插入数据库：

```sql
INSERT INTO sps_users (username, password_hash, display_name, role, status)
VALUES ('admin', '$2y$10$...（上面生成的哈希）', '管理员', 'admin', 'active');
```

**也可以通过后台「用户管理」页面添加**，但必须先有至少一个 admin 才能登录后台。

---

**第三步：验证 Web 访问**

| URL | 预期结果 |
|-----|---------|
| `/sps/ap/index.php?action=login` | 显示后台登录页 |
| `/sps/index.php?action=login` | 显示前台登录页 |

---

### 6.2 初始化基础数据（建议顺序）

1. **登录后台** `/sps/ap/index.php?action=login`
2. **供货商管理** → 添加供货商（如：某某水产、某某蔬菜）
3. **商品管理** → 添加商品，设置中文名/西班牙语名/单位/供货商/归属部门
4. **部门管理** → 如需新增部门（默认已有寿司房/热厨/跑堂）
5. **用户管理** → 为每个 Staff 创建账号，分配部门
6. **采购轮次** → 点击「创建新轮次」，开始第一轮

---

### 6.3 文件权限

`app/sps/` 目录不应直接对外 Web 访问。如使用 Nginx，可在配置中：

```nginx
location /app/ {
    deny all;
}
```

---

## 7. 用户角色与权限

### 角色对照表

| 功能 | admin（管理员） | staff（员工） |
|------|:--------------:|:-------------:|
| 登录后台 `/sps/ap/` | ✅ | ❌ |
| 登录前台 `/sps/` | ✅ | ✅ |
| 查看仪表盘 | ✅ | ❌ |
| 创建/完成采购轮次 | ✅ | ❌ |
| 更新商品采购状态 | ✅ | ❌ |
| 查看各部门汇总 | ✅ | ❌ |
| 填报本部门采购量 | ✅（前台） | ✅ |
| 商品/供货商/部门/用户管理 | ✅ | ❌ |

### Staff 的数据隔离

- Staff 只能看到**自己归属部门**的商品
- Staff 只能操作**自己部门**的填报（API 层二次验证）
- 多部门 Staff 可看到所有归属部门的商品，分区块显示

---

## 8. 用户使用场景

### 场景 A：每周采购流程（标准流程）

```
周一上午 10:00
    │
    ├─ 管理员登录后台
    ├─ 进入「采购轮次」
    ├─ 点击「创建新轮次」→ 系统自动命名「3月 第1次」
    │
    ├─ 【通知各部门 Staff 填报】（如：在工作群发消息）
    │
    ├─ 寿司房 Staff 登录前台 → 填写各食材用量（实时自动保存）
    ├─ 热厨 Staff 登录前台 → 填写本部门用量
    ├─ 跑堂 Staff 登录前台 → 填写饮料等用量
    │
    ├─ 管理员进入「采购视图」→ 查看按供货商分组的汇总
    ├─ 开始采购，每采购一个商品点击「✓ 已采购」
    ├─ 某些商品缺货 → 点击「✗ 缺货」（记录在案，不影响当轮）
    │
    └─ 全部处理完毕 → 点击「完成本次采购」
         └─ 系统自动创建「3月 第2次」（如果这周还要再采购）
```

---

### 场景 B：同月多次采购

系统按月内顺序自动编号：

| 完成时间 | 生成标签 |
|---------|---------|
| 3月第1次完成 | 自动创建「3月 第2次」 |
| 3月第2次完成（4月了） | 自动创建「4月 第1次」 |
| 4月第1次完成（还在4月） | 自动创建「4月 第2次」 |

月份判断基于**完成时的实际日期**，无需手动设置。

---

### 场景 C：Staff 跨部门操作

寿司房主管同时管理热厨：

1. 管理员在「用户管理」中，给该 Staff 同时勾选「寿司房」和「热厨」
2. Staff 登录前台后看到两个区块：
   ```
   ┌─ 🏢 寿司房 ────────────────────┐
   │  三文鱼    [  5  ] 公斤        │
   │  牛油果    [  3  ] 个          │
   └────────────────────────────────┘
   ┌─ 🏢 热厨 ──────────────────────┐
   │  牛腩      [  8  ] 公斤        │
   │  猪骨      [ 10  ] 公斤        │
   └────────────────────────────────┘
   ```

---

### 场景 D：查看历史采购记录

管理员进入「采购轮次」→ 点击已完成轮次的「查看记录」：
- 看到该轮次所有商品及采购状态
- 点击汇总数量可弹出各部门明细

---

### 场景 E：商品缺货的处理

1. 某轮次中，「进口鱼子酱」标记为「缺货」
2. 管理员完成本轮次 → 系统开启下一轮
3. 下一轮各部门**重新填报**（「进口鱼子酱」从零开始，不自动继承）
4. 历史记录中可查阅上一轮该商品的缺货状态

---

### 场景 F：新增商品

1. 管理员 → 商品管理 → 新增商品
2. 填写：中文名「大闸蟹」，西班牙语名「cangrejo peludo」，单位「只」
3. 供货商选「某某水产」
4. 归属部门勾选「寿司房」
5. 保存后，**下一轮开启后**，寿司房 Staff 的填报页即可看到该商品

> 注意：当前进行中轮次不会自动更新商品列表（因为填报已经开始）。若需要在当前轮次生效，管理员可完成当前轮并立即开启新轮。

---

## 9. 核心业务流程

### 轮次状态机

```
                    创建轮次
                       │
                       ▼
                    [ open ]
                    进行中
                  ↗         ↘
      Staff 填报数量       管理员标记采购状态
      （随时可改）        pending/purchased/out_of_stock
                       │
                  点击「完成本次采购」
                       │
                       ▼
                  [ completed ]  ← 不可修改
                  已完成
                       │
                  自动创建新轮次
                       ▼
                    [ open ]
                  下一轮开始
```

### 填报数据保存机制

Staff 修改数量后，**600ms 防抖**触发自动保存：

```
用户输入 → 600ms 倒计时 → 发 POST → API upsert → 返回成功
               │
               │（如果在600ms内再次输入）
               └─ 重置倒计时（不重复发请求）
```

保存状态反馈：
- **橙色圆点** = 正在保存
- **绿色圆点** = 已保存（2秒后消失）
- **红色边框** = 保存失败（检查网络）

---

## 10. API 接口说明

所有 API 均为 `POST`，请求体 `Content-Type: application/json`，返回 JSON。

### 统一响应格式

```json
{
  "success": true | false,
  "data": { ... } | null,
  "message": "操作说明"
}
```

### 后台 API（需 admin 登录）

| 入口 | 功能 | 关键参数 |
|------|------|---------|
| `?action=round_create` | 创建新轮次 | `remark?` |
| `?action=round_complete` | 完成轮次 | `round_id` |
| `?action=purchase_status` | 更新采购状态 | `round_id`, `product_id`, `status` |
| `?action=product_save` | 保存商品 | `name_cn`, `unit`, `dept_ids[]`, `product_id?` |
| `?action=product_delete` | 删除商品 | `product_id` |
| `?action=supplier_save` | 保存供货商 | `supplier_name`, `supplier_id?` |
| `?action=user_save` | 保存用户 | `username`, `display_name`, `role`, `user_id?` |
| `?action=dept_save` | 保存部门 | `dept_name`, `dept_id?` |

### 前台 API（需登录，staff 或 admin）

| 入口 | 功能 | 关键参数 |
|------|------|---------|
| `?action=entry_save` | 保存填报 | `round_id`, `product_id`, `dept_id`, `qty` |

**`entry_save` 验证逻辑：**

1. 轮次必须为 `open` 状态
2. 当前用户必须归属于该 `dept_id`
3. 该商品必须关联到该 `dept_id`

三条验证均通过才会写入数据库。

---

## 11. 配置说明

### 数据库配置

位于 `app/sps/config_sps/env_sps.php`：

```php
define('SPS_DB_HOST',    'mhdlmskp2kpxguj.mysql.db');
define('SPS_DB_PORT',    '3306');
define('SPS_DB_NAME',    'mhdlmskp2kpxguj');
define('SPS_DB_USER',    'mhdlmskp2kpxguj');
define('SPS_DB_PASS',    'BWNrmksqMEqgbX37r3QNDJLGRrUka');
```

与 MRS 系统使用**同一个数据库实例**，各表通过 `sps_` 前缀隔离。

### 会话配置

```php
define('SPS_SESSION_NAME',    'SPS_SESSION');  // Cookie 名
define('SPS_SESSION_TIMEOUT', 7200);            // 2小时（秒）
```

### 路由白名单

新增页面时，在对应入口文件的 `$page_actions` 数组中添加 action 名；新增 API 时在 `$api_actions` 中添加。

---

## 12. 常见问题与运维

### Q1：忘记管理员密码怎么办？

直接通过 MySQL 更新密码哈希：

```php
// 先用 PHP 生成新的 bcrypt 哈希
echo password_hash('新密码', PASSWORD_BCRYPT);
```

```sql
UPDATE sps_users SET password_hash='$2y$10$...' WHERE username='admin';
```

---

### Q2：如何查看某次采购的完整汇总？

进入后台 → 采购轮次 → 找到对应轮次 → 点击「查看记录」→ 进入采购视图。

采购视图按供货商分组，点击商品的汇总数量可弹出各部门明细。

---

### Q3：Staff 填报后页面没有保存提示？

检查：
1. 浏览器控制台是否有 JS 错误
2. 网络请求 `/sps/index.php?action=entry_save` 是否返回 `{"success":true}`
3. 轮次是否已被管理员关闭（`status=completed`）

---

### Q4：一个商品不想让某部门看到怎么办？

进入商品管理 → 编辑该商品 → 取消勾选对应部门 → 保存。下次填报时该部门 Staff 不会看到此商品（已有的历史填报数据不受影响）。

---

### Q5：如何停用一个 Staff 账号？

用户管理 → 编辑该用户 → 状态改为「停用」→ 保存。该用户下次登录时会被拒绝（`status='active'` 检查）。已登录的 Session 在 2 小时后自动失效。

---

### Q6：数据库备份建议

SPS 相关的 9 张表：

```sql
-- 导出所有 SPS 表
mysqldump -u用户名 -p 数据库名 \
  sps_users sps_departments sps_user_departments \
  sps_suppliers sps_products sps_product_departments \
  sps_rounds sps_round_entries sps_round_purchase \
  > sps_backup_$(date +%Y%m%d).sql
```

---

### Q7：如何重置某轮次的某商品的缺货状态？

通过后台采购视图，点击该商品旁的「↺」撤销按钮（仅在轮次 `open` 时可操作），将状态改回 `pending`。

---

*SPS 备货规划系统 v1.0 · 说明书结束*
