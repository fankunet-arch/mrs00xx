import { appState, showAlert, escapeHtml, modal } from './core.js';
import { skuAPI, categoryAPI } from './api.js';
import {SKU_IMPORT_PROMPT} from './main.js';

export async function loadSkus() {
  const filters = {
    search: document.getElementById('catalog-filter-search')?.value.trim() || '',
    category_id: document.getElementById('catalog-filter-category')?.value || '',
    is_precise_item: document.getElementById('catalog-filter-type')?.value || ''
  };

  const result = await skuAPI.getSkus(filters);

  if (result.success) {
    appState.skus = result.data;
    renderSkus();
  } else {
    showAlert('danger', '加载SKU列表失败: ' + result.message);
  }
}

function renderSkus() {
  const tbody = document.querySelector('#page-catalog tbody');
  if (!tbody) return;

  if (appState.skus.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty">暂无SKU数据</td></tr>';
    return;
  }

  tbody.innerHTML = appState.skus.map(sku => {
    const formattedQty = sku.case_to_standard_qty ? parseFloat(sku.case_to_standard_qty) : '';

    const unitRule = sku.case_unit_name
      ? `1 ${sku.case_unit_name} = ${formattedQty} ${sku.standard_unit}`
      : '—';

    const status = sku.status || 'active';
    const statusBadge = status === 'active'
      ? '<span class="badge success">上架</span>'
      : '<span class="badge secondary">下架</span>';

    const statusAction = status === 'active'
      ? `<button class="text secondary" data-action="toggleSkuStatus" data-sku-id="${sku.sku_id}" data-status="inactive" title="设为下架">下架</button>`
      : `<button class="text success" data-action="toggleSkuStatus" data-sku-id="${sku.sku_id}" data-status="active" title="设为上架">上架</button>`;

    return `
      <tr>
        <td>${escapeHtml(sku.sku_name)}</td>
        <td>${escapeHtml(sku.category_name || '-')}</td>
        <td>${escapeHtml(sku.brand_name)}</td>
        <td>${sku.is_precise_item ? '精计' : '粗计'}</td>
        <td>${escapeHtml(sku.standard_unit)}</td>
        <td>${escapeHtml(unitRule)}</td>
        <td>${statusBadge}</td>
        <td class="table-actions">
          ${statusAction}
          <button class="text primary" data-action="editSku" data-sku-id="${sku.sku_id}">编辑</button>
          <button class="text danger" data-action="deleteSku" data-sku-id="${sku.sku_id}">删除</button>
        </td>
      </tr>
    `;
  }).join('');
}

export function showNewSkuModal() {
  document.getElementById('form-sku').reset();
  document.getElementById('sku-id').value = ''; // 清除ID
  document.getElementById('modal-sku-title').textContent = '新增SKU';
  document.getElementById('sku-code').readOnly = false; // 允许输入编码
  loadCategoryOptions();
  modal.show('modal-sku');
}

export async function saveSku(event) {
  const form = document.getElementById('form-sku');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  const result = await skuAPI.saveSku(data);
  if (result.success) {
    showAlert('success', 'SKU保存成功');
    modal.hide('modal-sku');
    loadSkus();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

export async function editSku(skuId) {
  await loadCategoryOptions();

  const result = await skuAPI.getSkuDetail(skuId);

  if (result.success) {
    const sku = result.data;

    document.getElementById('sku-id').value = sku.sku_id;
    document.getElementById('sku-name').value = sku.sku_name;
    document.getElementById('sku-category').value = sku.category_id;
    document.getElementById('sku-brand').value = sku.brand_name;
    document.getElementById('sku-code').value = sku.sku_code;
    document.getElementById('sku-type').value = sku.is_precise_item;
    document.getElementById('sku-unit').value = sku.standard_unit;
    document.getElementById('sku-case-unit').value = sku.case_unit_name || '';
    document.getElementById('sku-case-qty').value = sku.case_to_standard_qty || '';
    document.getElementById('sku-note').value = sku.note || '';

    document.getElementById('modal-sku-title').textContent = '编辑SKU';
    modal.show('modal-sku');
  } else {
    showAlert('danger', '获取SKU信息失败: ' + result.message);
  }
}

export async function deleteSku(skuId) {
  if (!confirm('确定要删除这个SKU吗?')) {
    return;
  }

  const result = await skuAPI.deleteSku(skuId);
  if (result.success) {
    showAlert('success', 'SKU删除成功');
    loadSkus();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

export async function toggleSkuStatus(skuId, newStatus) {
  if (!confirm(`确定要将此SKU设为${newStatus === 'active' ? '上架' : '下架'}状态吗？`)) {
    return;
  }

  try {
    const result = await skuAPI.updateSkuStatus(skuId, newStatus);

    if (result.success) {
      showAlert('success', `SKU状态已更新为${newStatus === 'active' ? '上架' : '下架'}`);
      await loadSkus();
    } else {
      showAlert('danger', '更新状态失败: ' + result.message);
    }
  } catch (error) {
    console.error('更新SKU状态失败:', error);
    showAlert('danger', '系统错误');
  }
}

export function showImportSkuModal() {
  document.getElementById('import-sku-text').value = '';
  console.log('Use this prompt for AI:', SKU_IMPORT_PROMPT);
  modal.show('modal-import-sku');
}

export async function importSkus() {
  const textarea = document.getElementById('import-sku-text');
  const text = textarea.value.trim();

  if (!text) {
    showAlert('warning', '请粘贴内容');
    return;
  }

  const result = await skuAPI.importSkusText(text);
  if (result.success) {
    showAlert('success', result.message);
    modal.hide('modal-import-sku');
    loadSkus();
  } else {
    showAlert('danger', '导入失败: ' + result.message);
  }
}

export async function loadCategoryOptions() {
  const result = await categoryAPI.getCategories();
  if (result.success) {
    const select = document.getElementById('sku-category');
    select.innerHTML = '<option value="">请选择</option>' +
      result.data.map(cat => `<option value="${cat.category_id}">${cat.category_name}</option>`).join('');
  }
}
