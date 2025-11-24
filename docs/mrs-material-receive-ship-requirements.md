# MRS 物料收发管理系统需求说明（Draft v0.1）

项目代号：MRS（Material Receive & Ship）  
所属平台：ABCABC · DC（Data Collection）  
子系统目标范围：入库 + 出库管理，当前优先「预计收货 + 极速入库」。

## 1. 项目概述
MRS 物料收发管理系统用于记录和管理所有物料从进入库存（入库）到离开库存（出库）的关键动作。

本项目的核心诉求并非立即实现“全功能 WMS 仓储系统”，而是：
- 先解决“预计收货 + 实际入库”的极速录入问题；
- 在不增加一线人员负担的前提下，顺手积累结构化数据（品名、品类、单位、箱规）；
- 为后续 RMS / POS / BMS 等系统提供干净、可用的物料与流转数据基础；
- 兼顾未来的简单出库管理（门店领料 / 调拨 / 退货等）。

## 2. 设计目标与原则
### 2.1 核心目标
**预计收货记录**
- 记录即将到货的物料信息，至少包含：物料名称（自由文本）、品类（可选、尽量结构化）、基本单位（如：瓶、kg、包）。
- 条件允许时，增加整件与单件的数量关系（箱规），如：A 糖浆 10 瓶/箱，B 糖浆 15 瓶/箱。

**实际入库极速录入**
- 到货时，前台录入页面可以基于“预计收货单”快速确认或修改数量；
- 能在不知道/不完整的结构信息下完成录入（比如此时还不知道准确箱规也没关系）。

**出库记录（后续扩展）**
- 支持记录门店领料、店间调拨、退货、报废等出库动作；
- 暂不强制做复杂的波次拣货、货位管理。

**减轻后续工作量**
- 同一类物料（如各种品牌糖浆）可先用“自然语言 + 基本单位”录入；
- 后台再逐步把常用物料归一化为物料主数据 + 标准箱规，减少之后录入的重复工作。

### 2.2 设计原则
- **输入框优先、选择框辅助**：核心信息（物料名称、规格描述等）以文本输入为主，避免强制下拉导致录不进去；选择框只作为“建议/补全”，而不是阻碍录入的限制。
- **允许不完美，但可逐步变好**：允许先以“模糊/自然语言”录入，后续后台运营再做归一化与合并，避免“因为一开始做不完美，导致系统一直落地不了”。
- **对接现有命名与架构规范**：完全遵守 ABCABC 既有规范，目录结构遵循 `/dc_html/<project>/` 与 `/app/<project>/`，命名规范采用 snake_case、统一前缀、`_at` 时间字段、`_status` 状态字段等。
- **防止系统成为“黑箱”**：所有自动推断（如箱规匹配、物料归并）必须保留可追溯记录；重要动作（删除/合并/改规格）记录到 DTS 或 MRS 内部日志表。

## 3. 系统边界与关联系统
### 3.1 边界
MRS 专注以下内容：
- 入库前：预计收货单（预先录入/导入）。
- 入库时：实际入库单（基于预计单或直接创建）。
- 出库时：领料/调拨/退货记录。
- 支撑数据：物料主数据、单位、箱规规则。

不在本期范围：
- 货位管理 / 仓位优化。
- 路线规划 / 配送调度。
- 会计科目核算（可以通过导出或接口给 BMS / 财务）。

### 3.2 与其他系统的关系（预留接口）
- **RMS（Recipe Management System）**：物料主数据、单位、转换关系将与 RMS 共享或统一；MRS 负责“流转记录”，RMS 负责“配方与消耗”。
- **POS**：POS 侧的销售数据可以通过 RMS 计算理论消耗，再与 MRS 实际出库对比做盘点。
- **PRS**：PRS 专注“价格记录”；MRS 可为 PRS 提供“物料 + 单价 + 时间点”的数据基础。
- **DTS**：重要操作（比如物料合并、箱规变更、大量删除）可写入 DTS 做审计记录。

## 4. 功能范围
### 4.1 预计收货管理
目标：在货物未到前，先把“会来的东西”记录下名字、品类和单位，尽可能多地顺手补齐箱规。

