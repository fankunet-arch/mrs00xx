/**
 * MRS Count Operations JavaScript
 * 文件路径: dc_html/mrs/js/count_ops.js
 * 说明: 清点操作页面交互逻辑
 */

(function() {
    'use strict';

    // === 全局变量 ===
    let currentBoxData = null; // 当前搜索到的箱子数据
    let autocompleteTimeout = null; // 自动完成搜索延时
    let currentSuggestionIndex = -1; // 当前选中的建议索引

    // === DOM 元素 ===
    const boxNumberInput = document.getElementById('box-number-input');
    const autocompleteSuggestions = document.getElementById('autocomplete-suggestions');
    const btnSearch = document.getElementById('btn-search');
    const resultSection = document.getElementById('result-section');
    const searchResultContainer = document.getElementById('search-result-container');
    const historyContainer = document.getElementById('history-container');
    const btnRefreshHistory = document.getElementById('btn-refresh-history');
    const btnFinishSession = document.getElementById('btn-finish-session');
    const totalCountedEl = document.getElementById('total-counted');

    // 清点模态框
    const countModal = document.getElementById('count-modal');
    const modalBoxNumber = document.getElementById('modal-box-number');
    const modalLedgerId = document.getElementById('modal-ledger-id');
    const modalSystemContent = document.getElementById('modal-system-content');
    const systemInfoContainer = document.getElementById('system-info-container');
    const qtyCheckSection = document.getElementById('qty-check-section');
    const itemsContainer = document.getElementById('items-container');
    const btnAddItem = document.getElementById('btn-add-item');
    const countRemark = document.getElementById('count-remark');
    const btnModalSave = document.getElementById('modal-save-btn');
    const btnModalCancel = document.getElementById('modal-cancel-btn');
    const btnModalClose = document.getElementById('modal-close-btn');

    // 快速录入模态框
    const quickAddModal = document.getElementById('quick-add-modal');
    const quickAddBoxNumber = document.getElementById('quick-add-box-number');
    const quickAddBoxDisplay = document.getElementById('quick-add-box-display');
    const quickAddSku = document.getElementById('quick-add-sku');
    const quickAddSkuId = document.getElementById('quick-add-sku-id');
    const skuSuggestions = document.getElementById('sku-suggestions');
    const quickAddQty = document.getElementById('quick-add-qty');
    const quickAddContent = document.getElementById('quick-add-content');
    const btnQuickAddSave = document.getElementById('quick-add-save-btn');
    const btnQuickAddCancel = document.getElementById('quick-add-cancel-btn');
    const btnQuickAddClose = document.getElementById('quick-add-close-btn');

    // 报告模态框
    const reportModal = document.getElementById('report-modal');
    const reportContent = document.getElementById('report-content');
    const btnReportOk = document.getElementById('report-ok-btn');
    const btnReportClose = document.getElementById('report-close-btn');

    // === 更新时间 ===
    function updateTime() {
        const now = new Date();
        const timeStr = now.toLocaleString('zh-CN', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
        const timeEl = document.getElementById('current-time');
        if (timeEl) {
            timeEl.textContent = timeStr;
        }
    }

    updateTime();
    setInterval(updateTime, 1000);

    // === 搜索箱号 ===
    function searchBox() {
        const boxNumber = boxNumberInput.value.trim();

        if (!boxNumber) {
            alert('请输入箱号');
            boxNumberInput.focus();
            return;
        }

        // 禁用搜索按钮
        btnSearch.disabled = true;
        btnSearch.textContent = '搜索中...';

        fetch('/mrs/index.php?action=count_search_box&box_number=' + encodeURIComponent(boxNumber))
            .then(response => response.json())
            .then(data => {
                btnSearch.disabled = false;
                btnSearch.textContent = '搜索';

                if (data.found && data.data && data.data.length > 0) {
                    // 找到箱子
                    displaySearchResults(data.data);
                } else {
                    // 未找到箱子，显示快速录入选项
                    displayNotFound(boxNumber);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('搜索失败，请重试');
                btnSearch.disabled = false;
                btnSearch.textContent = '搜索';
            });
    }

    // 搜索按钮点击事件
    if (btnSearch) {
        btnSearch.addEventListener('click', searchBox);
    }

    // 回车搜索
    if (boxNumberInput) {
        boxNumberInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // 如果有选中的建议，选择它
                if (currentSuggestionIndex >= 0) {
                    const items = autocompleteSuggestions.querySelectorAll('.autocomplete-item');
                    if (items[currentSuggestionIndex]) {
                        items[currentSuggestionIndex].click();
                        return;
                    }
                }
                // 否则直接搜索
                hideAutocomplete();
                searchBox();
            }
        });

        // 实时搜索自动完成
        boxNumberInput.addEventListener('input', function(e) {
            const keyword = this.value.trim();

            // 重置建议索引
            currentSuggestionIndex = -1;

            // 如果输入为空，隐藏建议列表
            if (keyword.length === 0) {
                hideAutocomplete();
                return;
            }

            // 清除之前的延时
            clearTimeout(autocompleteTimeout);

            // 延迟300ms后搜索（防抖）
            autocompleteTimeout = setTimeout(() => {
                fetchAutocomplete(keyword);
            }, 300);
        });

        // 键盘导航（上下键）
        boxNumberInput.addEventListener('keydown', function(e) {
            if (!autocompleteSuggestions || autocompleteSuggestions.style.display === 'none') {
                return;
            }

            const items = autocompleteSuggestions.querySelectorAll('.autocomplete-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentSuggestionIndex = Math.min(currentSuggestionIndex + 1, items.length - 1);
                updateSuggestionHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentSuggestionIndex = Math.max(currentSuggestionIndex - 1, -1);
                updateSuggestionHighlight(items);
            } else if (e.key === 'Escape') {
                hideAutocomplete();
            }
        });
    }

    // === 自动完成搜索 ===
    function fetchAutocomplete(keyword) {
        fetch('/mrs/index.php?action=count_autocomplete_box&keyword=' + encodeURIComponent(keyword))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    displayAutocomplete(data.data);
                }
            })
            .catch(error => {
                console.error('Autocomplete error:', error);
            });
    }

    function displayAutocomplete(suggestions) {
        if (!suggestions || suggestions.length === 0) {
            autocompleteSuggestions.innerHTML = '<div class="autocomplete-no-results">没有找到匹配的箱子</div>';
            autocompleteSuggestions.style.display = 'block';
            return;
        }

        let html = '';
        suggestions.forEach((item, index) => {
            html += `
                <div class="autocomplete-item" data-box-number="${escapeHtml(item.box_number)}" data-tracking-number="${escapeHtml(item.tracking_number || '')}" data-index="${index}">
                    <div class="autocomplete-box-number">${escapeHtml(item.box_number)}</div>
                    <div class="autocomplete-details">
                        ${item.sku_name ? `<div class="autocomplete-detail-item"><span class="autocomplete-detail-label">SKU:</span><span class="autocomplete-detail-value">${escapeHtml(item.sku_name)}</span></div>` : ''}
                        ${item.content_note ? `<div class="autocomplete-detail-item"><span class="autocomplete-detail-label">内容:</span><span class="autocomplete-detail-value">${escapeHtml(item.content_note)}</span></div>` : ''}
                        ${item.quantity ? `<div class="autocomplete-detail-item"><span class="autocomplete-detail-label">数量:</span><span class="autocomplete-detail-value">${item.quantity}${escapeHtml(item.standard_unit || '件')}</span></div>` : ''}
                        ${item.batch_name ? `<div class="autocomplete-detail-item"><span class="autocomplete-detail-label">批次:</span><span class="autocomplete-detail-value">${escapeHtml(item.batch_name)}</span></div>` : '<div class="autocomplete-detail-item"><span class="autocomplete-detail-value" style="color:#ff9800;">零散入库</span></div>'}
                        ${item.tracking_number ? `<div class="autocomplete-detail-item"><span class="autocomplete-detail-label">快递:</span><span class="autocomplete-detail-value">${escapeHtml(item.tracking_number)}</span></div>` : ''}
                    </div>
                </div>
            `;
        });

        autocompleteSuggestions.innerHTML = html;
        autocompleteSuggestions.style.display = 'block';

        // 绑定点击事件
        const items = autocompleteSuggestions.querySelectorAll('.autocomplete-item');
        items.forEach(item => {
            item.addEventListener('click', function() {
                const boxNumber = this.getAttribute('data-box-number');
                const trackingNumber = this.getAttribute('data-tracking-number');
                boxNumberInput.value = trackingNumber || boxNumber;
                hideAutocomplete();
                // 自动触发搜索
                searchBox();
            });
        });
    }

    function updateSuggestionHighlight(items) {
        items.forEach((item, index) => {
            if (index === currentSuggestionIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    function hideAutocomplete() {
        if (autocompleteSuggestions) {
            autocompleteSuggestions.style.display = 'none';
            autocompleteSuggestions.innerHTML = '';
        }
        currentSuggestionIndex = -1;
    }

    // 点击页面其他地方关闭自动完成
    document.addEventListener('click', function(e) {
        if (e.target !== boxNumberInput && !autocompleteSuggestions.contains(e.target)) {
            hideAutocomplete();
        }
    });

    // === 显示搜索结果 ===
    function displaySearchResults(results) {
        resultSection.style.display = 'block';

        let html = '<h3>搜索结果</h3>';

        results.forEach((box, index) => {
            html += `
                <div class="session-card" style="cursor: pointer;" data-index="${index}">
                    <div class="session-header">
                        <h3 class="session-name">箱号: ${escapeHtml(box.box_number)}</h3>
                        ${box.tracking_number ? `<span style="font-size:12px;color:#666;margin-left:8px;">快递: ${escapeHtml(box.tracking_number)}</span>` : ''}
                        <span class="session-status status-${box.status === 'in_stock' ? 'counting' : 'completed'}">
                            ${box.status === 'in_stock' ? '在库' : '已出库'}
                        </span>
                    </div>
                    <div class="session-info">
                        ${box.sku_name ? `<div class="info-item"><span class="info-label">SKU:</span><span class="info-value">${escapeHtml(box.sku_name)}</span></div>` : ''}
                        ${box.content_note ? `<div class="info-item"><span class="info-label">内容:</span><span class="info-value">${escapeHtml(box.content_note)}</span></div>` : ''}
                        ${box.quantity ? `<div class="info-item"><span class="info-label">数量:</span><span class="info-value">${box.quantity} ${escapeHtml(box.standard_unit || '件')}</span></div>` : ''}
                        ${box.warehouse_location ? `<div class="info-item" style="background: #fff3e0; padding: 4px 8px; margin: 4px 0; border-radius: 4px;"><span class="info-label" style="color: #e65100;">📦 货架位置:</span><span class="info-value" style="font-weight: bold; color: #e65100;">${escapeHtml(box.warehouse_location)}</span></div>` : ''}
                        <div class="info-item"><span class="info-label">入库时间:</span><span class="info-value">${formatDateTime(box.inbound_time)}</span></div>
                    </div>
                </div>
            `;
        });

        searchResultContainer.innerHTML = html;

        // 绑定点击事件
        const cards = searchResultContainer.querySelectorAll('.session-card');
        cards.forEach((card, index) => {
            card.addEventListener('click', function() {
                openCountModal(results[index]);
            });
        });

        // 保存搜索结果
        currentBoxData = results;
    }

    // === 显示未找到 ===
    function displayNotFound(boxNumber) {
        resultSection.style.display = 'block';
        searchResultContainer.innerHTML = `
            <div class="alert alert-warning">
                <strong>系统中未找到箱号: ${escapeHtml(boxNumber)}</strong>
                <p style="margin-top: 8px;">您可以选择：</p>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 12px;">
                <button class="btn btn-secondary" style="flex: 1;" id="btn-record-not-found">仅记录为"仓库有但系统无"</button>
                <button class="btn btn-primary" style="flex: 1;" id="btn-quick-add">快速录入新箱</button>
            </div>
        `;

        // 仅记录按钮
        document.getElementById('btn-record-not-found').addEventListener('click', function() {
            saveNotFoundRecord(boxNumber);
        });

        // 快速录入按钮
        document.getElementById('btn-quick-add').addEventListener('click', function() {
            openQuickAddModal(boxNumber);
        });
    }

    // === 保存"未找到"记录 ===
    function saveNotFoundRecord(boxNumber) {
        const formData = new FormData();
        formData.append('session_id', SESSION_ID);
        formData.append('box_number', boxNumber);
        formData.append('check_mode', 'box_only');

        fetch('/mrs/index.php?action=count_save_record', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('已记录为"仓库有但系统无"', 'success');
                boxNumberInput.value = '';
                boxNumberInput.focus();
                resultSection.style.display = 'none';
                refreshHistory();
                updateTotalCounted();
            } else {
                alert(data.message || '保存失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存失败，请重试');
        });
    }

    // === 打开清点模态框 ===
    function openCountModal(boxData) {
        currentBoxData = boxData;

        // 设置箱号
        modalBoxNumber.textContent = boxData.box_number;
        modalLedgerId.value = boxData.ledger_id || '';
        modalSystemContent.value = boxData.content_note || '';

        // 显示系统信息
        let systemInfo = '';
        if (boxData.sku_name) {
            systemInfo += `<div class="system-info-item"><span class="system-info-label">SKU:</span><span class="system-info-value">${escapeHtml(boxData.sku_name)}</span></div>`;
        }
        if (boxData.content_note) {
            systemInfo += `<div class="system-info-item"><span class="system-info-label">内容:</span><span class="system-info-value">${escapeHtml(boxData.content_note)}</span></div>`;
        }
        if (boxData.quantity) {
            systemInfo += `<div class="system-info-item"><span class="system-info-label">系统数量:</span><span class="system-info-value">${boxData.quantity} ${escapeHtml(boxData.standard_unit || '件')}</span></div>`;
        }
        if (boxData.warehouse_location) {
            systemInfo += `<div class="system-info-item" style="background: #fff3e0; padding: 8px; border-left: 3px solid #ff9800;"><span class="system-info-label" style="color: #e65100;">📦 当前货架位置:</span><span class="system-info-value" style="font-weight: bold; color: #e65100;">${escapeHtml(boxData.warehouse_location)}</span></div>`;
        } else {
            systemInfo += `<div class="system-info-item" style="background: #f5f5f5; padding: 8px;"><span class="system-info-label">📦 当前货架位置:</span><span class="system-info-value" style="color: #999;">未设置</span></div>`;
        }
        systemInfoContainer.innerHTML = systemInfo;

        // 显示当前货架位置（三段式）
        const shelfRowInput = document.getElementById('shelf-row');
        const shelfRackInput = document.getElementById('shelf-rack');
        const shelfLevelInput = document.getElementById('shelf-level');
        const shelfLocationHidden = document.getElementById('shelf-location');
        const currentLocationHint = document.getElementById('current-location-hint');
        const currentLocationValue = document.getElementById('current-location-value');

        // 清空三段输入框
        if (shelfRowInput) shelfRowInput.value = '';
        if (shelfRackInput) shelfRackInput.value = '';
        if (shelfLevelInput) shelfLevelInput.value = '';
        if (shelfLocationHidden) shelfLocationHidden.value = '';

        // 显示当前位置提示
        if (boxData.warehouse_location) {
            currentLocationValue.textContent = boxData.warehouse_location;
            currentLocationHint.style.display = 'block';
        } else {
            currentLocationHint.style.display = 'none';
        }

        // 重置表单
        document.querySelector('input[name="check-mode"][value="box_only"]').checked = true;
        qtyCheckSection.style.display = 'none';
        itemsContainer.innerHTML = '';
        countRemark.value = '';

        // 显示模态框
        countModal.style.display = 'flex';
    }

    // === 清点方式切换 ===
    const checkModeRadios = document.querySelectorAll('input[name="check-mode"]');
    checkModeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'with_qty') {
                qtyCheckSection.style.display = 'block';
                // 自动添加一个物品行
                if (itemsContainer.children.length === 0) {
                    addItemRow(currentBoxData);
                }
            } else {
                qtyCheckSection.style.display = 'none';
            }
        });
    });

    // === 添加物品行 ===
    if (btnAddItem) {
        btnAddItem.addEventListener('click', addItemRow);
    }

    function addItemRow(data = null) {
        const itemRow = document.createElement('div');
        itemRow.className = 'item-row';
        itemRow.innerHTML = `
            <input type="text" class="form-control item-sku-name" placeholder="SKU名称" required>
            <input type="number" class="form-control item-system-qty" placeholder="系统数量" step="0.01" readonly>
            <input type="number" class="form-control item-actual-qty" placeholder="实际数量" step="0.01" required>
            <button type="button" class="btn-remove-item">删除</button>
        `;

        if (data) {
             itemRow.querySelector('.item-sku-name').value = data.sku_name || '';
             itemRow.querySelector('.item-system-qty').value = data.quantity || '';
             // Actual quantity is left empty for user to input
        }

        itemsContainer.appendChild(itemRow);

        // 删除按钮事件
        itemRow.querySelector('.btn-remove-item').addEventListener('click', function() {
            itemRow.remove();
        });
    }

    // === 保存清点记录 ===
    if (btnModalSave) {
        btnModalSave.addEventListener('click', function() {
            const checkMode = document.querySelector('input[name="check-mode"]:checked').value;
            const ledgerId = modalLedgerId.value;
            const boxNumber = modalBoxNumber.textContent;

            const formData = new FormData();
            formData.append('session_id', SESSION_ID);
            formData.append('box_number', boxNumber);
            formData.append('ledger_id', ledgerId || '');
            formData.append('check_mode', checkMode);
            formData.append('remark', countRemark.value.trim());

            // 添加货架位置（从隐藏字段读取三段式组合后的值）
            const shelfLocationHidden = document.getElementById('shelf-location');
            if (shelfLocationHidden) {
                formData.append('shelf_location', shelfLocationHidden.value.trim());
            }

            // 如果是核对数量模式，收集物品信息
            if (checkMode === 'with_qty') {
                const items = [];
                const itemRows = itemsContainer.querySelectorAll('.item-row');

                if (itemRows.length === 0) {
                    alert('请至少添加一件物品');
                    return;
                }

                let hasError = false;
                itemRows.forEach(row => {
                    const skuName = row.querySelector('.item-sku-name').value.trim();
                    const actualQty = parseFloat(row.querySelector('.item-actual-qty').value) || 0;
                    const systemQty = parseFloat(row.querySelector('.item-system-qty').value) || 0;

                    if (!skuName || !actualQty) {
                        hasError = true;
                        return;
                    }

                    items.push({
                        sku_name: skuName,
                        system_qty: systemQty,
                        actual_qty: actualQty,
                        unit: '件'
                    });
                });

                if (hasError) {
                    alert('请填写完整的物品信息');
                    return;
                }

                formData.append('items', JSON.stringify(items));
            }

            // 禁用按钮
            btnModalSave.disabled = true;
            btnModalSave.textContent = '保存中...';

            fetch('/mrs/index.php?action=count_save_record', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('清点记录保存成功', 'success');
                    closeCountModal();
                    boxNumberInput.value = '';
                    boxNumberInput.focus();
                    resultSection.style.display = 'none';
                    refreshHistory();
                    updateTotalCounted();
                } else {
                    alert(data.message || '保存失败');
                    btnModalSave.disabled = false;
                    btnModalSave.textContent = '保存';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('保存失败，请重试');
                btnModalSave.disabled = false;
                btnModalSave.textContent = '保存';
            });
        });
    }

    // === 关闭清点模态框 ===
    function closeCountModal() {
        countModal.style.display = 'none';
        btnModalSave.disabled = false;
        btnModalSave.textContent = '保存';
    }

    if (btnModalClose) {
        btnModalClose.addEventListener('click', closeCountModal);
    }

    if (btnModalCancel) {
        btnModalCancel.addEventListener('click', closeCountModal);
    }

    countModal.addEventListener('click', function(e) {
        if (e.target === countModal) {
            closeCountModal();
        }
    });

    // === 打开快速录入模态框 ===
    function openQuickAddModal(boxNumber) {
        quickAddBoxNumber.value = boxNumber;
        quickAddBoxDisplay.textContent = boxNumber;
        quickAddSku.value = '';
        quickAddSkuId.value = '';
        quickAddQty.value = '';
        quickAddContent.value = '';
        skuSuggestions.style.display = 'none';

        quickAddModal.style.display = 'flex';
        quickAddSku.focus();
    }

    // === SKU搜索建议 ===
    if (quickAddSku) {
        let searchTimeout;

        quickAddSku.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const keyword = this.value.trim();

            if (keyword.length < 2) {
                skuSuggestions.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                // 这里可以调用SKU搜索API
                // 暂时简化处理
                skuSuggestions.style.display = 'none';
            }, 300);
        });
    }

    // === 保存快速录入 ===
    if (btnQuickAddSave) {
        btnQuickAddSave.addEventListener('click', function() {
            const boxNumber = quickAddBoxNumber.value;
            const skuName = quickAddSku.value.trim();
            const skuId = quickAddSkuId.value;
            const qty = quickAddQty.value.trim();
            const content = quickAddContent.value.trim();

            if (!skuName) {
                alert('请输入SKU名称');
                quickAddSku.focus();
                return;
            }

            const formData = new FormData();
            formData.append('session_id', SESSION_ID);
            formData.append('box_number', boxNumber);
            formData.append('sku_name', skuName);
            formData.append('sku_id', skuId);
            formData.append('quantity', qty);
            formData.append('content_note', content || skuName);

            btnQuickAddSave.disabled = true;
            btnQuickAddSave.textContent = '保存中...';

            fetch('/mrs/index.php?action=count_quick_add_box', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('新箱录入成功并已清点', 'success');
                    closeQuickAddModal();
                    boxNumberInput.value = '';
                    boxNumberInput.focus();
                    resultSection.style.display = 'none';
                    refreshHistory();
                    updateTotalCounted();
                } else {
                    alert(data.message || '保存失败');
                    btnQuickAddSave.disabled = false;
                    btnQuickAddSave.textContent = '保存并清点';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('保存失败，请重试');
                btnQuickAddSave.disabled = false;
                btnQuickAddSave.textContent = '保存并清点';
            });
        });
    }

    // === 关闭快速录入模态框 ===
    function closeQuickAddModal() {
        quickAddModal.style.display = 'none';
        btnQuickAddSave.disabled = false;
        btnQuickAddSave.textContent = '保存并清点';
    }

    if (btnQuickAddClose) {
        btnQuickAddClose.addEventListener('click', closeQuickAddModal);
    }

    if (btnQuickAddCancel) {
        btnQuickAddCancel.addEventListener('click', closeQuickAddModal);
    }

    quickAddModal.addEventListener('click', function(e) {
        if (e.target === quickAddModal) {
            closeQuickAddModal();
        }
    });

    // === 刷新历史记录 ===
    function refreshHistory() {
        fetch('/mrs/index.php?action=count_get_recent&session_id=' + SESSION_ID + '&limit=20')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(record => {
                        let statusText = '';
                        let statusClass = '';

                        if (record.match_status === 'found') {
                            statusText = '✓ 已确认';
                            statusClass = 'status-completed';
                        } else if (record.match_status === 'matched') {
                            statusText = '✓ 数量一致';
                            statusClass = 'status-completed';
                        } else if (record.match_status === 'diff') {
                            statusText = '⚠ 数量有差异';
                            statusClass = 'status-cancelled';
                        } else if (record.match_status === 'not_found') {
                            statusText = '✗ 系统无此箱';
                            statusClass = 'status-cancelled';
                        }

                        html += `
                            <div class="history-item">
                                <div class="history-box">
                                    箱号: ${escapeHtml(record.box_number)}
                                    <span class="session-status ${statusClass}" style="margin-left: 8px; font-size: 12px; padding: 2px 8px;">
                                        ${statusText}
                                    </span>
                                </div>
                                ${record.system_content ? `<div class="history-info">内容: ${escapeHtml(record.system_content)}</div>` : ''}
                                ${record.remark ? `<div class="history-info">备注: ${escapeHtml(record.remark)}</div>` : ''}
                                <div class="history-time">${formatDateTime(record.counted_at)}</div>
                            </div>
                        `;
                    });
                    historyContainer.innerHTML = html;
                } else {
                    historyContainer.innerHTML = '<p class="empty-text">暂无记录</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // 初始加载历史
    refreshHistory();

    // 刷新历史按钮
    if (btnRefreshHistory) {
        btnRefreshHistory.addEventListener('click', refreshHistory);
    }

    // === 更新已清点数量 ===
    function updateTotalCounted() {
        const current = parseInt(totalCountedEl.textContent) || 0;
        totalCountedEl.textContent = current + 1;
    }

    // === 完成清点并生成报告 ===
    if (btnFinishSession) {
        btnFinishSession.addEventListener('click', function() {
            if (!confirm('确定要完成此次清点任务吗？完成后将无法继续添加清点记录。')) {
                return;
            }

            btnFinishSession.disabled = true;
            btnFinishSession.textContent = '生成报告中...';

            const formData = new FormData();
            formData.append('session_id', SESSION_ID);

            fetch('/mrs/index.php?action=count_finish_session', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showReport(data.report);
                } else {
                    alert(data.message || '完成失败');
                    btnFinishSession.disabled = false;
                    btnFinishSession.textContent = '完成清点并生成报告';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('操作失败，请重试');
                btnFinishSession.disabled = false;
                btnFinishSession.textContent = '完成清点并生成报告';
            });
        });
    }

    // === 显示报告 ===
    function showReport(report) {
        const stats = report.stats;

        let html = `
            <div class="report-section">
                <h4>清点统计</h4>
                <div class="report-stats">
                    <div class="report-stat-item">
                        <div class="report-stat-label">总清点数</div>
                        <div class="report-stat-value">${stats.total_count || 0}</div>
                    </div>
                    <div class="report-stat-item">
                        <div class="report-stat-label">仅确认箱子</div>
                        <div class="report-stat-value">${stats.box_only_count || 0}</div>
                    </div>
                    <div class="report-stat-item">
                        <div class="report-stat-label">核对数量</div>
                        <div class="report-stat-value">${stats.with_qty_count || 0}</div>
                    </div>
                    <div class="report-stat-item">
                        <div class="report-stat-label">数量一致</div>
                        <div class="report-stat-value">${stats.matched_count || 0}</div>
                    </div>
                    <div class="report-stat-item">
                        <div class="report-stat-label">数量有差异</div>
                        <div class="report-stat-value" style="color: #e65100;">${stats.diff_count || 0}</div>
                    </div>
                    <div class="report-stat-item">
                        <div class="report-stat-label">仓库有但系统无</div>
                        <div class="report-stat-value" style="color: #c62828;">${stats.not_found_count || 0}</div>
                    </div>
                </div>
            </div>
        `;

        // 未清点的箱子
        if (report.missing_count > 0) {
            html += `
                <div class="report-section">
                    <h4>系统中有但未清点的箱子 (${report.missing_count}个)</h4>
                    <ul class="report-list">
            `;
            report.missing_boxes.slice(0, 50).forEach(box => {
                html += `<li>${escapeHtml(box)}</li>`;
            });
            if (report.missing_count > 50) {
                html += `<li>... 还有 ${report.missing_count - 50} 个箱子未显示</li>`;
            }
            html += '</ul></div>';
        }

        // 数量有差异的记录
        if (report.diff_records && report.diff_records.length > 0) {
            html += `
                <div class="report-section">
                    <h4>数量有差异的箱子 (${report.diff_records.length}个)</h4>
                    <ul class="report-list">
            `;
            report.diff_records.forEach(record => {
                html += `<li><strong>${escapeHtml(record.box_number)}</strong>: ${escapeHtml(record.items_detail || '数量不一致')}</li>`;
            });
            html += '</ul></div>';
        }

        // 仓库有但系统无的记录
        if (report.not_found_records && report.not_found_records.length > 0) {
            html += `
                <div class="report-section">
                    <h4>仓库有但系统无的箱子 (${report.not_found_records.length}个)</h4>
                    <ul class="report-list">
            `;
            report.not_found_records.forEach(record => {
                html += `<li>${escapeHtml(record.box_number)}</li>`;
            });
            html += '</ul></div>';
        }

        reportContent.innerHTML = html;
        reportModal.style.display = 'flex';
    }

    // === 关闭报告模态框 ===
    function closeReportModal() {
        reportModal.style.display = 'none';
        // 返回首页
        window.location.href = '/mrs/index.php?action=count_home';
    }

    if (btnReportOk) {
        btnReportOk.addEventListener('click', closeReportModal);
    }

    if (btnReportClose) {
        btnReportClose.addEventListener('click', closeReportModal);
    }

    reportModal.addEventListener('click', function(e) {
        if (e.target === reportModal) {
            closeReportModal();
        }
    });

    // === 工具函数 ===
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return '';
        const date = new Date(dateTimeStr);
        return date.toLocaleString('zh-CN', {
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function showMessage(message, type) {
        // 简单的消息提示
        alert(message);
    }

    // === 三段式货架位置输入处理 ===
    (function() {
        const rowInput = document.getElementById('shelf-row');
        const rackInput = document.getElementById('shelf-rack');
        const levelInput = document.getElementById('shelf-level');
        const hiddenInput = document.getElementById('shelf-location');

        if (!rowInput || !rackInput || !levelInput || !hiddenInput) return;

        const segments = [rowInput, rackInput, levelInput];

        // 更新隐藏字段
        function updateShelfLocation() {
            const row = rowInput.value.trim();
            const rack = rackInput.value.trim();
            const level = levelInput.value.trim();

            // 如果都为空，隐藏字段也为空
            if (!row && !rack && !level) {
                hiddenInput.value = '';
                return;
            }

            // 组合成格式化字符串
            const parts = [];
            if (row) parts.push(row.padStart(2, '0'));
            if (rack) parts.push(rack.padStart(2, '0'));
            if (level) parts.push(level.padStart(2, '0'));

            hiddenInput.value = parts.join('-');
        }

        // 为每个输入框添加事件监听
        segments.forEach((input, index) => {
            // 只允许输入数字
            input.addEventListener('input', function(e) {
                // 过滤非数字字符
                this.value = this.value.replace(/\D/g, '');

                // 限制最多2位
                if (this.value.length > 2) {
                    this.value = this.value.substring(0, 2);
                }

                // 更新隐藏字段
                updateShelfLocation();

                // 输入满2位后立即跳转到下一个输入框
                if (this.value.length === 2 && index < segments.length - 1) {
                    // 使用setTimeout确保DOM更新后再跳转
                    setTimeout(() => {
                        segments[index + 1].focus();
                        segments[index + 1].select();
                    }, 0);
                }
            });

            // 同时监听keyup事件以处理单字符输入的跳转
            input.addEventListener('keyup', function(e) {
                // 如果已经是2位数字且不是导航键，跳转
                if (this.value.length === 2 && index < segments.length - 1) {
                    const navKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Backspace', 'Delete'];
                    if (!navKeys.includes(e.key)) {
                        setTimeout(() => {
                            segments[index + 1].focus();
                            segments[index + 1].select();
                        }, 0);
                    }
                }
            });

            // 支持键盘导航
            input.addEventListener('keydown', function(e) {
                // Backspace: 如果当前为空，跳到上一个
                if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                    e.preventDefault();
                    segments[index - 1].focus();
                    segments[index - 1].value = '';
                    updateShelfLocation();
                }

                // 左箭头: 跳到上一个
                if (e.key === 'ArrowLeft' && this.selectionStart === 0 && index > 0) {
                    e.preventDefault();
                    segments[index - 1].focus();
                    segments[index - 1].setSelectionRange(segments[index - 1].value.length, segments[index - 1].value.length);
                }

                // 右箭头: 跳到下一个
                if (e.key === 'ArrowRight' && this.selectionStart === this.value.length && index < segments.length - 1) {
                    e.preventDefault();
                    segments[index + 1].focus();
                    segments[index + 1].setSelectionRange(0, 0);
                }
            });

            // 粘贴处理：自动拆分格式化字符串
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').trim();

                // 如果是格式化字符串（如 "01-02-03"）
                if (pasteData.includes('-')) {
                    const parts = pasteData.split('-').map(p => p.trim().replace(/\D/g, ''));
                    if (parts[0]) rowInput.value = parts[0].substring(0, 2);
                    if (parts[1]) rackInput.value = parts[1].substring(0, 2);
                    if (parts[2]) levelInput.value = parts[2].substring(0, 2);
                    updateShelfLocation();
                } else {
                    // 否则只粘贴数字到当前框
                    const numbers = pasteData.replace(/\D/g, '');
                    this.value = numbers.substring(0, 2);
                    if (numbers.length > 2 && index < segments.length - 1) {
                        segments[index + 1].focus();
                    }
                    updateShelfLocation();
                }
            });
        });
    })();
})();
