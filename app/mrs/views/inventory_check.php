<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MRS 移动盘点</title>
    <link rel="stylesheet" href="/mrs/front/css/mobile_check.css">
</head>
<body>
<div class="header">
    <h1 class="title">库存盘点</h1>
</div>
<div class="search-bar">
    <input id="search-input" class="search-input" type="search" placeholder="搜箱号/内容/单号/位置..." autocomplete="off">
    <label class="filter-row">
        <input type="checkbox" id="only-unchecked"> 仅显示未盘点
    </label>
</div>
<div id="card-container" class="card-container"></div>
<div id="toast" class="toast"></div>
<script src="/mrs/front/js/inventory_check.js"></script>
</body>
</html>