主要功能：
- 新建预计收货单。
- 编辑/删除预计收货单。
- 为每个预计单添加多条物料行（自由文本 + 尽量结构化）。
- 为物料行补充或调整品类、品牌、基本单位、整件 vs 单件的数量关系（箱规）。
- 支持按供应商、日期范围、状态进行搜索与过滤。

### 4.2 实际入库管理（极速录入）
目标：在现场收货时，能快速完成录入，不被复杂规则卡住。

主要功能：
- 从“预计收货单”快速生成“实际入库单”。
- 也支持直接创建实际入库单（紧急采购 / 零散到货）。
- 在一页中完成物料行的数量录入（整件数、散件数均可）、单行添加/删除，以及临时添加新物料（仅输入名称 + 单位即可）。
- 支持输入框 + 简易模糊匹配（比如输入“糖浆”时，给出已有常用物料的候选）。

### 4.3 出库管理（基础版本）
主要能力（本期结构预留，可后续实现）：
- 领料出库：从仓库到门店/生产线。
- 店间调拨：门店之间互相调拨。
- 退货出库：退回供应商。
- 报废出库：损坏、过期报废。

每条出库记录至少包含：
- 出库类型：领料 / 调拨 / 退货 / 报废。
- 来源与去向（仓库/门店/供应商）。
- 物料 + 数量（支持整件 + 单件）。

### 4.4 物料与包装规格管理
后台功能：
- 物料主数据维护（名称、品类、品牌、默认单位）。
- 箱规管理（每个物料可有多个包装规格版本，支持启用/停用）。
- 物料归并：把多个“自然语言物料”合并到一个标准物料下（保留映射与历史）。

## 5. 业务流程说明
### 5.1 预计收货流程
后台或管理人员创建预计收货单：
- 填写：供应商、预计到货日期、仓库/门店、备注等。
- 添加物料行：物料名称（必填，自由文本）、品类（下拉或自动补全）、单位（文本 + 建议下拉）、预计数量（可分“整件数量 + 单件数量”其中任意一项），若已知则填写箱规（如：1 箱 = 10 瓶）。
- 保存后，预计收货单状态为：draft（草稿） → confirmed（确认）。

### 5.2 实际入库流程（极速录入页面）
到货时，操作员在前台录入页面选择：
- 基于某一“预计收货单”进行入库，或
- 直接新建“临时入库单”（无预计单）。

页面展示物料行：
- 列出预计单中的物料，如：物料名、单位、预计数量。
- 提供输入框填写实际整件数（可空）、实际散件数（可空）。
- 若箱规已知，则可以在界面上显示提示，例如“10 瓶/箱”。

用户可以：
- 增加新物料行（现场临时发现新增物料）。
- 修改物料名称（供应商临时换牌子）。
- 留下备注（例如：部分破损、少货等）。

保存后：
- 生成实际入库单记录；
- 更新物料库存（与内部库存逻辑对接，当前可先仅记账，不做复杂锁定）。

### 5.3 出库流程（框架）
- 选择出库类型：领料 / 调拨 / 退货 / 报废。
- 选择来源仓库 + 去向（门店/供应商/其他仓库）。
- 添加物料行 + 数量。
- 保存出库单，更新库存。

## 6. 界面与交互设计（模板）
### 6.1 前台录入页面模板（极速入库）
用途：一线操作员在收货现场使用，追求「快 + 容错」。

建议 URL：`https://dc.abcabc.net/mrs/index.php?action=inbound_quick`

核心布局（示意字段）：
- 顶部：仓库/门店选择、关联预计收货单（可选，下拉或搜索）、供应商（可从预计单带出，也可手填）。
- 表格区（可增删行）：

| # | 物料名称（文本输入） | 品类（建议选择） | 单位 | 箱规提示 | 整件数量 | 散件数量 | 备注 |
| --- | --- | --- | --- | --- | --- | --- | --- |

操作：\[新增一行]、\[保存草稿]、\[确认入库]。

交互原则：
- 文本框永远可以输入任何内容；
- 下拉选项不强制，输入不匹配也允许保存；
- 对于同名文本，后台给出“可能是已有物料”的提示，但不强制绑定。

### 6.2 后台管理页面模板
用途：管理人员查看与维护预计/实际收货单、物料主数据。

建议 URL（示例）：
- `index.php?action=inbound_list` 入库单列表
- `index.php?action=expected_list` 预计收货单列表
- `index.php?action=items` 物料主数据列表

