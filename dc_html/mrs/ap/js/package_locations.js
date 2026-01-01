/**
 * Package Locations Management JavaScript
 * 文件路径: dc_html/mrs/ap/js/package_locations.js
 */

let currentPage = 1;
let totalPages = 1;

// 页面加载时获取数据
document.addEventListener('DOMContentLoaded', function() {
    loadPackageLocations(1);
    initSegmentedInputs();
});

// 初始化三段式输入
function initSegmentedInputs() {
    const segments = document.querySelectorAll('.shelf-segment');

    segments.forEach((input, index) => {
        const allSegments = Array.from(input.closest('.shelf-inputs').querySelectorAll('.shelf-segment'));
        const currentIndex = allSegments.indexOf(input);

        // 只允许输入数字
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');

            if (this.value.length > 2) {
                this.value = this.value.substring(0, 2);
            }

            // 输入满2位后自动跳转
            if (this.value.length === 2 && currentIndex < allSegments.length - 1) {
                setTimeout(() => {
                    allSegments[currentIndex + 1].focus();
                    allSegments[currentIndex + 1].select();
                }, 0);
            }
        });

        // 同时监听keyup事件
        input.addEventListener('keyup', function(e) {
            if (this.value.length === 2 && currentIndex < allSegments.length - 1) {
                const navKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Backspace', 'Delete'];
                if (!navKeys.includes(e.key)) {
                    setTimeout(() => {
                        allSegments[currentIndex + 1].focus();
                        allSegments[currentIndex + 1].select();
                    }, 0);
                }
            }
        });

        // 支持键盘导航
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && currentIndex > 0) {
                e.preventDefault();
                allSegments[currentIndex - 1].focus();
                allSegments[currentIndex - 1].select();
            }

            if (e.key === 'ArrowLeft' && currentIndex > 0) {
                e.preventDefault();
                allSegments[currentIndex - 1].focus();
            }

            if (e.key === 'ArrowRight' && currentIndex < allSegments.length - 1) {
                e.preventDefault();
                allSegments[currentIndex + 1].focus();
            }
        });
    });
}

