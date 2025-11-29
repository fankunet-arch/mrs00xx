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
        <button class="text" data-action="viewBatch" data-batch-id="${batch.batch_id}">查看</button>
        <button class="secondary" data-action="showMergePage" data-batch-id="${batch.batch_id}">合并</button>
        <button class="text" data-action="editBatch" data-batch-id="${batch.batch_id}">编辑</button>
        <button class="text danger" data-action="deleteBatch" data-batch-id="${batch.batch_id}">删除</button>
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
    const batch = result.data.batch;
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
    const batch = result.data.batch;
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
    appState.mergeItems = result.data.items || []; // 保存到 appState
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

    // 渲染操作列：包含查看明细、输入框和确认按钮
    const actions = isConfirmed
      ? '<span class="badge success">✓ 已确认</span>'
      : `
        <div style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
          <button class="text" data-action="viewRawRecords" data-sku-id="${item.sku_id}">查看明细</button>
          <input type="number" id="case-${item.sku_id}" value="${item.confirmed_case || 0}" style="width: 70px;" placeholder="箱数" min="0" step="1" />
          <input type="number" id="single-${item.sku_id}" value="${item.confirmed_single || 0}" style="width: 70px;" placeholder="散件" min="0" step="1" />
          <button class="secondary" data-action="confirmItem" data-sku-id="${item.sku_id}">确认</button>
        </div>
      `;

    return `
      <tr class="${isConfirmed ? 'confirmed' : ''}">
        <td>${escapeHtml(item.sku_name)}</td>
        <td>${escapeHtml(item.category_name || '-')}</td>
        <td>${item.is_precise_item ? '精计' : '粗计'}</td>
        <td>${item.case_unit_name ? `1 ${item.case_unit_name} = ${parseFloat(item.case_to_standard_qty)} ${item.standard_unit}` : '—'}</td>
        <td><strong>${item.expected_qty || 0}</strong></td>
        <td>${escapeHtml(item.raw_summary || '-')}</td>
        <td><span class="pill">${escapeHtml(item.suggested_qty || '-')}</span></td>
        <td><span class="badge ${item.status === 'normal' ? 'success' : item.status === 'over' ? 'warning' : 'danger'}">${item.status_text || '正常'}</span></td>
        <td class="table-actions">${actions}</td>
      </tr>
    `;
  }).join('');
}

/**
 * 确认单个项目
 */
export async function confirmItem(skuId) {
  if (!appState.currentBatch) return;

  // 从 appState 中找到对应的 item
  const item = appState.mergeItems.find(i => i.sku_id === skuId);
  if (!item) {
    showAlert('danger', '数据同步错误，请刷新页面');
    return;
  }

  // 读取输入框的值
  const caseInput = document.getElementById(`case-${skuId}`);
  const singleInput = document.getElementById(`single-${skuId}`);

  if (!caseInput || !singleInput) {
    showAlert('danger', '输入框未找到，请刷新页面');
    return;
  }

  // 构建 payload
  const payload = {
    batch_id: appState.currentBatch.batch_id,
    close_batch: false, // 单个确认不关闭批次
    items: [{
      sku_id: item.sku_id,
      case_qty: parseFloat(caseInput.value) || 0,
      single_qty: parseFloat(singleInput.value) || 0,
      expected_qty: item.expected_qty || 0
    }]
  };

  const result = await batchAPI.confirmMerge(payload.batch_id, payload.items, payload.close_batch);

  if (result.success) {
    showAlert('success', '已确认');
    await showMergePage(appState.currentBatch.batch_id);
  } else {
    showAlert('danger', '确认失败: ' + result.message);
  }
}

/**
 * 确认全部
 */
export async function confirmAllMerge() {
  if (!appState.currentBatch) return;
  if (!confirm('确定要根据当前的输入值确认所有条目吗？')) return;

  // 收集所有项目的输入值
  const items = [];
  if (appState.mergeItems) {
    appState.mergeItems.forEach((item) => {
      const caseInput = document.getElementById(`case-${item.sku_id}`);
      const singleInput = document.getElementById(`single-${item.sku_id}`);

      // 只包含输入框存在的项目（未确认的项目）
      if (caseInput && singleInput) {
        items.push({
          sku_id: item.sku_id,
          case_qty: parseFloat(caseInput.value) || 0,
          single_qty: parseFloat(singleInput.value) || 0,
          expected_qty: item.expected_qty || 0
        });
      }
    });
  }

  if (items.length === 0) {
    showAlert('warning', '没有可确认的条目');
    return;
  }

  // 构建 payload
  const payload = {
    batch_id: appState.currentBatch.batch_id,
    close_batch: true, // 确认全部时关闭批次
    items: items
  };

  const result = await batchAPI.confirmMerge(payload.batch_id, payload.items, payload.close_batch);

  if (result.success) {
    showAlert('success', '全部确认成功');
    showPage('batches');
    loadBatches();
  } else {
    showAlert('danger', '批量确认失败: ' + result.message);
  }
}

/**
 * 查看原始记录
 */
export async function viewRawRecords(skuId) {
  if (!appState.currentBatch) {
    showAlert('danger', '批次信息未加载');
    return;
  }

  // 从 appState 中找到对应的 item
  const item = appState.mergeItems.find(i => i.sku_id === skuId);
  if (!item) {
    showAlert('danger', '数据同步错误，请刷新页面');
    return;
  }

  // 获取原始记录
  const result = await batchAPI.getRawRecords(appState.currentBatch.batch_id, skuId);

  if (!result.success) {
    showAlert('danger', '加载原始记录失败: ' + result.message);
    return;
  }

  // 填充模态框内容
  document.getElementById('raw-records-sku-name').textContent = item.sku_name || '-';
  document.getElementById('raw-records-batch-code').textContent = appState.currentBatch.batch_code || '-';

  const tbody = document.getElementById('raw-records-tbody');
  if (!result.data.records || result.data.records.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="empty">暂无原始记录</td></tr>';
  } else {
    tbody.innerHTML = result.data.records.map(record => `
      <tr>
        <td>${escapeHtml(record.recorded_at || '-')}</td>
        <td>${escapeHtml(record.operator_name || '-')}</td>
        <td><strong>${escapeHtml(record.qty || '0')}</strong></td>
        <td>${escapeHtml(record.unit_name || '-')}</td>
        <td>${escapeHtml(record.note || '-')}</td>
      </tr>
    `).join('');
  }

  // 显示模态框
  modal.show('modal-raw-records');
}
