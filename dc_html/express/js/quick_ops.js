/**
 * Express Package Management System - Quick Operations Frontend
 * 文件路径: dc_html/express/js/quick_ops.js
 * 修复说明: 
 * 1. 历史记录列表现在跟随“操作类型”变化，仅显示当前类型的记录。
 * 2. 增加了单号去重逻辑，同一单号在同一操作类型下只显示最新一条。
 * 3. 增加了历史记录存储容量（10 -> 100），以支持筛选。
 */

// 全局状态
const state = {
    currentBatchId: null,
    currentOperation: null,
    searchTimeout: null,
    operationHistory: [],
    searchResults: new Map(),
    lastCountNote: '',
    productItemCounter: 0,  // 产品项计数器
    lastProductName: '',     // 上一个录入的产品名称
    productNameSearchTimeouts: new Map(),  // 产品名称搜索延时Map（按itemId）
    currentExpiryModalItemId: null  // 当前打开有效期模态框的产品项ID
};

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // 更新时间显示
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);

    // 绑定事件
    bindEvents();

    // 初始显示空的历史区域
    displayHistory();

    // [FIX] 监听页面可见性变化，解决手机熄屏后状态丢失的问题
    setupVisibilityListener();
}

function bindEvents() {
    // 批次选择
    const batchSelect = document.getElementById('batch-select');
    if (batchSelect) {
        batchSelect.addEventListener('change', onBatchChange);
    }
    
    const refreshBtn = document.getElementById('refresh-batches');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshBatches);
    }

    // 操作类型选择
    document.querySelectorAll('.btn-operation').forEach(btn => {
        btn.addEventListener('click', function() {
            selectOperation(this.dataset.operation);
        });
    });

    // 快递单号输入
    const trackingInput = document.getElementById('tracking-input');
    if (trackingInput) {
        trackingInput.addEventListener('input', onTrackingInput);
        trackingInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleDirectInput();
            }
        });
    }

    // 按钮事件
    const btnClear = document.getElementById('btn-clear-input');
    if (btnClear) btnClear.addEventListener('click', clearInput);
    
    const btnSubmit = document.getElementById('btn-submit');
    if (btnSubmit) btnSubmit.addEventListener('click', submitOperation);

    const btnReset = document.getElementById('btn-reset');
    if (btnReset) btnReset.addEventListener('click', resetForm);

    const btnChangeOp = document.getElementById('btn-change-operation');
    if (btnChangeOp) btnChangeOp.addEventListener('click', changeOperation);

    const lastCountButton = document.getElementById('btn-apply-last-count');
    if (lastCountButton) {
        lastCountButton.addEventListener('click', function() {
            const content = this.dataset.content || '';

            if (!content) return;

            // 填充到第一个空的产品项，如果都有内容则填充到最后一个
            const container = document.getElementById('products-container');
            if (!container) return;

            const productItems = container.querySelectorAll('.product-item');
            if (productItems.length === 0) return;

            // 查找第一个产品名称为空的产品项
            let targetItem = null;
            for (let item of productItems) {
                const itemId = item.dataset.itemId;
                const nameField = item.querySelector(`.product-name[data-item-id="${itemId}"]`);
                if (nameField && !nameField.value.trim()) {
                    targetItem = item;
                    break;
                }
            }

            // 如果所有产品项都有内容，则使用最后一个产品项
            if (!targetItem) {
                targetItem = productItems[productItems.length - 1];
            }

            const targetItemId = targetItem.dataset.itemId;
            const nameField = targetItem.querySelector(`.product-name[data-item-id="${targetItemId}"]`);

            if (nameField) {
                nameField.value = content;
                nameField.focus();
                const length = content.length;
                nameField.setSelectionRange(length, length);
            }
        });
    }

    // 清空保质期按钮
    const btnClearExpiry = document.getElementById('btn-clear-expiry');
    if (btnClearExpiry) {
        btnClearExpiry.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // 防止触发日期选择器
            const expiryField = document.getElementById('expiry-date');
            if (expiryField) {
                expiryField.value = '';
                // 给用户反馈
                const wrapper = expiryField.closest('.expiry-date-wrapper');
                if (wrapper) {
                    wrapper.classList.add('cleared');
                    setTimeout(() => wrapper.classList.remove('cleared'), 300);
                }
            }
        });
    }

    // 保质期输入框 - 确保点击整个区域都能弹出选择器
    const expiryDateInput = document.getElementById('expiry-date');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('click', function() {
            this.showPicker && this.showPicker();
        });
    }

    // 清空数量按钮
    const btnClearQuantity = document.getElementById('btn-clear-quantity');
    if (btnClearQuantity) {
        btnClearQuantity.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const quantityField = document.getElementById('quantity');
            if (quantityField) {
                quantityField.value = '';
            }
        });
    }

    // 添加产品按钮
    const btnAddProduct = document.getElementById('btn-add-product');
    if (btnAddProduct) {
        btnAddProduct.addEventListener('click', addProductItem);
    }
}

// 时间更新
function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    const timeEl = document.getElementById('current-time');
    if (timeEl) timeEl.textContent = timeStr;
}