// 加载箱子位置数据
function loadPackageLocations(page) {
    currentPage = page;
    const params = new URLSearchParams({
        operation: 'list',
        page: page,
        limit: 20
    });

    const boxNumber = document.getElementById('filter-box-number').value.trim();
    const location = document.getElementById('filter-location').value.trim();
    const batch = document.getElementById('filter-batch').value.trim();
    const status = document.getElementById('filter-status').value;

    if (boxNumber) params.append('box_number', boxNumber);
    if (location) params.append('location', location);
    if (batch) params.append('batch_name', batch);
    if (status) params.append('status', status);

    fetch(`/mrs/ap/index.php?action=backend_package_locations&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLocations(data.data.items);
                updatePagination(data.data.pagination);
            } else {
                alert('加载失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('加载失败，请稍后重试');
        });
}

// 显示数据
function displayLocations(items) {
    const tbody = document.getElementById('locations-tbody');

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="empty-state">暂无数据</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(item => {
        const trackingTail = item.tracking_number ? item.tracking_number.slice(-6) : '-';
        return `
        <tr>
            <td>
                <input type="checkbox" class="item-checkbox" value="${item.ledger_id}" />
            </td>
            <td>${escapeHtml(item.box_number || '-')}</td>
            <td>${escapeHtml(item.batch_name || '-')}</td>
            <td>${escapeHtml(item.tracking_number || '-')}</td>
            <td>${escapeHtml(item.warehouse_location || '-')}</td>
            <td>${escapeHtml(item.content_note || '-')}</td>
            <td>${item.quantity || 0}</td>
            <td>
                <span class="badge ${item.status === 'in_stock' ? 'in-stock' : 'shipped'}">
                    ${item.status === 'in_stock' ? '在库' : '已出库'}
                </span>
            </td>
            <td>${item.inbound_time || '-'}</td>
            <td>
                <button class="edit" onclick="showUpdateModal(${item.ledger_id}, '${escapeHtml(item.box_number || '')}', '${trackingTail}', '${escapeHtml(item.content_note || '')}', '${escapeHtml(item.warehouse_location || '')}')">
                    修改位置
                </button>
            </td>
        </tr>
        `;
    }).join('');
}

// 更新分页
function updatePagination(pagination) {
    totalPages = pagination.total_pages;
    const container = document.getElementById('pagination-container');

    container.innerHTML = `
        <button onclick="loadPackageLocations(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
            上一页
        </button>
        <span>第 ${currentPage} / ${totalPages} 页 (共 ${pagination.total} 条)</span>
        <button onclick="loadPackageLocations(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
            下一页
        </button>
    `;
}

// 显示单个修改模态框
function showUpdateModal(ledgerId, boxNumber, trackingTail, contentNote, currentLocation) {
    document.getElementById('update-ledger-id').value = ledgerId;
    document.getElementById('update-box-number').value = boxNumber;
    document.getElementById('update-tracking-tail').value = trackingTail || '无';
    document.getElementById('update-content-note').value = contentNote || '无';

    // 解析现有位置
    const parts = currentLocation.split('-');
    document.getElementById('update-row').value = parts[0] || '';
    document.getElementById('update-rack').value = parts[1] || '';
    document.getElementById('update-level').value = parts[2] || '';

    openModal('modal-update-single');
}

// 清空位置输入框
function clearLocationInputs(type) {
    if (type === 'update') {
        document.getElementById('update-row').value = '';
        document.getElementById('update-rack').value = '';
        document.getElementById('update-level').value = '';
    } else if (type === 'batch') {
        document.getElementById('batch-row').value = '';
        document.getElementById('batch-rack').value = '';
        document.getElementById('batch-level').value = '';
    }
}

// 提交单个修改
function submitSingleUpdate(event) {
    event.preventDefault();

    const ledgerId = document.getElementById('update-ledger-id').value;
    const row = document.getElementById('update-row').value.trim();
    const rack = document.getElementById('update-rack').value.trim();
    const level = document.getElementById('update-level').value.trim();

    let newLocation = '';

    // 检查是否全部为空（清除位置）
    if (!row && !rack && !level) {
        if (!confirm('确定要清除此箱子的货架位置信息吗？')) {
            return;
        }
        newLocation = ''; // 空字符串表示清除
    } else {
        // 必须全部填写
        if (!row || !rack || !level) {
            alert('请填写完整的位置信息（排号-架号-层号），或全部留空以清除位置');
            return;
        }
        newLocation = `${row.padStart(2, '0')}-${rack.padStart(2, '0')}-${level.padStart(2, '0')}`;
    }

    fetch('/mrs/ap/index.php?action=update_package_location', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            ledger_id: ledgerId,
            new_location: newLocation
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(newLocation ? '位置更新成功' : '位置已清除');
            closeModal('modal-update-single');
            loadPackageLocations(currentPage);
        } else {
            alert('更新失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('更新失败，请稍后重试');
    });
}

// 批量修改位置
function batchUpdateLocations() {
    const checked = document.querySelectorAll('.item-checkbox:checked');

    if (checked.length === 0) {
        alert('请先选择要修改的箱子');
        return;
    }

    document.getElementById('selected-count').textContent = checked.length;
    document.getElementById('batch-row').value = '';
    document.getElementById('batch-rack').value = '';
    document.getElementById('batch-level').value = '';

    openModal('modal-batch-update');
}

// 提交批量修改
function submitBatchUpdate(event) {
    event.preventDefault();

    const checked = document.querySelectorAll('.item-checkbox:checked');
    const ledgerIds = Array.from(checked).map(cb => parseInt(cb.value));

    const row = document.getElementById('batch-row').value.trim();
    const rack = document.getElementById('batch-rack').value.trim();
    const level = document.getElementById('batch-level').value.trim();

    let newLocation = '';
    let confirmMessage = '';

    // 检查是否全部为空（清除位置）
    if (!row && !rack && !level) {
        newLocation = ''; // 空字符串表示清除
        confirmMessage = `确定要清除 ${ledgerIds.length} 个箱子的货架位置信息吗？`;
    } else {
        // 必须全部填写
        if (!row || !rack || !level) {
            alert('请填写完整的位置信息（排号-架号-层号），或全部留空以清除位置');
            return;
        }
        newLocation = `${row.padStart(2, '0')}-${rack.padStart(2, '0')}-${level.padStart(2, '0')}`;
        confirmMessage = `确定要将 ${ledgerIds.length} 个箱子的位置更新为 ${newLocation} 吗？`;
    }

    if (!confirm(confirmMessage)) {
        return;
    }

    fetch('/mrs/ap/index.php?action=batch_update_locations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            ledger_ids: ledgerIds,
            new_location: newLocation
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = newLocation
                ? `成功更新 ${data.data.affected} 个箱子的位置为 ${newLocation}`
                : `成功清除 ${data.data.affected} 个箱子的位置`;
            alert(message);
            closeModal('modal-batch-update');
            loadPackageLocations(currentPage);
            document.getElementById('select-all').checked = false;
        } else {
            alert('更新失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('更新失败，请稍后重试');
    });
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

// 重置过滤
function resetFilters() {
    document.getElementById('filter-box-number').value = '';
    document.getElementById('filter-location').value = '';
    document.getElementById('filter-batch').value = '';
    document.getElementById('filter-status').value = '';
    loadPackageLocations(1);
}

// 导出数据
function exportData() {
    alert('导出功能开发中...');
}

// 打开模态框
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

// 关闭模态框
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 点击模态框背景关闭
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
});
