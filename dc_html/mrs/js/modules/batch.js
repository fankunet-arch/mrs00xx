import { appState, showAlert, escapeHtml, modal, showPage } from './core.js';
import { batchAPI } from './api.js';

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

export function getStatusText(status) {
  const statusMap = {
    'draft': '草稿',
    'receiving': '收货中',
    'pending_merge': '待合并',
    'confirmed': '已确认',
    'posted': '已过账'
  };
  return statusMap[status] || status;
}

export function getStatusBadgeClass(status) {
  const classMap = {
    'draft': 'info',
    'receiving': 'info',
    'pending_merge': 'warning',
    'confirmed': 'success',
    'posted': 'success'
  };
  return classMap[status] || 'info';
}

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

export async function saveBatch(event) {
  const form = document.getElementById('form-batch');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  const result = await batchAPI.saveBatch(data);
  if (result.success) {
    showAlert('success', '批次保存成功');
    modal.hide('modal-batch');
    loadBatches();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

export async function viewBatch(batchId) {
  const result = await batchAPI.getBatchDetail(batchId);
  if (result.success) {
    const data = result.data;
    const batch = data.batch;
    const stats = data.stats;

    const content = `
      <div class="detail-grid">
        <div class="detail-item"><label>批次编号:</label> <span>${escapeHtml(batch.batch_code)}</span></div>
        <div class="detail-item"><label>收货日期:</label> <span>${escapeHtml(batch.batch_date)}</span></div>
        <div class="detail-item"><label>地点/门店:</label> <span>${escapeHtml(batch.location_name)}</span></div>
        <div class="detail-item"><label>状态:</label> <span class="badge ${getStatusBadgeClass(batch.batch_status)}">${getStatusText(batch.batch_status)}</span></div>
        <div class="detail-item full"><label>备注:</label> <span>${escapeHtml(batch.remark || '-')}</span></div>
      </div>
      <hr class="my-4" />
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">${stats.raw_records_count}</div>
          <div class="stat-label">原始记录</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${stats.expected_items_count}</div>
          <div class="stat-label">预计清单</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${stats.confirmed_items_count}</div>
          <div class="stat-label">确认条目</div>
        </div>
      </div>
      <div class="mt-4 text-center">
        <p class="text-muted small">创建时间: ${new Date(batch.created_at).toLocaleString('zh-CN')} | 更新时间: ${new Date(batch.updated_at).toLocaleString('zh-CN')}</p>
      </div>
    `;

    document.getElementById('batch-detail-content').innerHTML = content;
    modal.show('modal-batch-detail');
  } else {
    showAlert('danger', '获取详情失败: ' + result.message);
  }
}

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
    showAlert('danger', '获取批次信息失败: ' + result.message);
  }
}

