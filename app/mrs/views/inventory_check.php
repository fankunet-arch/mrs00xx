<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MRS 移动清点</title>
    <link rel="stylesheet" href="/mrs/front/css/mobile_check.css">
</head>
<body>
    <div class="page">
        <header class="page-header">
            <div>
                <p class="eyebrow">移动端清点工具</p>
                <h1>MRS 清点 V2.2</h1>
                <p class="subtitle">搜索 → 确认/修改 → 丢失 → 补录</p>
            </div>
            <div class="user-chip">已登录</div>
        </header>

        <section class="card search-card">
            <form id="search-form" class="search-form">
                <label class="field">
                    <span class="field-label">箱号 / 单号 / 位置 / 备注</span>
                    <input type="text" id="keyword" name="keyword" placeholder="输入关键字后搜索" autocomplete="off">
                </label>
                <div class="search-options">
                    <label class="checkbox">
                        <input type="checkbox" id="only-unchecked" name="only_unchecked" value="1" checked>
                        <span>仅显示未盘点</span>
                    </label>
                    <button type="submit" class="primary-btn">搜索</button>
                </div>
            </form>
            <div class="helper" id="search-helper">只查询在库包裹，默认只显示今日未盘点记录。</div>
        </section>

        <section id="result-section">
            <div id="result-list" class="card-list"></div>
        </section>

        <section id="create-section" class="card hidden">
            <h2>登记新货</h2>
            <p class="helper">系统没有的实物，可在此补录。批次/单号将由系统生成，避免唯一键冲突。</p>
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">箱号 *</span>
                    <input type="text" id="create-box-number" placeholder="必填，例如 A-01">
                </label>
                <label class="field">
                    <span class="field-label">数量 (选填)</span>
                    <input type="number" id="create-qty" inputmode="decimal" placeholder="输入实物数量">
                </label>
                <label class="field full">
                    <span class="field-label">内容备注 (选填)</span>
                    <textarea id="create-content-note" rows="2" placeholder="产品/包装描述、破损情况等"></textarea>
                </label>
                <label class="field full">
                    <span class="field-label">仓库位置 (选填)</span>
                    <input type="text" id="create-location" placeholder="如：货架B-2">
                </label>
            </div>
            <div class="actions">
                <button id="create-btn" class="primary-btn full">登记新货</button>
            </div>
            <div class="helper" id="create-feedback"></div>
        </section>
    </div>

    <script src="/mrs/front/js/inventory_check.js"></script>
</body>
</html>
