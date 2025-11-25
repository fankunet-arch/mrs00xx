/**
 * MRS Backend - Compatibility Layer
 * 兼容层：将模块函数暴露到全局，使原有 onclick 处理器可以工作
 * 这是一个过渡性解决方案
 */

import { modal, showAlert, showPage, escapeHtml, appState, dom } from './core.js';
import { batchAPI, skuAPI, categoryAPI, inventoryAPI, outboundAPI, reportsAPI, systemAPI } from './api.js';
import * as Inventory from './inventory.js';

// SKU 导入提示词
const SKU_IMPORT_PROMPT = `你是一个数据提取助手。请根据以下图片内容，提取收货单中的物料信息。

输出格式要求：
每行一个物料，格式为：[品名] | [箱规] | [单位] | [品类]

注意事项：
1. 品名：保留完整名称
2. 箱规：提取数量和单位（如：500g/30包）
3. 单位：箱/盒/包等
4. 品类：根据品名推断（茶叶/包材/五金/其他）

示例输出：
90-700注塑细磨砂杯 | 500 | 箱 | 包材
茉莉银毫 | 500g/30包 | 箱 | 茶叶`;

/**
 * 获取状态文本
 */
function getStatusText(status) {
  const statusMap = {
    'draft': '草稿',
    'receiving': '收货中',
    'pending_merge': '待合并',
    'confirmed': '已确认',
    'posted': '已过账'
  };
  return statusMap[status] || status;
}

/**
 * 获取状态徽章样式类
 */
function getStatusBadgeClass(status) {
  const classMap = {
    'draft': 'secondary',
    'receiving': 'primary',
    'pending_merge': 'warning',
    'confirmed': 'success',
    'posted': 'success'
  };
  return classMap[status] || 'secondary';
}

// ========== 批次管理 ==========

async function loadBatches() {
  const filters = {
    search: document.getElementById('filter-search')?.value.trim() || '',
    date_start: document.getElementById('filter-date-start')?.value || '',
    date_end: document.getElementById('filter-date-end')?.value || '',
    status: document.getElementById('filter-status')?.value || ''
  };

  const result = await batchAPI.getBatches(filters);
  if (result.success) {
    appState.batches = result.data.batches || [];
    renderBatches();
  } else {
    showAlert('danger', '加载批次列表失败: ' + result.message);
  }
}

function renderBatches() {
  const tbody = document.querySelector('#page-batches tbody');
  if (!tbody) return;

  if (appState.batches.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">暂无批次数据</td></tr>';
    return;
  }

  tbody.innerHTML = appState.batches.map(batch => `
    <tr>
      <td>${escapeHtml(batch.batch_code)}</td>
      <td>${escapeHtml(batch.batch_date)}</td>
      <td>${escapeHtml(batch.location_name)}</td>
      <td><span class="badge ${getStatusBadgeClass(batch.batch_status)}">${getStatusText(batch.batch_status)}</span></td>
      <td>${escapeHtml(batch.remark || '-')}</td>
      <td class="table-actions">
        <button class="text" onclick="viewBatch(${batch.batch_id})">查看</button>
        <button class="secondary" onclick="showMergePage(${batch.batch_id})">合并</button>
        <button class="text" onclick="editBatch(${batch.batch_id})">编辑</button>
        <button class="text danger" onclick="deleteBatch(${batch.batch_id})">删除</button>
      </td>
    </tr>
  `).join('');
}

function showNewBatchModal() {
  document.getElementById('form-batch').reset();
  document.getElementById('batch-id').value = '';
  document.getElementById('modal-batch-title').textContent = '新建批次';

  const today = new Date().toISOString().split('T')[0];
  const batchCodeInput = document.getElementById('batch-code');
  batchCodeInput.value = '';
  batchCodeInput.placeholder = '系统自动生成';
  batchCodeInput.readOnly = false;
  document.getElementById('batch-date').value = today;

  modal.show('modal-batch');
}