export async function deleteBatch(batchId) {
  if (!confirm('确定要删除这个批次吗?此操作不可撤销!')) {
    return;
  }

  const result = await batchAPI.deleteBatch(batchId);
  if (result.success) {
    showAlert('success', '批次删除成功');
    loadBatches();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

export async function showMergePage(batchId) {
  appState.currentBatch = appState.batches.find(b => b.batch_id === batchId);

  const result = await batchAPI.getMergeData(batchId);
  if (result.success) {
    renderMergePage(result.data);
    showPage('merge');
  } else {
    showAlert('danger', '加载合并数据失败: ' + result.message);
  }
}

function renderMergePage(data) {
  const infoContainer = document.querySelector('#page-merge .columns');
  if (infoContainer && appState.currentBatch) {
    infoContainer.innerHTML = `
      <div>
        <div class="muted">批次编号</div>
        <div class="status-label">${escapeHtml(appState.currentBatch.batch_code)}</div>
      </div>
      <div>
        <div class="muted">收货日期</div>
        <div class="status-label">${escapeHtml(appState.currentBatch.batch_date)}</div>
      </div>
      <div>
        <div class="muted">地点</div>
        <div class="status-label">${escapeHtml(appState.currentBatch.location_name)}</div>
      </div>
      <div>
        <div class="muted">状态</div>
        <div class="status-label"><span class="badge ${getStatusBadgeClass(appState.currentBatch.batch_status)}">${getStatusText(appState.currentBatch.batch_status)}</span></div>
      </div>
      <div>
        <div class="muted">备注</div>
        <div class="status-label">${escapeHtml(appState.currentBatch.remark || '-')}</div>
      </div>
    `;
  }

  const tbody = document.querySelector('#page-merge tbody');
  if (tbody && data.items) {
    tbody.innerHTML = data.items.map((item, index) => `
      <tr>
        <td>${escapeHtml(item.sku_name)}</td>
        <td>${escapeHtml(item.category_name || '-')}</td>
        <td>${item.is_precise_item ? '精计' : '粗计'}</td>
        <td>${item.case_unit_name ? `1 ${item.case_unit_name} = ${parseFloat(item.case_to_standard_qty)} ${item.standard_unit}` : '—'}</td>
        <td>${item.expected_qty || '-'}</td>
        <td>${escapeHtml(item.raw_summary || '-')}</td>
        <td><span class="pill">${escapeHtml(item.suggested_qty || '-')}</span></td>
        <td><span class="badge ${item.status === 'normal' ? 'success' : item.status === 'over' ? 'warning' : 'danger'}">${item.status_text || '正常'}</span></td>
        <td>
          <div class="table-actions">
            <button class="text" data-action="viewRawRecords" data-sku-id="${item.sku_id}">查看明细</button>
            <input type="number" id="case-${item.sku_id}" value="${item.confirmed_case || 0}" style="width: 70px;" placeholder="箱数" />
            <input type="number" id="single-${item.sku_id}" value="${item.confirmed_single || 0}" style="width: 70px;" placeholder="散件" />
            <button class="secondary" data-action="confirmItem" data-sku-id="${item.sku_id}">确认</button>
          </div>
        </td>
      </tr>
    `).join('');
  }
}

export async function confirmItem(skuId) {
  if (!appState.currentBatch) return;

  const item = appState.mergeItems.find(i => i.sku_id === skuId);

  if (!item) {
      showAlert('danger', '数据同步错误，请刷新页面');
      return;
  }

  const caseInput = document.getElementById(`case-${skuId}`);
  const singleInput = document.getElementById(`single-${skuId}`);

  const payload = {
      batch_id: appState.currentBatch.batch_id,
      close_batch: false,
      items: [{
          sku_id: item.sku_id,
          case_qty: caseInput.value || 0,
          single_qty: singleInput.value || 0,
          expected_qty: item.expected_qty || 0
      }]
  };

  const result = await batchAPI.confirmMerge(appState.currentBatch.batch_id, payload.items, false);

  if (result.success) {
      showAlert('success', '已确认');
      showMergePage(appState.currentBatch.batch_id);
  } else {
      showAlert('danger', '确认失败: ' + result.message);
  }
}

export async function confirmAllMerge() {
  if (!appState.currentBatch) return;
  if (!confirm('确定要根据当前的输入值确认所有条目吗？')) return;

  const items = [];
  if (appState.mergeItems) {
      appState.mergeItems.forEach((item) => {
          const caseInput = document.getElementById(`case-${item.sku_id}`);
          const singleInput = document.getElementById(`single-${item.sku_id}`);

          if (caseInput && singleInput) {
              items.push({
                  sku_id: item.sku_id,
                  case_qty: caseInput.value || 0,
                  single_qty: singleInput.value || 0,
                  expected_qty: item.expected_qty || 0
              });
          }
      });
  }

  if (items.length === 0) {
      showAlert('warning', '没有可确认的条目');
      return;
  }

  const payload = {
      batch_id: appState.currentBatch.batch_id,
      close_batch: true,
      items: items
  };

  const result = await batchAPI.confirmMerge(appState.currentBatch.batch_id, items, true);

  if (result.success) {
      showAlert('success', '全部确认成功');
      showMergePage(appState.currentBatch.batch_id);
  } else {
      showAlert('danger', '批量确认失败: ' + result.message);
  }
}