// 批次选择事件
async function onBatchChange(e) {
    const batchId = e.target.value;

    if (!batchId) {
        state.currentBatchId = null;
        const batchStats = document.getElementById('batch-stats');
        const operationSection = document.getElementById('operation-section');
        const inputSection = document.getElementById('input-section');
        if (batchStats) batchStats.style.display = 'none';
        if (operationSection) operationSection.style.display = 'none';
        if (inputSection) inputSection.style.display = 'none';
        return;
    }

    state.currentBatchId = batchId;

    // 更新统计信息
    const selectedOption = e.target.options[e.target.selectedIndex];
    updateBatchStats({
        total_count: parseInt(selectedOption.dataset.total) || 0,
        verified_count: parseInt(selectedOption.dataset.verified) || 0,
        counted_count: parseInt(selectedOption.dataset.counted) || 0,
        adjusted_count: parseInt(selectedOption.dataset.adjusted) || 0
    });

    // 显示操作选择区域
    const batchStats = document.getElementById('batch-stats');
    const operationSection = document.getElementById('operation-section');
    const inputSection = document.getElementById('input-section');
    if (batchStats) batchStats.style.display = 'flex';
    if (operationSection) operationSection.style.display = 'block';
    if (inputSection) inputSection.style.display = 'none';

    await refreshHistoryFromServer();
}

// 更新批次统计
function updateBatchStats(stats) {
    const statTotal = document.getElementById('stat-total');
    const statVerified = document.getElementById('stat-verified');
    const statCounted = document.getElementById('stat-counted');
    const statAdjusted = document.getElementById('stat-adjusted');

    if (statTotal) statTotal.textContent = stats.total_count;
    if (statVerified) statVerified.textContent = stats.verified_count;
    if (statCounted) statCounted.textContent = stats.counted_count;
    if (statAdjusted) statAdjusted.textContent = stats.adjusted_count;

    // 更新进度条
    const progress = stats.total_count > 0
        ? Math.round((stats.verified_count / stats.total_count) * 100)
        : 0;

    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    if (progressFill) progressFill.style.width = progress + '%';
    if (progressText) progressText.textContent = progress + '%';
}

// 刷新批次列表
async function refreshBatches() {
    try {
        const response = await fetch('/express/index.php?action=get_batches_api');
        const data = await response.json();

        if (data.success) {
            const batchSelect = document.getElementById('batch-select');
            const currentBatchId = batchSelect.value;

            batchSelect.innerHTML = '<option value="">-- 请选择批次 --</option>';

            data.data.forEach(batch => {
                const option = document.createElement('option');
                option.value = batch.batch_id;
                option.textContent = `${batch.batch_name} (${batch.total_count}个包裹)`;
                option.dataset.total = batch.total_count;
                option.dataset.verified = batch.verified_count;
                option.dataset.counted = batch.counted_count;
                option.dataset.adjusted = batch.adjusted_count;
                batchSelect.appendChild(option);
            });

            // 保持当前选择
            if (currentBatchId) {
                batchSelect.value = currentBatchId;
            }

            showMessage('批次列表已刷新', 'success');
        }
    } catch (error) {
        showMessage('刷新失败: ' + error.message, 'error');
    }
}

// 选择操作类型
function selectOperation(operation) {
    state.currentOperation = operation;

    if (operation !== 'count') {
        hideLastCountSuggestion();
    }

    // 更新操作名称显示
    const operationNames = {
        'verify': '核实',
        'count': '清点',
        'adjust': '调整'
    };

    const operationName = document.getElementById('operation-name');
    if (operationName) operationName.textContent = operationNames[operation];

    // 显示/隐藏相应的备注输入框
    const productsGroup = document.getElementById('products-group');
    const adjustmentNoteGroup = document.getElementById('adjustment-note-group');
    if (productsGroup) productsGroup.style.display = operation === 'count' ? 'block' : 'none';
    if (adjustmentNoteGroup) adjustmentNoteGroup.style.display = operation === 'adjust' ? 'block' : 'none';

    // 如果是清点操作,初始化至少一个产品项
    if (operation === 'count') {
        initializeProductItems();
        // [新增] 切换到清点模式时，专门获取一次最新的清点内容
        fetchLastCountRecord();
    }

    // 显示输入区域
    const inputSection = document.getElementById('input-section');
    if (inputSection) inputSection.style.display = 'block';

    // 聚焦到输入框
    const trackingInput = document.getElementById('tracking-input');
    if (trackingInput) trackingInput.focus();

    // [FIX] 切换操作类型时，立即刷新并筛选历史记录
    displayHistory();
}

// 快递单号输入事件（模糊搜索）
function onTrackingInput(e) {
    const keyword = e.target.value.trim();

    // 清除之前的延时
    if (state.searchTimeout) {
        clearTimeout(state.searchTimeout);
    }

    if (!keyword) {
        hideSearchResults();
        return;
    }

    // 延时搜索（防抖）
    state.searchTimeout = setTimeout(() => {
        performSearch(keyword);
    }, 300);
}

// 执行搜索
async function performSearch(keyword) {
    if (!state.currentBatchId) {
        return;
    }

    try {
        const response = await fetch(
            `/express/index.php?action=search_tracking_api&batch_id=${state.currentBatchId}&keyword=${encodeURIComponent(keyword)}`
        );
        const data = await response.json();

        if (data.success) {
            state.searchResults = new Map();
            data.data.forEach(pkg => {
                state.searchResults.set(pkg.tracking_number, pkg);
            });

            const currentInput = document.getElementById('tracking-input');
            if (currentInput) {
                const currentValue = currentInput.value.trim();
                if (currentValue && state.searchResults.has(currentValue)) {
                    updateNotesPrefill(currentValue);
                }
            }

            displaySearchResults(data.data, keyword);
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

// 显示搜索结果
function displaySearchResults(results, keyword) {
    const resultsDiv = document.getElementById('search-results');

    if (results.length === 0) {
        resultsDiv.innerHTML = `
            <div class="search-result-item new-item" data-tracking="${keyword}">
                <div class="tracking-number">${keyword}</div>
                <div class="tracking-status">新单号（点击创建）</div>
            </div>
        `;
    } else {
        resultsDiv.innerHTML = results.map(pkg => `
            <div class="search-result-item" data-tracking="${pkg.tracking_number}">
                <div class="tracking-number">${pkg.tracking_number}</div>
                <div class="tracking-status status-${pkg.package_status}">${getStatusText(pkg.package_status)}</div>
            </div>
        `).join('');
    }

    // 绑定点击事件
    resultsDiv.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', function() {
            selectTrackingNumber(this.dataset.tracking);
        });
    });

    resultsDiv.style.display = 'block';
}

