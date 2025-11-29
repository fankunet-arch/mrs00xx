import { appState, showAlert, escapeHtml, modal } from './core.js';
import { outboundAPI, skuAPI } from './api.js';
import { loadSkus } from './sku.js';
import * as Inventory from './inventory.js';

export async function addOutboundItemRow(item = null) {
  const tbody = document.getElementById('outbound-items-body');
  const index = tbody.children.length;
  const rowId = `row-${Date.now()}-${index}`;

  const tr = document.createElement('tr');
  tr.id = rowId;

  let skuOptions = '<option value="">选择物料</option>';
  if (appState.skus.length === 0) {
      await loadSkus();
  }

  appState.skus.forEach(s => {
      const selected = item && item.sku_id == s.sku_id ? 'selected' : '';
      skuOptions += `<option value="${s.sku_id}" ${selected} data-unit="${s.standard_unit}" data-case="${s.case_unit_name || ''}" data-spec="${s.case_to_standard_qty || 1}">${s.sku_name}</option>`;
  });

  const caseQty = item ? parseFloat(item.outbound_case_qty) : '';
  const singleQty = item ? parseFloat(item.outbound_single_qty) : '';
  const unit = item ? item.unit_name : '';
  const caseUnit = item ? item.case_unit_name : '';

  tr.innerHTML = `
    <td>
      <select class="form-control" name="items[${index}][sku_id]" data-action="onOutboundSkuChange" data-row-id="${rowId}" required>
        ${skuOptions}
      </select>
    </td>
    <td>
       <span class="inventory-display text-muted small">请选择...</span>
    </td>
    <td>
      <div class="input-group">
        <input type="number" step="0.01" class="form-control" name="items[${index}][outbound_case_qty]" value="${caseQty}" placeholder="箱数">
        <span class="input-addon case-unit-display">${caseUnit || '箱'}</span>
      </div>
    </td>
    <td>
      <div class="input-group">
        <input type="number" step="0.01" class="form-control" name="items[${index}][outbound_single_qty]" value="${singleQty}" placeholder="散数">
        <span class="input-addon unit-display">${unit || '个'}</span>
      </div>
    </td>
    <td>
      <button type="button" class="text danger" data-action="removeOutboundItemRow" data-row-id="${rowId}">X</button>
    </td>
  `;

  tbody.appendChild(tr);

  if (item) {
     const select = tr.querySelector('select');
     onOutboundSkuChange(select, rowId);
  }
}

export function removeOutboundItemRow(rowId) {
  const row = document.getElementById(rowId);
  if (row) row.remove();
}

export async function onOutboundSkuChange(select, rowId) {
  const row = document.getElementById(rowId);
  const option = select.options[select.selectedIndex];

  if (!option.value) return;

  const unit = option.dataset.unit;
  const caseUnit = option.dataset.case || '箱';
  const skuId = option.value;

  row.querySelector('.unit-display').textContent = unit;
  row.querySelector('.case-unit-display').textContent = caseUnit;

  const invDisplay = row.querySelector('.inventory-display');
  invDisplay.textContent = '查询中...';

  const result = await skuAPI.queryInventory(skuId);
  if (result.success) {
      invDisplay.textContent = `库存: ${result.data.display_text}`;
  } else {
      invDisplay.textContent = '查询失败';
  }
}

export async function saveOutbound(event) {
  const form = document.getElementById('form-outbound');
  const formData = new FormData(form);
  const data = {
      outbound_order_id: formData.get('outbound_order_id'),
      outbound_date: formData.get('outbound_date'),
      outbound_type: formData.get('outbound_type'),
      location_name: formData.get('location_name'),
      remark: formData.get('remark'),
      items: []
  };

  const rows = document.querySelectorAll('#outbound-items-body tr');
  rows.forEach((row, index) => {
      const skuId = row.querySelector(`select[name*="[sku_id]"]`).value;
      if (skuId) {
          data.items.push({
              sku_id: skuId,
              outbound_case_qty: row.querySelector(`input[name*="[outbound_case_qty]"]`).value || 0,
              outbound_single_qty: row.querySelector(`input[name*="[outbound_single_qty]"]`).value || 0
          });
      }
  });

  if (data.items.length === 0) {
      showAlert('warning', '请至少添加一个物料');
      return;
  }

  const result = await outboundAPI.saveOutbound(data);
  if (result.success) {
    showAlert('success', '出库单保存成功');
    modal.hide('modal-outbound');
    Inventory.loadInventoryList();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}