async function saveBatch(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  const result = await batchAPI.saveBatch(data);
  if (result.success) {
    showAlert('success', '保存成功');
    modal.hide('modal-batch');
    loadBatches();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

async function editBatch(batchId) {
  const result = await batchAPI.getBatchDetail(batchId);
  if (result.success) {
    const batch = result.data;
    document.getElementById('batch-id').value = batch.batch_id;
    document.getElementById('batch-code').value = batch.batch_code;
    document.getElementById('batch-date').value = batch.batch_date;
    document.getElementById('batch-location').value = batch.location_name;
    document.getElementById('batch-remark').value = batch.remark || '';
    document.getElementById('batch-status').value = batch.batch_status;
    document.getElementById('modal-batch-title').textContent = '编辑批次';

    modal.show('modal-batch');
  } else {
    showAlert('danger', '加载批次详情失败: ' + result.message);
  }
}

async function deleteBatch(batchId) {
  if (!confirm('确定要删除该批次吗？')) return;

  const result = await batchAPI.deleteBatch(batchId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadBatches();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

async function viewBatch(batchId) {
  const result = await batchAPI.getBatchDetail(batchId);
  if (result.success) {
    const batch = result.data;
    const content = document.getElementById('batch-detail-content');
    content.innerHTML = `
      <div class="info-grid">
        <div><strong>批次编号：</strong>${escapeHtml(batch.batch_code)}</div>
        <div><strong>收货日期：</strong>${escapeHtml(batch.batch_date)}</div>
        <div><strong>地点：</strong>${escapeHtml(batch.location_name)}</div>
        <div><strong>状态：</strong><span class="badge ${getStatusBadgeClass(batch.batch_status)}">${getStatusText(batch.batch_status)}</span></div>
        <div class="full"><strong>备注：</strong>${escapeHtml(batch.remark || '-')}</div>
      </div>
    `;
    modal.show('modal-batch-detail');
  }
}

async function showMergePage(batchId) {
  const result = await batchAPI.getMergeData(batchId);
  if (result.success) {
    appState.currentBatch = { batch_id: batchId, ...result.data.batch };
    renderMergePage(result.data);
    showPage('merge');
  } else {
    showAlert('danger', '加载合并数据失败: ' + result.message);
  }
}

function renderMergePage(data) {
  // 渲染批次信息
  const batchInfo = document.getElementById('merge-batch-info');
  if (batchInfo) {
    batchInfo.innerHTML = `
      <div><strong>批次编号：</strong>${escapeHtml(data.batch.batch_code)}</div>
      <div><strong>收货日期：</strong>${escapeHtml(data.batch.batch_date)}</div>
      <div><strong>地点：</strong>${escapeHtml(data.batch.location_name)}</div>
      <div><strong>状态：</strong><span class="badge ${getStatusBadgeClass(data.batch.batch_status)}">${getStatusText(data.batch.batch_status)}</span></div>
    `;
  }

  // 渲染合并项
  const tbody = document.querySelector('#page-merge tbody');
  if (!tbody) return;

  if (!data.items || data.items.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty">暂无记录</td></tr>';
    return;
  }

  tbody.innerHTML = data.items.map(item => {
    const isConfirmed = item.merge_status === 'confirmed';
    const actions = isConfirmed
      ? '<span class="badge success">✓ 已确认</span>'
      : `<button class="success" onclick="confirmItem(${item.sku_id})">确认入库</button>`;

    return `
      <tr class="${isConfirmed ? 'confirmed' : ''}">
        <td>${escapeHtml(item.sku_name)}</td>
        <td>${escapeHtml(item.category_name || '-')}</td>
        <td>${item.is_precise_item ? '精计' : '粗计'}</td>
        <td>${escapeHtml(item.unit_rule || '-')}</td>
        <td><strong>${item.estimated_qty || 0}</strong></td>
        <td><button class="text info" onclick="viewRawRecords(${item.sku_id})">查看</button></td>
        <td>${item.suggestion || '-'}</td>
        <td>${isConfirmed ? '<span class="badge success">已确认</span>' : '<span class="badge secondary">待确认</span>'}</td>
        <td class="table-actions">${actions}</td>
      </tr>
    `;
  }).join('');
}

async function confirmItem(skuId) {
  const result = await batchAPI.confirmMerge(appState.currentBatch.batch_id, skuId);
  if (result.success) {
    showAlert('success', '确认成功');
    await showMergePage(appState.currentBatch.batch_id);
  } else {
    showAlert('danger', '确认失败: ' + result.message);
  }
}

async function confirmAllMerge() {
  if (!confirm('确认全部并入库？')) return;

  const result = await batchAPI.confirmMerge(appState.currentBatch.batch_id, null);
  if (result.success) {
    showAlert('success', '全部确认成功');
    showPage('batches');
    loadBatches();
  } else {
    showAlert('danger', '确认失败: ' + result.message);
  }
}

async function viewRawRecords(skuId) {
  // TODO: 实现查看原始记录功能
  showAlert('info', '原始记录查看功能待实现');
}

// ========== SKU 管理 ==========

async function loadSkus() {
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
      ? `<button class="text secondary" onclick="toggleSkuStatus(${sku.sku_id}, 'inactive')">下架</button>`
      : `<button class="text success" onclick="toggleSkuStatus(${sku.sku_id}, 'active')">上架</button>`;

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
          <button class="text primary" onclick="editSku(${sku.sku_id})">编辑</button>
          <button class="text danger" onclick="deleteSku(${sku.sku_id})">删除</button>
        </td>
      </tr>
    `;
  }).join('');
}

function showNewSkuModal() {
  document.getElementById('form-sku').reset();
  document.getElementById('sku-id').value = '';
  document.getElementById('modal-sku-title').textContent = '新增SKU';
  document.getElementById('sku-code').readOnly = false;
  loadCategoryOptions();
  modal.show('modal-sku');
}

async function saveSku(event) {
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

async function editSku(skuId) {
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

async function deleteSku(skuId) {
  if (!confirm('确定要删除该SKU吗？')) return;

  const result = await skuAPI.deleteSku(skuId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadSkus();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

async function toggleSkuStatus(skuId, newStatus) {
  const result = await skuAPI.updateSkuStatus(skuId, newStatus);
  if (result.success) {
    showAlert('success', newStatus === 'active' ? '已上架' : '已下架');
    loadSkus();
  } else {
    showAlert('danger', '操作失败: ' + result.message);
  }
}

function showImportSkuModal() {
  document.getElementById('import-sku-text').value = '';
  modal.show('modal-import-sku');
}

function showAiPromptHelper() {
  const textarea = document.getElementById('ai-prompt-text');
  if (textarea) {
    textarea.value = SKU_IMPORT_PROMPT;
  }
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.add('show');
  }
}

function closeAiPromptHelper() {
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.remove('show');
  }
}

function copyAiPrompt() {
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

async function importSkus() {
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

// ========== 品类管理 ==========

async function loadCategories() {
  const filters = {
    search: document.getElementById('category-filter-search')?.value.trim() || ''
  };
  const result = await categoryAPI.getCategories(filters);
  if (result.success) {
    appState.categories = result.data;
    renderCategories();
  } else {
    showAlert('danger', '加载品类列表失败: ' + result.message);
  }
}

function renderCategories() {
  const tbody = document.querySelector('#page-categories tbody');
  if (!tbody) return;

  if (appState.categories.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="empty">暂无品类数据</td></tr>';
    return;
  }

  tbody.innerHTML = appState.categories.map(category => `
    <tr>
      <td>${escapeHtml(category.category_name)}</td>
      <td>${escapeHtml(category.category_code || '-')}</td>
      <td>${new Date(category.created_at).toLocaleString('zh-CN')}</td>
      <td class="table-actions">
        <button class="text" onclick="editCategory(${category.category_id})">编辑</button>
        <button class="text danger" onclick="deleteCategory(${category.category_id})">删除</button>
      </td>
    </tr>
  `).join('');
}

function showNewCategoryModal() {
  document.getElementById('form-category').reset();
  document.getElementById('category-id').value = '';
  document.getElementById('modal-category-title').textContent = '新增品类';
  modal.show('modal-category');
}

async function saveCategory(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  const result = await categoryAPI.saveCategory(data);
  if (result.success) {
    showAlert('success', '保存成功');
    modal.hide('modal-category');
    loadCategories();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

async function editCategory(categoryId) {
  const category = appState.categories.find(c => c.category_id === categoryId);
  if (!category) return;

  document.getElementById('category-id').value = category.category_id;
  document.getElementById('category-name').value = category.category_name;
  document.getElementById('category-code').value = category.category_code || '';
  document.getElementById('modal-category-title').textContent = '编辑品类';

  modal.show('modal-category');
}

async function deleteCategory(categoryId) {
  if (!confirm('确定要删除该品类吗？')) return;

  const result = await categoryAPI.deleteCategory(categoryId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadCategories();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

async function loadCategoryOptions() {
  const result = await categoryAPI.getCategories();
  if (result.success) {
    const select = document.getElementById('sku-category');
    if (select) {
      const currentVal = select.value;
      select.innerHTML = '<option value="">请选择</option>' +
        result.data.map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
      if (currentVal) {
        select.value = currentVal;
      }
    }
  }
}

// ========== 报表管理 ==========

async function loadReports() {
  showAlert('info', '报表功能待实现');
}

async function exportReport() {
  showAlert('info', '导出功能待实现');
}

// ========== 系统维护 ==========

async function loadSystemStatus() {
  const result = await systemAPI.getSystemStatus();
  const container = document.getElementById('system-status-container');
  if (!container) return;

  if (result.success) {
    container.innerHTML = `
      <div class="system-status">
        <p><strong>系统状态：</strong><span class="badge success">正常</span></p>
        <p><strong>数据库：</strong>${result.data.database || '未知'}</p>
        <p><strong>版本：</strong>${result.data.version || '1.0.0'}</p>
      </div>
    `;
  } else {
    container.innerHTML = `<p class="text-danger">系统检查失败: ${result.message}</p>`;
  }
}

async function fixSystem() {
  if (!confirm('确定要执行系统修复吗？')) return;

  const result = await systemAPI.fixSystem();
  if (result.success) {
    showAlert('success', '修复完成');
    loadSystemStatus();
  } else {
    showAlert('danger', '修复失败: ' + result.message);
  }
}

// ========== 导出到全局 ==========

window.showPage = showPage;
window.modal = modal;
window.showAlert = showAlert;

// 批次
window.loadBatches = loadBatches;
window.showNewBatchModal = showNewBatchModal;
window.saveBatch = saveBatch;
window.editBatch = editBatch;
window.deleteBatch = deleteBatch;
window.viewBatch = viewBatch;
window.showMergePage = showMergePage;
window.confirmItem = confirmItem;
window.confirmAllMerge = confirmAllMerge;
window.viewRawRecords = viewRawRecords;

// SKU
window.loadSkus = loadSkus;
window.showNewSkuModal = showNewSkuModal;
window.saveSku = saveSku;
window.editSku = editSku;
window.deleteSku = deleteSku;
window.toggleSkuStatus = toggleSkuStatus;
window.showImportSkuModal = showImportSkuModal;
window.showAiPromptHelper = showAiPromptHelper;
window.closeAiPromptHelper = closeAiPromptHelper;
window.copyAiPrompt = copyAiPrompt;
window.importSkus = importSkus;

// 品类
window.loadCategories = loadCategories;
window.showNewCategoryModal = showNewCategoryModal;
window.saveCategory = saveCategory;
window.editCategory = editCategory;
window.deleteCategory = deleteCategory;

// 库存（从 inventory 模块暴露）
window.loadInventoryList = Inventory.loadInventoryList;
window.refreshInventory = Inventory.refreshInventory;
window.viewSkuHistory = Inventory.viewSkuHistory;
window.showQuickOutboundModal = Inventory.showQuickOutboundModal;
window.saveQuickOutbound = Inventory.saveQuickOutbound;
window.showInventoryAdjustModal = Inventory.showInventoryAdjustModal;
window.saveInventoryAdjustment = Inventory.saveInventoryAdjustment;

// 报表
window.loadReports = loadReports;
window.exportReport = exportReport;

// 系统
window.loadSystemStatus = loadSystemStatus;
window.fixSystem = fixSystem;