// 隐藏搜索结果
function hideSearchResults() {
    document.getElementById('search-results').style.display = 'none';
}

// 选择快递单号
function selectTrackingNumber(trackingNumber) {
    document.getElementById('tracking-input').value = trackingNumber;
    hideSearchResults();

    // 根据已存在的包裹信息预填备注
    updateNotesPrefill(trackingNumber);

    // 如果是清点操作，聚焦到内容备注
    if (state.currentOperation === 'count') {
        document.getElementById('content-note').focus();
    } else if (state.currentOperation === 'adjust') {
        document.getElementById('adjustment-note').focus();
    }
}

// 直接按Enter键处理
function handleDirectInput() {
    const trackingNumber = document.getElementById('tracking-input').value.trim();

    if (!trackingNumber) {
        return;
    }

    updateNotesPrefill(trackingNumber);
    selectTrackingNumber(trackingNumber);
}

// 清空输入
function clearInput() {
    document.getElementById('tracking-input').value = '';
    document.getElementById('adjustment-note').value = '';

    // 清空产品项
    if (state.currentOperation === 'count') {
        clearProductItems();
    }

    hideSearchResults();
    hideLastCountSuggestion();
    document.getElementById('tracking-input').focus();
}

// 提交操作
async function submitOperation() {
    const trackingNumber = document.getElementById('tracking-input').value.trim();

    if (!trackingNumber) {
        showMessage('请输入快递单号', 'error');
        return;
    }

    if (!state.currentBatchId || !state.currentOperation) {
        showMessage('请选择批次和操作类型', 'error');
        return;
    }

    const payload = {
        batch_id: state.currentBatchId,
        tracking_number: trackingNumber,
        operation_type: state.currentOperation,
        operator: 'frontend_user'
    };

    if (state.currentOperation === 'count') {
        // 收集多产品数据
        const products = collectProductItems();
        if (products.length === 0) {
            showMessage('请至少填写一个产品信息', 'error');
            return;
        }
        payload.products = products;
    }

    if (state.currentOperation === 'adjust') {
        payload.adjustment_note = document.getElementById('adjustment-note').value.trim();
    }

    try {
        const response = await fetch('/express/index.php?action=save_record_api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            showMessage(data.message, 'success');

            if (state.currentOperation === 'count') {
                // 正确从多产品数据中提取名称更新本地缓存
                if (payload.products && payload.products.length > 0) {
                    const productNames = payload.products
                        .map(p => p.product_name)
                        .filter(name => name && name.trim())
                        .map(name => name.trim());

                    state.lastCountNote = productNames.join(', ');
                    // 同时更新产品名称快捷标签
                    state.lastProductName = productNames[0] || '';
                } else {
                    state.lastCountNote = (payload.content_note || '').trim();
                    state.lastProductName = state.lastCountNote;
                }
            }

            // 更新批次统计
            if (data.data.batch) {
                updateBatchStats(data.data.batch);
            }

            if (data.data.package) {
                if (!(state.searchResults instanceof Map)) {
                    state.searchResults = new Map();
                }
                state.searchResults.set(trackingNumber, data.data.package);
            }

            await refreshHistoryFromServer();

            // 清空输入，准备下一个
            clearInput();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('操作失败: ' + error.message, 'error');
    }
}

// 重置表单
function resetForm() {
    clearInput();
    // [FIX] 重置时不清除当前选择的操作类型，符合连续操作习惯
    // state.currentOperation = null; 
    // document.getElementById('input-section').style.display = 'none';
}

// 切换操作类型
function changeOperation() {
    state.currentOperation = null;
    document.getElementById('input-section').style.display = 'none';
    // 清空历史显示（因为没有选择类型）
    displayHistory();
}

// 显示消息
function showMessage(message, type) {
    const messageBox = document.getElementById('message-box');
    messageBox.textContent = message;
    messageBox.className = `message-box ${type}`;
    messageBox.style.display = 'block';

    setTimeout(() => {
        messageBox.style.display = 'none';
    }, 3000);
}

// 获取状态文本
function getStatusText(status) {
    const statusMap = {
        'pending': '待处理',
        'verified': '已核实',
        'counted': '已清点',
        'adjusted': '已调整'
    };
    return statusMap[status] || status;
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderTrackingNumber(trackingNumber) {
    const tracking = escapeHtml(trackingNumber || '');

    if (tracking.length <= 4) {
        return `<span class="tracking-suffix">${tracking}</span>`;
    }

    const prefix = tracking.slice(0, -4);
    const suffix = tracking.slice(-4);

    return `<span class="tracking-prefix">${prefix}</span><span class="tracking-suffix">${suffix}</span>`;
}

// [FIX] 显示历史记录（重构：增加筛选和去重逻辑）
function displayHistory() {
    const historyDiv = document.getElementById('operation-history');
    
    // 1. 获取所有记录
    let records = state.operationHistory || [];

    // 2. 如果已选择操作类型，则只显示该类型的记录
    if (state.currentOperation) {
        records = records.filter(r => r.operation === state.currentOperation);
    }

    // 3. 按快递单号去重，只保留最新的一条
    // (由于 records 是按时间倒序排列的，第一次出现的单号即为最新的)
    const uniqueMap = new Map();
    const uniqueRecords = [];
    
    for (const record of records) {
        if (!uniqueMap.has(record.tracking_number)) {
            uniqueMap.set(record.tracking_number, true);
            uniqueRecords.push(record);
        }
    }
    
    // 4. 只显示前 10 条
    const displayRecords = uniqueRecords.slice(0, 10);

    if (displayRecords.length === 0) {
        historyDiv.innerHTML = '<p class="empty-text">暂无操作记录</p>';
        return;
    }

    historyDiv.innerHTML = displayRecords.map(record => `
        <div class="history-item">
            <div class="history-meta">
                <span class="history-time">${record.time}</span>
                <span class="history-status status-${record.status}">${getStatusText(record.status)}</span>
            </div>
            <div class="history-tracking">${renderTrackingNumber(record.tracking_number)}</div>
        </div>
    `).join('');
}

// 根据当前操作类型预填备注
function updateNotesPrefill(trackingNumber) {
    if (!(state.searchResults instanceof Map)) {
        hideLastCountSuggestion();
        return;
    }

    const pkg = state.searchResults.get(trackingNumber);
    if (state.currentOperation === 'count') {
        // 如果包裹已有产品数据,则预填
        if (pkg && pkg.items && Array.isArray(pkg.items) && pkg.items.length > 0) {
            hideLastCountSuggestion();
            fillProductItems(pkg.items);
            return;
        }

        // 清空产品项
        clearProductItems();
        // 显示上次清点建议(如果有)
        showLastCountSuggestion(state.lastCountNote);
    } else {
        hideLastCountSuggestion();
    }

    if (!pkg) {
        return;
    }

    if (state.currentOperation === 'adjust') {
        const adjustField = document.getElementById('adjustment-note');
        if (adjustField) {
            adjustField.value = pkg.adjustment_note || '';
        }
    }
}

// 从服务端刷新最新历史，保证不同设备展示一致
async function refreshHistoryFromServer() {
    if (!state.currentBatchId) {
        state.operationHistory = [];
        displayHistory();
        return;
    }

    // 1. 获取用于显示的历史列表 (混合类型, 去重)
    const params = new URLSearchParams({
        batch_id: state.currentBatchId,
        limit: 100
    });

    try {
        const response = await fetch(`/express/index.php?action=get_recent_operations_api&${params.toString()}`);
        const data = await response.json();

        if (data.success && Array.isArray(data.data)) {
            state.operationHistory = data.data.map(item => ({
                tracking_number: item.tracking_number,
                operation: item.operation_type,
                status: item.new_status || item.package_status || item.old_status,
                time: formatOperationTime(item.operation_time),
                notes: item.notes || ''
            }));

            syncLastCountNote(state.operationHistory);
            syncLastProductName(state.operationHistory);
            displayHistory();
        } else {
            showMessage(data.message || '获取历史失败', 'error');
        }
    } catch (error) {
        showMessage('获取历史失败: ' + error.message, 'error');
    }

    // 2. [新增] 专门获取最后一条"清点"记录，用于快捷标签
    // 这样做可以避免"调整"或"核实"操作导致清点记录在去重时被隐藏
    if (state.currentOperation === 'count') {
        await fetchLastCountRecord();
    }
}

// [新增] 独立获取最后清点记录的函数
async function fetchLastCountRecord() {
    if (!state.currentBatchId) return;

    const params = new URLSearchParams({
        batch_id: state.currentBatchId,
        operation_type: 'count', // 指定只查清点记录
        limit: 1 // 只要最新的一条
    });

    try {
        const response = await fetch(`/express/index.php?action=get_recent_operations_api&${params.toString()}`);
        const data = await response.json();

        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            const lastRecord = data.data[0];
            if (lastRecord.notes && lastRecord.notes.trim()) {
                state.lastCountNote = lastRecord.notes.trim();

                // 提取产品名称
                const notes = lastRecord.notes.trim();
                const match = notes.match(/^([^,]+)/);
                if (match) {
                    state.lastProductName = match[1].trim();
                    console.log('[快捷标签] 已同步最新清点内容:', state.lastCountNote);
                    console.log('[产品名称快捷标签] 已同步最新产品名称:', state.lastProductName);

                    // 更新所有产品项的快捷标签显示
                    updateAllProductNameSuggestions();
                }
            }
        }
    } catch (error) {
        console.error('获取最后清点记录失败:', error);
    }
}

function formatOperationTime(value) {
    if (!value) return '';

    const parsed = new Date(value.replace(/-/g, '/'));
    if (!Number.isNaN(parsed.getTime())) {
        return parsed.toLocaleString('zh-CN', {
            hour12: false,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    return value;
}

// 显示/隐藏上次清点内容提示
function showLastCountSuggestion(contentNote) {
    const container = document.getElementById('last-count-suggestion');
    const button = document.getElementById('btn-apply-last-count');

    if (!container || !button) {
        console.log('[快捷标签] 元素不存在');
        return;
    }

    const text = (contentNote || '').trim();
    if (state.currentOperation !== 'count' || !text) {
        console.log('[快捷标签] 不显示 - 操作类型:', state.currentOperation, '内容:', text);
        hideLastCountSuggestion();
        return;
    }

    button.textContent = text;
    button.dataset.content = text;
    container.style.display = 'flex';
    console.log('[快捷标签] 显示成功:', text, 'display:', container.style.display);
}

function hideLastCountSuggestion() {
    const container = document.getElementById('last-count-suggestion');
    const button = document.getElementById('btn-apply-last-count');

    if (!container || !button) {
        return;
    }

    container.style.display = 'none';
    button.textContent = '';
    button.dataset.content = '';
}

// 从历史记录提取最近一次清点的备注，用于新单号提示（跨设备持久）
function syncLastCountNote(records) {
    state.lastCountNote = '';

    if (!Array.isArray(records)) {
        return;
    }

    // 修复：确保获取时间最新的清点记录（而不是第一个）
    // 历史记录已按时间倒序排列，find会找到最新的
    const latestCountRecord = records.find(rec => rec.operation === 'count' && rec.notes && rec.notes.trim());
    if (latestCountRecord) {
        state.lastCountNote = latestCountRecord.notes.trim();
        console.log('[快捷标签] 同步最新清点记录:', state.lastCountNote);
    } else {
        console.log('[快捷标签] 未找到清点记录');
    }
}

// [FIX] 监听页面可见性变化，解决手机熄屏后批次选择状态丢失的问题
function setupVisibilityListener() {
    document.addEventListener('visibilitychange', function() {
        // 当页面从隐藏状态恢复到可见状态时
        if (!document.hidden) {
            const batchSelect = document.getElementById('batch-select');

            // 检查批次选择器是否有值，但 JavaScript 状态为空
            // 这种情况说明页面被冻结后恢复，DOM 状态和 JS 状态不一致
            if (batchSelect && batchSelect.value && !state.currentBatchId) {
                console.log('[页面恢复] 检测到批次选择状态不一致，正在同步...');

                // 手动触发批次选择事件，恢复页面状态
                const event = new Event('change', { bubbles: true });
                batchSelect.dispatchEvent(event);
            }
        }
    });
}

// ============= 多产品支持功能 =============

// 初始化产品项列表(至少一项)
function initializeProductItems() {
    const container = document.getElementById('products-container');
    if (!container) return;

    // 清空容器
    container.innerHTML = '';
    state.productItemCounter = 0;

    // 添加第一个产品项
    addProductItem();
}

// 添加一个产品项
function addProductItem() {
    const container = document.getElementById('products-container');
    if (!container) return;

    const itemId = ++state.productItemCounter;

    const itemHtml = `
        <div class="product-item" data-item-id="${itemId}">
            <div class="product-item-header">
                <span class="product-item-number">产品 ${itemId}</span>
                <button type="button" class="btn-remove-product" data-item-id="${itemId}" title="删除此产品">×</button>
            </div>
            <div class="product-item-body">
                <div class="form-group" style="position: relative;">
                    <label>产品名称/内容:</label>
                    <input type="text" class="form-control product-name"
                           placeholder="例如：番茄酱"
                           data-item-id="${itemId}"
                           autocomplete="off">
                    <div class="product-name-search-results" data-item-id="${itemId}" style="display: none;"></div>
                    <div class="product-name-suggestion" data-item-id="${itemId}" style="display: none;">
                        <span class="suggestion-label">上次:</span>
                        <button type="button" class="btn-apply-product-name suggestion-chip"
                                data-item-id="${itemId}"
                                title="点击填入上次的产品名称"></button>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数量:</label>
                        <input type="number" class="form-control product-quantity"
                               placeholder="数量" min="1" step="1"
                               data-item-id="${itemId}">
                    </div>
                    <div class="form-group">
                        <label>保质期:</label>
                        <input type="date" class="form-control product-expiry"
                               data-item-id="${itemId}"
                               readonly
                               placeholder="点击打开">
                    </div>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', itemHtml);

    // 绑定删除按钮事件
    const removeBtn = container.querySelector(`[data-item-id="${itemId}"].btn-remove-product`);
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            removeProductItem(itemId);
        });
    }

    // 绑定保质期输入框点击事件 - 打开模态框
    const expiryInput = container.querySelector(`.product-expiry[data-item-id="${itemId}"]`);
    if (expiryInput) {
        expiryInput.addEventListener('click', function() {
            openExpiryModal(itemId);
        });
    }

    // 绑定产品名称输入事件
    const nameInput = container.querySelector(`.product-name[data-item-id="${itemId}"]`);
    if (nameInput) {
        // 输入时搜索产品名称
        nameInput.addEventListener('input', function() {
            onProductNameInput(itemId, this.value.trim());
        });

        // 失去焦点时隐藏搜索结果
        nameInput.addEventListener('blur', function() {
            // 延迟隐藏，以便点击搜索结果
            setTimeout(() => {
                hideProductNameSearchResults(itemId);
            }, 200);
        });

        // 获得焦点时显示产品名称快捷标签
        nameInput.addEventListener('focus', function() {
            if (!this.value.trim()) {
                showProductNameSuggestion(itemId);
            }
        });
    }

    // 绑定产品名称快捷标签按钮点击事件
    const productNameBtn = container.querySelector(`.btn-apply-product-name[data-item-id="${itemId}"]`);
    if (productNameBtn) {
        productNameBtn.addEventListener('click', function() {
            const productName = this.dataset.productName || '';
            if (productName && nameInput) {
                nameInput.value = productName;
                nameInput.focus();
                hideProductNameSuggestion(itemId);
            }
        });
    }

    // 显示产品名称快捷标签（如果有）
    showProductNameSuggestion(itemId);
}

// 删除一个产品项
function removeProductItem(itemId) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const item = container.querySelector(`.product-item[data-item-id="${itemId}"]`);
    if (!item) return;

    // 如果只剩一个产品项,不允许删除
    const remainingItems = container.querySelectorAll('.product-item');
    if (remainingItems.length <= 1) {
        showMessage('至少需要保留一个产品项', 'warning');
        return;
    }

    item.remove();

    // 重新编号
    renumberProductItems();
}

// 重新编号产品项
function renumberProductItems() {
    const container = document.getElementById('products-container');
    if (!container) return;

    const items = container.querySelectorAll('.product-item');
    items.forEach((item, index) => {
        const numberSpan = item.querySelector('.product-item-number');
        if (numberSpan) {
            numberSpan.textContent = `产品 ${index + 1}`;
        }
    });
}

// 收集所有产品项数据
function collectProductItems() {
    const container = document.getElementById('products-container');
    if (!container) return [];

    const items = container.querySelectorAll('.product-item');
    const products = [];

    items.forEach((item, index) => {
        const itemId = item.dataset.itemId;
        const nameInput = item.querySelector(`.product-name[data-item-id="${itemId}"]`);
        const quantityInput = item.querySelector(`.product-quantity[data-item-id="${itemId}"]`);
        const expiryInput = item.querySelector(`.product-expiry[data-item-id="${itemId}"]`);

        const product = {
            product_name: nameInput ? nameInput.value.trim() : '',
            quantity: quantityInput && quantityInput.value ? parseInt(quantityInput.value) : null,
            expiry_date: expiryInput && expiryInput.value ? expiryInput.value : null,
            sort_order: index
        };

        // 只添加有内容的产品项
        if (product.product_name || product.quantity || product.expiry_date) {
            products.push(product);
        }
    });

    return products;
}

// 清空所有产品项
function clearProductItems() {
    const container = document.getElementById('products-container');
    if (!container) return;

    const items = container.querySelectorAll('.product-item');
    items.forEach(item => {
        const itemId = item.dataset.itemId;
        const nameInput = item.querySelector(`.product-name[data-item-id="${itemId}"]`);
        const quantityInput = item.querySelector(`.product-quantity[data-item-id="${itemId}"]`);
        const expiryInput = item.querySelector(`.product-expiry[data-item-id="${itemId}"]`);

        if (nameInput) nameInput.value = '';
        if (quantityInput) quantityInput.value = '';
        if (expiryInput) expiryInput.value = '';
    });
}

// 填充产品项数据(用于预填)
function fillProductItems(items) {
    if (!Array.isArray(items) || items.length === 0) return;

    const container = document.getElementById('products-container');
    if (!container) return;

    // 清空现有项
    container.innerHTML = '';
    state.productItemCounter = 0;

    // 添加每个产品项
    items.forEach((item, index) => {
        addProductItem();

        const itemId = state.productItemCounter;
        const nameInput = container.querySelector(`.product-name[data-item-id="${itemId}"]`);
        const quantityInput = container.querySelector(`.product-quantity[data-item-id="${itemId}"]`);
        const expiryInput = container.querySelector(`.product-expiry[data-item-id="${itemId}"]`);

        if (nameInput && item.product_name) {
            nameInput.value = item.product_name;
        }
        if (quantityInput && item.quantity) {
            quantityInput.value = item.quantity;
        }
        if (expiryInput && item.expiry_date) {
            expiryInput.value = item.expiry_date;
        }
    });
}

// ============= 产品名称快捷标签和搜索功能 =============

// 显示产品名称快捷标签
function showProductNameSuggestion(itemId) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const suggestionDiv = container.querySelector(`.product-name-suggestion[data-item-id="${itemId}"]`);
    const suggestionBtn = container.querySelector(`.btn-apply-product-name[data-item-id="${itemId}"]`);

    if (!suggestionDiv || !suggestionBtn) return;

    const productName = state.lastProductName.trim();
    if (!productName) {
        suggestionDiv.style.display = 'none';
        return;
    }

    suggestionBtn.textContent = productName;
    suggestionBtn.dataset.productName = productName;
    suggestionDiv.style.display = 'flex';
}

// 隐藏产品名称快捷标签
function hideProductNameSuggestion(itemId) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const suggestionDiv = container.querySelector(`.product-name-suggestion[data-item-id="${itemId}"]`);
    if (suggestionDiv) {
        suggestionDiv.style.display = 'none';
    }
}

// 产品名称输入事件
function onProductNameInput(itemId, keyword) {
    // 隐藏快捷标签
    hideProductNameSuggestion(itemId);

    // 清除之前的延时
    const timeoutId = state.productNameSearchTimeouts.get(itemId);
    if (timeoutId) {
        clearTimeout(timeoutId);
    }

    if (!keyword) {
        hideProductNameSearchResults(itemId);
        showProductNameSuggestion(itemId);
        return;
    }

    // 延时搜索（防抖）
    const newTimeoutId = setTimeout(() => {
        performProductNameSearch(itemId, keyword);
    }, 300);

    state.productNameSearchTimeouts.set(itemId, newTimeoutId);
}

// 执行产品名称搜索
async function performProductNameSearch(itemId, keyword) {
    try {
        const response = await fetch(
            `/express/index.php?action=search_product_name_api&keyword=${encodeURIComponent(keyword)}`
        );
        const data = await response.json();

        if (data.success && Array.isArray(data.data)) {
            displayProductNameSearchResults(itemId, data.data, keyword);
        }
    } catch (error) {
        console.error('Product name search error:', error);
    }
}

// 显示产品名称搜索结果
function displayProductNameSearchResults(itemId, results, keyword) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const resultsDiv = container.querySelector(`.product-name-search-results[data-item-id="${itemId}"]`);
    if (!resultsDiv) return;

    if (results.length === 0) {
        resultsDiv.style.display = 'none';
        return;
    }

    resultsDiv.innerHTML = results.map(item => `
        <div class="product-name-search-item" data-item-id="${itemId}" data-product-name="${escapeHtml(item.product_name)}">
            <span class="product-name-text">${escapeHtml(item.product_name)}</span>
            <span class="product-name-count">已用${item.usage_count}次</span>
        </div>
    `).join('');

    // 绑定点击事件
    resultsDiv.querySelectorAll('.product-name-search-item').forEach(item => {
        item.addEventListener('click', function() {
            selectProductName(itemId, this.dataset.productName);
        });
    });

    resultsDiv.style.display = 'block';
}

// 隐藏产品名称搜索结果
function hideProductNameSearchResults(itemId) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const resultsDiv = container.querySelector(`.product-name-search-results[data-item-id="${itemId}"]`);
    if (resultsDiv) {
        resultsDiv.style.display = 'none';
    }
}

// 选择产品名称
function selectProductName(itemId, productName) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const nameInput = container.querySelector(`.product-name[data-item-id="${itemId}"]`);
    if (nameInput) {
        nameInput.value = productName;
        nameInput.focus();
    }

    hideProductNameSearchResults(itemId);
}

// 从历史记录中同步最后一个产品名称
function syncLastProductName(records) {
    state.lastProductName = '';

    if (!Array.isArray(records)) {
        return;
    }

    // 查找最新的清点记录，提取产品名称
    const latestCountRecord = records.find(rec => rec.operation === 'count' && rec.notes && rec.notes.trim());
    if (latestCountRecord) {
        const notes = latestCountRecord.notes.trim();
        // notes格式：单个产品 "番茄酱" 或多个产品 "番茄酱, 辣椒酱"
        // 提取第一个产品名称（逗号前的部分）
        const match = notes.match(/^([^,]+)/);
        if (match) {
            state.lastProductName = match[1].trim();
            console.log('[产品名称快捷标签] 同步最新产品名称:', state.lastProductName);
        }
    }
}

// [新增] 更新所有产品项的产品名称快捷标签显示
function updateAllProductNameSuggestions() {
    const container = document.getElementById('products-container');
    if (!container) return;

    // 获取所有产品项
    const productItems = container.querySelectorAll('.product-item');
    productItems.forEach(item => {
        const itemId = item.dataset.itemId;
        if (itemId) {
            showProductNameSuggestion(itemId);
        }
    });
}

// ============= 保质期建议功能 =============

// 检查产品保质期建议
async function checkProductExpirySuggestion(itemId, productName) {
    const container = document.getElementById('products-container');
    if (!container) return;

    const suggestionDiv = container.querySelector(`.expiry-suggestion[data-item-id="${itemId}"]`);
    const suggestionBtn = container.querySelector(`.btn-apply-expiry[data-item-id="${itemId}"]`);

    if (!suggestionDiv || !suggestionBtn) return;

    // 如果产品名称为空，隐藏建议
    if (!productName) {
        suggestionDiv.style.display = 'none';
        suggestionBtn.textContent = '';
        suggestionBtn.dataset.expiryDate = '';
        return;
    }

    // 如果没有选择批次，无法查询
    if (!state.currentBatchId) {
        suggestionDiv.style.display = 'none';
        return;
    }

    try {
        const params = new URLSearchParams({
            batch_id: state.currentBatchId,
            product_name: productName
        });

        const response = await fetch(`/express/index.php?action=get_product_expiry_api&${params.toString()}`);
        const data = await response.json();

        if (data.success && data.data && data.data.expiry_date) {
            const expiryDate = data.data.expiry_date;
            const usageCount = data.data.usage_count || 1;

            // 格式化日期显示
            const dateObj = new Date(expiryDate);
            const displayText = `${expiryDate} (已用${usageCount}次)`;

            suggestionBtn.textContent = displayText;
            suggestionBtn.dataset.expiryDate = expiryDate;
            suggestionDiv.style.display = 'flex';
        } else {
            // 没有找到建议，隐藏
            suggestionDiv.style.display = 'none';
            suggestionBtn.textContent = '';
            suggestionBtn.dataset.expiryDate = '';
        }
    } catch (error) {
        console.error('Failed to get expiry suggestion:', error);
        suggestionDiv.style.display = 'none';
    }
}

// ============= 有效期模态框功能 =============

// 打开有效期模态框
async function openExpiryModal(itemId) {
    state.currentExpiryModalItemId = itemId;

    const overlay = document.getElementById('expiry-modal-overlay');
    if (!overlay) return;

    // 获取当前产品项的数据
    const container = document.getElementById('products-container');
    if (!container) return;

    const nameInput = container.querySelector(`.product-name[data-item-id="${itemId}"]`);
    const expiryInput = container.querySelector(`.product-expiry[data-item-id="${itemId}"]`);

    const productName = nameInput ? nameInput.value.trim() : '';
    const currentExpiry = expiryInput ? expiryInput.value : '';

    // 重置模态框
    resetExpiryModal();

    // 如果有当前有效期，预填到直接输入框
    const directInput = document.getElementById('modal-expiry-direct');
    if (directInput && currentExpiry) {
        directInput.value = currentExpiry;
    }

    // 绑定模态框事件
    bindExpiryModalEvents();

    // 加载有效期建议（基于产品名称）
    if (productName && state.currentBatchId) {
        await loadExpiryModalSuggestions(productName);
    }

    // 显示模态框
    overlay.style.display = 'flex';

    // 防止背景滚动
    document.body.style.overflow = 'hidden';
}

// 关闭有效期模态框
function closeExpiryModal() {
    const overlay = document.getElementById('expiry-modal-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }

    // 恢复背景滚动
    document.body.style.overflow = '';

    state.currentExpiryModalItemId = null;
}

// 重置模态框
function resetExpiryModal() {
    const directInput = document.getElementById('modal-expiry-direct');
    const productionInput = document.getElementById('modal-production-date');
    const monthsSelect = document.getElementById('modal-expiry-months');
    const calculatedDiv = document.getElementById('modal-calculated-expiry');
    const suggestionsSection = document.getElementById('modal-expiry-suggestions-section');

    if (directInput) directInput.value = '';
    if (productionInput) productionInput.value = '';
    if (monthsSelect) monthsSelect.value = '';
    if (calculatedDiv) calculatedDiv.style.display = 'none';
    if (suggestionsSection) suggestionsSection.style.display = 'none';
}

// 绑定模态框事件
function bindExpiryModalEvents() {
    // 关闭按钮
    const closeBtn = document.getElementById('modal-close-btn');
    const cancelBtn = document.getElementById('modal-cancel-btn');
    const confirmBtn = document.getElementById('modal-confirm-btn');

    if (closeBtn) {
        closeBtn.onclick = closeExpiryModal;
    }

    if (cancelBtn) {
        cancelBtn.onclick = closeExpiryModal;
    }

    if (confirmBtn) {
        confirmBtn.onclick = confirmExpiryModal;
    }

    // 点击遮罩层关闭
    const overlay = document.getElementById('expiry-modal-overlay');
    if (overlay) {
        overlay.onclick = function(e) {
            if (e.target === overlay) {
                closeExpiryModal();
            }
        };
    }

    // 生产日期和月数变化时自动计算
    const productionInput = document.getElementById('modal-production-date');
    const monthsSelect = document.getElementById('modal-expiry-months');

    if (productionInput) {
        productionInput.oninput = calculateExpiryFromProduction;
    }

    if (monthsSelect) {
        monthsSelect.onchange = calculateExpiryFromProduction;
    }
}

// 从生产日期计算有效期
function calculateExpiryFromProduction() {
    const productionInput = document.getElementById('modal-production-date');
    const monthsSelect = document.getElementById('modal-expiry-months');
    const calculatedDiv = document.getElementById('modal-calculated-expiry');
    const calculatedValue = document.getElementById('modal-calculated-expiry-value');

    if (!productionInput || !monthsSelect || !calculatedDiv || !calculatedValue) return;

    const productionDate = productionInput.value;
    const months = parseInt(monthsSelect.value);

    if (!productionDate || !months) {
        calculatedDiv.style.display = 'none';
        return;
    }

    // 计算有效期
    const date = new Date(productionDate);
    date.setMonth(date.getMonth() + months);

    const expiryDate = date.toISOString().split('T')[0];

    calculatedValue.textContent = expiryDate;
    calculatedDiv.style.display = 'block';

    // 同时更新直接输入框
    const directInput = document.getElementById('modal-expiry-direct');
    if (directInput) {
        directInput.value = expiryDate;
    }
}

// 确认模态框
function confirmExpiryModal() {
    const directInput = document.getElementById('modal-expiry-direct');
    const expiryDate = directInput ? directInput.value : '';

    if (!expiryDate) {
        showMessage('请选择或计算有效期', 'warning');
        return;
    }

    // 将有效期填入对应的产品项
    const itemId = state.currentExpiryModalItemId;
    if (itemId) {
        const container = document.getElementById('products-container');
        if (container) {
            const expiryInput = container.querySelector(`.product-expiry[data-item-id="${itemId}"]`);
            if (expiryInput) {
                expiryInput.value = expiryDate;
            }
        }
    }

    closeExpiryModal();
}

// 加载有效期建议
async function loadExpiryModalSuggestions(productName) {
    const suggestionsDiv = document.getElementById('modal-expiry-suggestions');
    const suggestionsSection = document.getElementById('modal-expiry-suggestions-section');

    if (!suggestionsDiv || !suggestionsSection) return;

    try {
        const params = new URLSearchParams({
            batch_id: state.currentBatchId,
            product_name: productName
        });

        const response = await fetch(`/express/index.php?action=get_product_expiry_api&${params.toString()}`);
        const data = await response.json();

        if (data.success && data.data && data.data.expiry_date) {
            const expiryDate = data.data.expiry_date;
            const usageCount = data.data.usage_count || 1;

            suggestionsDiv.innerHTML = `
                <div class="modal-expiry-suggestion-item" data-expiry-date="${expiryDate}">
                    ${expiryDate} (已用${usageCount}次)
                </div>
            `;

            // 绑定点击事件
            suggestionsDiv.querySelectorAll('.modal-expiry-suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    const expiry = this.dataset.expiryDate;
                    const directInput = document.getElementById('modal-expiry-direct');
                    if (directInput) {
                        directInput.value = expiry;
                    }
                });
            });

            suggestionsSection.style.display = 'block';
        } else {
            suggestionsSection.style.display = 'none';
        }
    } catch (error) {
        console.error('Failed to load expiry suggestions:', error);
        suggestionsSection.style.display = 'none';
    }
}