基本布局：
- 列表页：分页表格 + 搜索条件（供应商、日期、仓库、状态）。
- 明细页：展示单头信息 + 单身明细表格。
- 提供操作：编辑、关闭、复制、新建出库单等。

## 7. 数据库设计与命名规范
### 7.1 通用命名规范（继承全局规则）
- 所有表名：`mrs_` 前缀 + snake_case，如：`mrs_inbound_order`。
- 字段命名：snake_case，小写，长度 ≤ 30。
- 主键字段：`<entity>_id`，例如 inbound_order_id。
- 外键字段：`<ref>_id`，例如 warehouse_id, item_id。
- 时间字段：created_at, updated_at, deleted_at（DATETIME(6) UTC）。
- 状态字段：名称统一为 `*_status`（如 order_status），整数型，值在注释中定义。
- 布尔字段：is_* / has_* / *_flag，TINYINT(1)。
- 国际化字段：本项目内以英文为主，若需多语言描述再扩展。

### 7.2 核心表清单（建议草案）
#### 7.2.1 预计收货单
- 表名：`mrs_expected_order`
  - expected_order_id (PK)
  - supplier_id（可与外部供应商表关联，也可以先用 0/NULL + 文本名）
  - warehouse_id / store_id
  - expected_date（DATE）
  - order_status（TINYINT：0 draft, 1 confirmed, 9 canceled）
  - remarks（TEXT）
  - created_at, updated_at, created_by_user_id
- 表名：`mrs_expected_order_item`
  - expected_order_item_id (PK)
  - expected_order_id (FK)
  - item_temp_name（物料名称，自然语言）
  - item_id（可选，关联到物料主数据，初期可为 NULL）
  - category_id（可选）
  - unit_code（如 bottle, kg, bag）
  - expected_cases（预计整件数，可空）
  - expected_units（预计散件数，可空）
  - case_unit_qty（箱规：1 箱 = ? 单位，可空）
  - remarks
  - created_at, updated_at

#### 7.2.2 实际入库单
- 表名：`mrs_inbound_order`
  - inbound_order_id (PK)
  - expected_order_id（FK，可空）
  - supplier_id
  - warehouse_id / store_id
  - inbound_date（DATE/TIMESTAMP）
  - order_status（0 draft, 1 confirmed, 9 cancelled）
  - remarks
  - created_at, updated_at, created_by_user_id
- 表名：`mrs_inbound_order_item`
  - inbound_order_item_id (PK)
  - inbound_order_id (FK)
  - expected_order_item_id（FK，可空）
  - item_temp_name
  - item_id（可空）
  - category_id（可空）
  - unit_code
  - case_unit_qty（当前使用的箱规，如 10 瓶/箱）
  - received_cases（实收整件数）
  - received_units（实收散件数）
  - total_base_units（折算后的总基础单位数，内部使用）
  - remarks
  - created_at, updated_at

#### 7.2.3 出库单（框架）
- 表名：`mrs_outbound_order`
  - outbound_order_id (PK)
  - outbound_type（TINYINT：1 领料，2 调拨，3 退货，4 报废）
  - from_warehouse_id / from_store_id
  - to_warehouse_id / to_store_id / supplier_id
  - outbound_date
  - order_status
  - remarks
  - created_at, updated_at
- 表名：`mrs_outbound_order_item`
  - outbound_order_item_id
  - outbound_order_id
  - item_id / item_temp_name
  - category_id
  - unit_code
  - case_unit_qty
  - outbound_cases
  - outbound_units
  - total_base_units
  - remarks
  - created_at, updated_at

#### 7.2.4 物料与箱规
- 表名：`mrs_item`
  - item_id (PK)
  - item_name（标准化物料名）
  - category_id
  - brand_name（可选）
  - default_unit_code
  - is_active
  - created_at, updated_at
- 表名：`mrs_item_pack_rule`
  - pack_rule_id (PK)
  - item_id
  - package_name（如“纸箱装”、“塑封箱”）
  - case_unit_qty（1 箱 = ? 单位）
  - is_default
  - effective_from, effective_to（预留）
  - created_at, updated_at
- 表名：`mrs_unit`
  - unit_code (PK)
  - unit_name
  - base_unit_code（如有换算体系，可用）
  - ratio_to_base
  - created_at, updated_at

