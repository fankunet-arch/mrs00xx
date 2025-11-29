<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MRS 现场收货</title>
    <link rel="stylesheet" href="/mrs/css/receipt.css">
</head>
<body>
    <div class="container">
        <h1>现场收货</h1>
        <div class="card">
            <div class="card-header">选择批次</div>
            <div class="card-body batch-buttons" id="batch-buttons"></div>
        </div>
        <div class="card"><div id="batch-info-grid"></div></div>
        <div class="card sticky-input">
            <div class="input-area">
                <div class="material-wrapper">
                    <input type="text" id="material-input" placeholder="输入物料名称或编码..." autocomplete="off">
                    <div class="candidate-list" id="candidate-list" style="display: none;"></div>
                </div>
                <input type="number" id="qty-input" placeholder="数量" inputmode="decimal">
                <button class="primary-btn" id="btn-add">记录本次收货</button>
            </div>
            <div class="unit-row" id="unit-row"></div>
        </div>
        <div class="card">
            <div class="card-header">本批次记录</div>
            <div class="records" id="records"></div>
        </div>
        <div class="card">
            <div class="card-header">汇总</div>
            <div class="summary" id="summary"></div>
        </div>
    </div>
    <script src="/mrs/js/receipt.js"></script>
</body>
</html>
