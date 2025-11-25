/**
 * MRS Backend - Batch Management Module
 * 批次管理模块
 */

import { modal, showAlert, showPage, escapeHtml, appState } from './core.js';
import { batchAPI } from './api.js';
import { getStatusText, getStatusBadgeClass } from './utils.js';

/**
 * 加载批次列表
 */
export async function loadBatches() {
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

/**
 * 渲染批次列表
 */
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

/**
 * 显示新建批次模态框
 */
export function showNewBatchModal() {
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

/**
 * 保存批次
 */
export async function saveBatch(event) {
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

/**
 * 编辑批次
 */
export async function editBatch(batchId) {
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

/**
 * 删除批次
 */
export async function deleteBatch(batchId) {
  if (!confirm('确定要删除该批次吗？')) return;

  const result = await batchAPI.deleteBatch(batchId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadBatches();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

/**
 * 查看批次详情
 */
export async function viewBatch(batchId) {
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

/**
 * 显示合并页面
 */
export async function showMergePage(batchId) {
  const result = await batchAPI.getMergeData(batchId);
  if (result.success) {
    appState.currentBatch = { batch_id: batchId, ...result.data.batch };
    renderMergePage(result.data);
    showPage('merge');
  } else {
    showAlert('danger', '加载合并数据失败: ' + result.message);
  }
}

/**
 * 渲染合并页面
 */
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

/**
 * 确认单个项目
 */
export async function confirmItem(skuId) {
  const result = await batchAPI.confirmMerge(appState.currentBatch.batch_id, skuId);
  if (result.success) {
    showAlert('success', '确认成功');
    await showMergePage(appState.currentBatch.batch_id);
  } else {
    showAlert('danger', '确认失败: ' + result.message);
  }
}

/**
 * 确认全部
 */
export async function confirmAllMerge() {
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

/**
 * 查看原始记录
 */
export async function viewRawRecords(skuId) {
  // TODO: 实现查看原始记录功能
  showAlert('info', '原始记录查看功能待实现');
}