（以上仅为核心建议，可在具体实施时再细化字段类型与索引。）

## 8. 部署结构与文件命名
### 8.1 域名与访问路径
统一从 DC 平台访问：基本入口 `https://dc.abcabc.net/mrs/index.php`。

### 8.2 目录结构
按 ABCABC 统一规范：

```
/dc_html/
  └── mrs/                      # MRS 前端入口目录（公网可访问）
      └── index.php             # 前端入口/路由
/app/
  └── mrs/                      # MRS 业务逻辑目录（非公网）
      ├── actions/              # 控制器/动作
      │   ├── inbound_quick.php
      │   ├── inbound_list.php
      │   ├── expected_edit.php
      │   ├── outbound_list.php
      │   └── items.php
      ├── lib/                  # 业务库函数（物料归并、箱规计算等）
      │   ├── mrs_lib.php
      │   └── mrs_item_lib.php
      ├── templates/            # 页面模板（HTML/PHP）
      │   ├── layout_front.php  # 前台录入页面通用布局
      │   ├── layout_admin.php  # 后台管理页面通用布局
      │   ├── inbound_quick.tpl.php
      │   ├── inbound_list.tpl.php
      │   └── expected_edit.tpl.php
      ├── config_mrs/
      │   └── env_mrs.php       # 环境配置（DB 连接、路径常量等）
      └── logs/                 # 项目专用日志（可选路径）
          └── mrs_debug.log
/app/logs/
  └── mrs/                      # 若沿用全局 logs 目录，则在此分子目录存放
      └── debug.log
```

说明：业务逻辑统一放在 `/app/mrs/` 下，前端入口只做路由分发；配置文件固定为 `/app/mrs/config_mrs/env_mrs.php`；日志要么放在 `/app/mrs/logs/`，要么统一放在 `/app/logs/mrs/`，但必须项目独立子目录。

### 8.3 关键文件命名规范
- 动作文件：`<模块>_<动作>.php`（如：inbound_quick.php, expected_edit.php）。
- 模板文件：`<模块>_<动作>.tpl.php`（如：inbound_quick.tpl.php）。
- 公共布局模板：layout_front.php（前台录入）、layout_admin.php（后台列表/详情）。
- 库文件：`mrs_*.php`（如：mrs_lib.php, mrs_item_lib.php）。

### 8.4 配置与日志
- 配置文件：`/app/mrs/config_mrs/env_mrs.php`。
- 定义内容示例（约定）：DB 连接信息（主库/只读库）、项目路径常量（MRS_APP_PATH, MRS_TPL_PATH 等）、日志路径（MRS_LOG_PATH）。
- 日志文件：建议 `/app/logs/mrs/debug.log`，仅记录错误、关键业务动作、必要的调试信息（注意隐私与容量控制）。

## 9. 开发阶段与任务拆分（给工程师/AI 的执行指引）
**阶段 1：基础骨架搭建**
- 按上述目录结构创建：`/dc_html/mrs/index.php`（入口 + 简单路由）、`/app/mrs/actions/`、`/app/mrs/lib/`、`/app/mrs/templates/`、`/app/mrs/config_mrs/`。
- 实现两个核心页面模板（只要能跑通）：前台 inbound_quick.php + inbound_quick.tpl.php；后台 inbound_list.php + inbound_list.tpl.php（简单表格 + 假数据）。
- 建立 env_mrs.php，与现有 ABCABC 项目风格一致。

**阶段 2：数据库建表**
- 在 DB 中按本说明中的命名规范与字段草案创建表：`mrs_expected_order` + `mrs_expected_order_item`、`mrs_inbound_order` + `mrs_inbound_order_item`、（预留）`mrs_outbound_order` + `mrs_outbound_order_item`、`mrs_item` + `mrs_item_pack_rule` + `mrs_unit`。
- 建立必要索引（主键、外键、按日期/仓库/供应商的查询索引）。

**阶段 3：预计收货 + 实际入库功能打通**
- 实现预计收货单的增删改查（后台页面）。
- 在前台 inbound_quick 中支持：基于预计单加载物料行；录入实际数量并保存到 `mrs_inbound_order` 系列表。
- 完成基本的错误提示与日志记录。
