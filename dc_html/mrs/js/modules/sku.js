/**
 * MRS Backend - SKU Management Module
 * SKU 管理模块
 */

import { modal, showAlert, escapeHtml, appState } from './core.js';
import { skuAPI, categoryAPI } from './api.js';
import { SKU_IMPORT_PROMPT } from './utils.js';

/**
 * 加载SKU列表
 */
export async function loadSkus() {
  const filters = {
    search: document.getElementById('catalog-filter-search')?.value.trim() || '',
    category_id: document.getElementById('catalog-filter-category')?.value || '',
    is_precise_item: document.getElementById('catalog-filter-type')?.value || ''
  };

  const result = await skuAPI.getSkus(filters);
  if (result.success) {
    appState.skus = result.data.skus;
    renderSkus();
  } else {
    showAlert('danger', '加载SKU列表失败: ' + result.message);
  }
}

/**
 * 渲染SKU列表
 */
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
      ? `<button class="text secondary" data-action="toggleSkuStatus" data-sku-id="${sku.sku_id}" data-status="inactive">下架</button>`
      : `<button class="text success" data-action="toggleSkuStatus" data-sku-id="${sku.sku_id}" data-status="active">上架</button>`;

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

/**
 * 显示新建SKU模态框
 */
export function showNewSkuModal() {
  document.getElementById('form-sku').reset();
  document.getElementById('sku-id').value = '';
  document.getElementById('modal-sku-title').textContent = '新增SKU';
  document.getElementById('sku-code').readOnly = false;
  loadCategoryOptions();
  modal.show('modal-sku');
}

/**
 * 保存SKU
 */
export async function saveSku(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  const result = await skuAPI.saveSku(data);
  if (result.success) {
    showAlert('success', '保存成功');
    modal.hide('modal-sku');
    loadSkus();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 编辑SKU
 */
export async function editSku(skuId) {
  const sku = appState.skus.find(s => s.sku_id === skuId);
  if (!sku) return;

  document.getElementById('sku-id').value = sku.sku_id;
  document.getElementById('sku-name').value = sku.sku_name;
  document.getElementById('sku-brand').value = sku.brand_name;
  document.getElementById('sku-code').value = sku.sku_code;
  document.getElementById('sku-type').value = sku.is_precise_item ? '1' : '0';
  document.getElementById('sku-unit').value = sku.standard_unit;
  document.getElementById('sku-case-unit').value = sku.case_unit_name || '';
  document.getElementById('sku-case-qty').value = sku.case_to_standard_qty || '';
  document.getElementById('sku-note').value = sku.note || '';
  document.getElementById('modal-sku-title').textContent = '编辑SKU';

  await loadCategoryOptions();
  document.getElementById('sku-category').value = sku.category_id;

  modal.show('modal-sku');
}

/**
 * 删除SKU
 */
export async function deleteSku(skuId) {
  if (!confirm('确定要删除该SKU吗？')) return;

  const result = await skuAPI.deleteSku(skuId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadSkus();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

/**
 * 切换SKU状态
 */
export async function toggleSkuStatus(skuId, newStatus) {
  const result = await skuAPI.updateSkuStatus(skuId, newStatus);
  if (result.success) {
    showAlert('success', newStatus === 'active' ? '已上架' : '已下架');
    loadSkus();
  } else {
    showAlert('danger', '操作失败: ' + result.message);
  }
}

/**
 * 显示批量导入模态框
 */
export function showImportSkuModal() {
  document.getElementById('import-sku-text').value = '';
  modal.show('modal-import-sku');
}

/**
 * 显示AI提示词助手
 */
export function showAiPromptHelper() {
  const textarea = document.getElementById('ai-prompt-text');
  if (textarea) {
    textarea.value = SKU_IMPORT_PROMPT;
  }
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.add('show');
  }
}

/**
 * 关闭AI提示词助手
 */
export function closeAiPromptHelper() {
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.remove('show');
  }
}

/**
 * 复制AI提示词
 */
export function copyAiPrompt() {
  const textarea = document.getElementById('ai-prompt-text');
  if (!textarea) return;

  textarea.select();
  textarea.setSelectionRange(0, 99999);

  if (navigator.clipboard) {
    navigator.clipboard.writeText(textarea.value).then(() => {
      showAlert('success', '复制成功');
    }).catch(() => {
      fallbackCopy(textarea);
    });
  } else {
    fallbackCopy(textarea);
  }
}

/**
 * 降级复制策略
 */
function fallbackCopy(textarea) {
  try {
    const successful = document.execCommand('copy');
    if (successful) {
      showAlert('success', '复制成功');
    } else {
      showAlert('warning', '复制失败，请手动复制');
    }
  } catch (err) {
    showAlert('danger', '浏览器不支持自动复制');
  }
}

/**
 * 执行批量导入
 */
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

/**
 * 加载品类选项到下拉框
 */
async function loadCategoryOptions() {
  const result = await categoryAPI.getCategories();
  if (result.success) {
    const select = document.getElementById('sku-category');
    if (select) {
      const currentVal = select.value;
      select.innerHTML = '<option value="">请选择</option>' +
        result.data.categories.map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
      if (currentVal) {
        select.value = currentVal;
      }
    }
  }
}
