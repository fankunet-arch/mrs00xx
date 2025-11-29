/**
 * MRS Backend - Inventory Module
 * 库存管理功能
 */

import { appState, showAlert, escapeHtml, modal } from './core.js';
import { inventoryAPI, skuAPI } from './api.js';

/**
 * 加载库存列表
 */
export async function loadInventoryList() {
  const filters = {
    search: document.getElementById('inventory-filter-search')?.value.trim() || '',
    category_id: document.getElementById('inventory-filter-category')?.value || ''
  };

  const result = await inventoryAPI.getInventoryList(filters);

  if (result.success) {
    appState.inventory = result.data.inventory;
    renderInventoryList();
  } else {
    showAlert('danger', '加载库存列表失败: ' + result.message);
  }
}

/**
 * 渲染库存列表
 */
function renderInventoryList() {
  const tbody = document.querySelector('#page-inventory tbody');
  if (!tbody) return;

  if (!appState.inventory || appState.inventory.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty">暂无库存数据</td></tr>';
    return;
  }

  tbody.innerHTML = appState.inventory.map(item => {
    let inventoryClass = '';
    if (item.current_inventory <= 0) {
      inventoryClass = 'text-danger';
    } else if (item.current_inventory < 10) {
      inventoryClass = 'text-warning';
    } else {
      inventoryClass = 'text-success';
    }

    return `
      <tr>
        <td>${escapeHtml(item.sku_name)}</td>
        <td>${escapeHtml(item.category_name)}</td>
        <td>${escapeHtml(item.brand_name)}</td>
        <td>${escapeHtml(item.standard_unit)}</td>
        <td class="${inventoryClass}" style="font-weight: bold;">${escapeHtml(item.display_text)}</td>
        <td>${item.total_inbound}</td>
        <td>${item.total_outbound}</td>
        <td>${item.total_adjustment}</td>
        <td class="table-actions">
          <button class="text info" data-action="viewHistory" data-sku-id="${item.sku_id}">📜 履历</button>
          <button class="text danger" data-action="quickOutbound" data-sku-id="${item.sku_id}">出库</button>
          <button class="text success" data-action="inventoryAdjust" data-sku-id="${item.sku_id}">盘点</button>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * 刷新库存
 */
export async function refreshInventory() {
  await loadInventoryList();
  showAlert('success', '库存数据已刷新');
}

/**
 * 显示极速出库模态框
 */
export async function showQuickOutboundModal(skuId) {
  try {
    const sku = appState.inventory.find(s => s.sku_id === skuId) ||
                 appState.skus.find(s => s.sku_id === skuId);

    if (!sku) {
      showAlert('danger', 'SKU不存在');
      return;
    }

    const inventoryResult = await inventoryAPI.queryInventory(skuId);
    if (!inventoryResult.success) {
      showAlert('danger', '查询库存失败: ' + inventoryResult.message);
      return;
    }

    document.getElementById('quick-outbound-sku-id').value = skuId;
    document.getElementById('quick-outbound-sku-name').textContent = sku.sku_name;
    document.getElementById('quick-outbound-inventory').textContent = inventoryResult.data.display_text || '0';
    document.getElementById('quick-outbound-qty').value = '';
    document.getElementById('quick-outbound-location').value = '门店出库';
    document.getElementById('quick-outbound-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('quick-outbound-remark').value = '';

    modal.show('modal-quick-outbound');
  } catch (error) {
    console.error('显示出库模态框失败:', error);
    showAlert('danger', '系统错误');
  }
}

/**
 * 保存极速出库
 */
export async function saveQuickOutbound(formData) {
  const data = {
    sku_id: parseInt(formData.get('sku_id')),
    qty: parseFloat(formData.get('qty')),
    location_name: formData.get('location_name'),
    outbound_date: formData.get('outbound_date'),
    remark: formData.get('remark') || '极速出库'
  };

  if (!data.sku_id || !data.qty || !data.location_name || !data.outbound_date) {
    showAlert('danger', '请填写所有必填项');
    return false;
  }

  if (data.qty <= 0) {
    showAlert('danger', '出库数量必须大于0');
    return false;
  }

  try {
    const result = await inventoryAPI.quickOutbound(data);

    if (result.success) {
      showAlert('success', '出库成功');
      modal.hide('modal-quick-outbound');
      await loadInventoryList();
      return true;
    } else {
      showAlert('danger', '出库失败: ' + result.message);
      return false;
    }
  } catch (error) {
    console.error('出库失败:', error);
    showAlert('danger', '系统错误');
    return false;
  }
}

/**
 * 显示库存盘点/调整模态框
 */
export async function showInventoryAdjustModal(skuId) {
  try {
    const sku = appState.inventory.find(s => s.sku_id === skuId) ||
                 appState.skus.find(s => s.sku_id === skuId);

    if (!sku) {
      showAlert('danger', 'SKU不存在');
      return;
    }

    const inventoryResult = await inventoryAPI.queryInventory(skuId);
    if (!inventoryResult.success) {
      showAlert('danger', '查询库存失败: ' + inventoryResult.message);
      return;
    }

    const currentInventory = inventoryResult.data.current_inventory || 0;

    document.getElementById('adjust-sku-id').value = skuId;
    document.getElementById('adjust-sku-name').textContent = sku.sku_name;
    document.getElementById('adjust-system-inventory').textContent = inventoryResult.data.display_text || '0';
    document.getElementById('adjust-current-qty').value = currentInventory;
    document.getElementById('adjust-delta').textContent = '-';
    document.getElementById('adjust-reason').value = '';

    // 监听数量变化，实时计算差异
    const qtyInput = document.getElementById('adjust-current-qty');
    const deltaDisplay = document.getElementById('adjust-delta');

    qtyInput.oninput = function() {
      const newQty = parseFloat(this.value) || 0;
      const delta = newQty - currentInventory;

      if (delta === 0) {
        deltaDisplay.textContent = '无差异';
        deltaDisplay.style.color = '#666';
      } else if (delta > 0) {
        deltaDisplay.textContent = `+${delta} (盘盈)`;
        deltaDisplay.style.color = 'green';
      } else {
        deltaDisplay.textContent = `${delta} (盘亏)`;
        deltaDisplay.style.color = 'red';
      }
    };

    modal.show('modal-inventory-adjust');
  } catch (error) {
    console.error('显示盘点模态框失败:', error);
    showAlert('danger', '系统错误');
  }
}

/**
 * 保存库存调整
 */
export async function saveInventoryAdjustment(formData) {
  const data = {
    sku_id: parseInt(formData.get('sku_id')),
    current_qty: parseFloat(formData.get('current_qty')),
    reason: formData.get('reason')
  };

  if (!data.sku_id || data.current_qty === undefined || !data.reason) {
    showAlert('danger', '请填写所有必填项');
    return false;
  }

  if (data.current_qty < 0) {
    showAlert('danger', '库存数量不能为负数');
    return false;
  }

  try {
    const result = await inventoryAPI.adjustInventory(data);

    if (result.success) {
      if (result.data.delta === 0) {
        showAlert('info', result.message || '库存数量一致，无需调整');
      } else {
        showAlert('success', `库存调整成功，差异: ${result.data.delta > 0 ? '+' : ''}${result.data.delta}`);
      }
      modal.hide('modal-inventory-adjust');
      await loadInventoryList();
      return true;
    } else {
      showAlert('danger', '库存调整失败: ' + result.message);
      return false;
    }
  } catch (error) {
    console.error('库存调整失败:', error);
    showAlert('danger', '系统错误');
    return false;
  }
}

/**
 * 查看SKU履历
 */
export async function viewSkuHistory(skuId) {
  try {
    const sku = appState.inventory.find(s => s.sku_id === skuId) ||
                 appState.skus.find(s => s.sku_id === skuId);

    if (!sku) {
      showAlert('danger', 'SKU不存在');
      return;
    }

    document.getElementById('history-sku-name').textContent = sku.sku_name;
    document.getElementById('history-tbody').innerHTML = '<tr><td colspan="5" class="loading">加载中...</td></tr>';
    modal.show('modal-sku-history');

    const result = await skuAPI.getSkuHistory(skuId);

    if (!result.success) {
      document.getElementById('history-tbody').innerHTML =
        `<tr><td colspan="5" class="empty">加载失败: ${result.message}</td></tr>`;
      return;
    }

    const history = result.data.history || [];

    if (history.length === 0) {
      document.getElementById('history-tbody').innerHTML =
        '<tr><td colspan="5" class="empty">暂无历史记录</td></tr>';
      return;
    }

    const tbody = document.getElementById('history-tbody');
    tbody.innerHTML = history.map(record => {
      let qtyClass = '';
      if (record.type === '入库') {
        qtyClass = 'text-success';
      } else if (record.type === '出库') {
        qtyClass = 'text-danger';
      } else if (record.type === '盘点调整') {
        qtyClass = record.qty > 0 ? 'text-success' : 'text-danger';
      }

      return `
        <tr>
          <td>${escapeHtml(record.date)}</td>
          <td><span class="badge ${record.type === '入库' ? 'success' : record.type === '出库' ? 'danger' : 'info'}">${escapeHtml(record.type)}</span></td>
          <td>${escapeHtml(record.code)}</td>
          <td class="${qtyClass}" style="font-weight: bold;">${escapeHtml(record.qty_display)}</td>
          <td>${escapeHtml(record.location)} ${record.remark !== '-' ? '/ ' + escapeHtml(record.remark) : ''}</td>
        </tr>
      `;
    }).join('');

  } catch (error) {
    console.error('查看SKU履历失败:', error);
    showAlert('danger', '系统错误');
  }
}
