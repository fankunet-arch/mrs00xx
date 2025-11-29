// 文件路径: fankunet-arch/mrs01xx/mrs01xx-010e6f69542fe42f0d89e7b08582c8361ace551f/dc_html/mrs/js/receipt.js
/**
 * MRS 物料收发管理系统 - 极速收货前台交互逻辑
 * 文件路径: dc_html/mrs/js/receipt.js
 * 说明: 前台收货页面的所有交互逻辑
 * 重构: 使用 showAlert 统一通知，移除 inline onclick 处理器
 */

/**
 * 格式化数字显示：去除不必要的小数点后的0
 * @param {number|string} value - 要格式化的数值
 * @returns {string} - 格式化后的字符串
 */
function formatNumber(value) {
  const num = parseFloat(value);
  if (isNaN(num)) return '0';

  // 如果是整数，直接返回整数形式
  if (num % 1 === 0) {
    return parseInt(num, 10).toString();
  }

  // 否则保留最多4位小数，并移除末尾的0
  return num.toFixed(4).replace(/\.?0+$/, '');
}

/**
 * 显示通知消息（与 backend 保持一致）
 */
function showAlert(type, message) {
  const alertContainer = document.getElementById('alert-container') || createAlertContainer();

  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  alert.textContent = message;

  alertContainer.appendChild(alert);

  setTimeout(() => {
    alert.classList.add('show');
  }, 10);

  setTimeout(() => {
    alert.classList.remove('show');
    setTimeout(() => alert.remove(), 300);
  }, 3000);
}

function createAlertContainer() {
  const container = document.createElement('div');
  container.id = 'alert-container';
  container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
  document.body.appendChild(container);
  return container;
}

// 全局状态
const appState = {
  batches: [],
  materials: [],
  // [FIX] 默认通用单位，当未选择SKU时使用
  units: ['瓶', '箱', '包', '件', '袋', 'kg', 'g'],
  currentBatch: null,
  selectedUnit: '瓶',
  selectedMaterial: null,
  records: []
};

// DOM 元素引用
const dom = {
  batchButtons: null,
  batchInfoGrid: null,
  candidateList: null,
  materialInput: null,
  qtyInput: null,
  unitRow: null,
  recordsEl: null,
  summaryEl: null,
  btnAdd: null
};

/**
 * 初始化 DOM 引用
 */
function initDom() {
  dom.batchButtons = document.getElementById('batch-buttons');
  dom.batchInfoGrid = document.getElementById('batch-info-grid');
  dom.candidateList = document.getElementById('candidate-list');
  dom.materialInput = document.getElementById('material-input');
  dom.qtyInput = document.getElementById('qty-input');
  dom.unitRow = document.getElementById('unit-row');
  dom.recordsEl = document.getElementById('records');
  dom.summaryEl = document.getElementById('summary');
  dom.btnAdd = document.getElementById('btn-add');
}

/**
 * API 调用封装
 */
const api = {
  /**
   * 获取批次列表
   */
  async getBatches() {
    try {
      const response = await fetch('/mrs/be/index.php?action=get_batch_list_api');
      const data = await response.json();
      if (data.success) {
        return data.data;
      } else {
        console.error('获取批次列表失败:', data.message);
        return [];
      }
    } catch (error) {
      console.error('API 错误:', error);
      return [];
    }
  },

  /**
   * 搜索 SKU
   */
  async searchSku(keyword) {
    try {
      const response = await fetch(`/mrs/be/index.php?action=sku_search_api&keyword=${encodeURIComponent(keyword)}`);
      const data = await response.json();
      if (data.success) {
        return data.data;
      } else {
        console.error('搜索SKU失败:', data.message);
        return [];
      }
    } catch (error) {
      console.error('API 错误:', error);
      return [];
    }
  },

  /**
   * 保存收货记录
   */
  async saveRecord(recordData) {
    try {
      const response = await fetch('/mrs/be/index.php?action=save_receipt_api', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(recordData)
      });
      const data = await response.json();
      return data;
    } catch (error) {
      console.error('API 错误:', error);
      return { success: false, message: '网络错误' };
    }
  },

  /**
   * 获取批次的原始记录
   */
  async getBatchRecords(batchId) {
    try {
      const response = await fetch(`/mrs/be/index.php?action=get_batch_records_api&batch_id=${batchId}`);
      const data = await response.json();
      if (data.success) {
        return data.data;
      } else {
        console.error('获取批次记录失败:', data.message);
        return [];
      }
    } catch (error) {
      console.error('API 错误:', error);
      return [];
    }
  }
};

/**
 * 渲染批次按钮
 */
function renderBatches() {
  dom.batchButtons.innerHTML = '';
  appState.batches.forEach((batch) => {
    const btn = document.createElement('button');
    btn.className = 'batch-btn' + (batch.batch_id === appState.currentBatch?.batch_id ? ' active' : '');
    btn.textContent = batch.batch_code;
    btn.dataset.batchId = batch.batch_id;
    dom.batchButtons.appendChild(btn);
  });
}

/**
 * 处理批次选择
 */
async function handleBatchSelect(batchId) {
  const batch = appState.batches.find(b => b.batch_id === parseInt(batchId));
  if (batch) {
    appState.currentBatch = batch;
    renderBatches();
    renderBatchInfo();
    await loadBatchRecords();
  }
}

/**
 * 渲染批次信息 (紧凑版 - 优化首屏空间)
 */
function renderBatchInfo() {
  if (!appState.currentBatch) {
    dom.batchInfoGrid.innerHTML = '<div class="label">请选择批次</div>';
    return;
  }

  // 清空原有网格内容
  dom.batchInfoGrid.innerHTML = '';

  // 移除原有的 grid 样式（如果是在 CSS 中定义的 .info-grid，这里通过 JS 覆盖样式或更改 class 亦可，
  // 但最简单的是直接渲染不同的 HTML 结构）
  dom.batchInfoGrid.className = ''; // 移除 .info-grid class 以取消网格布局

  // 构造紧凑的信息字符串
  const batchCode = appState.currentBatch.batch_code;
  const date = appState.currentBatch.batch_date;
  const location = appState.currentBatch.location_name;
  const status = getStatusText(appState.currentBatch.batch_status);

  // 构造一行显示的 HTML
  const div = document.createElement('div');
  div.className = 'compact-info-bar';

  // 使用 innerHTML 填充内容
  div.innerHTML = `
    <span class="compact-info-item"><strong>${batchCode}</strong></span>
    <span class="compact-info-item">${date}</span>
    <span class="compact-info-item">${location}</span>
    <span class="compact-info-item">${status}</span>
  `;

  // 如果有备注，单独显示一行（可选，为了极致压缩也可以不显示或悬浮显示）
  if (appState.currentBatch.remark) {
    const remarkDiv = document.createElement('div');
    remarkDiv.style.fontSize = '12px';
    remarkDiv.style.color = '#64748b';
    remarkDiv.style.marginTop = '4px';
    remarkDiv.textContent = '备注: ' + appState.currentBatch.remark;
    div.appendChild(remarkDiv); // 将其加到 compact-info-bar 内部或外部均可，这里加在内部会破坏flex，建议外部
  }

  dom.batchInfoGrid.appendChild(div);
}

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
 * 渲染单位按钮
 */
function renderUnits() {
  dom.unitRow.innerHTML = '';
  appState.units.forEach((unit) => {
    const btn = document.createElement('div');
    btn.className = 'unit-btn' + (unit === appState.selectedUnit ? ' active' : '');
    btn.textContent = unit;
    btn.dataset.unit = unit;
    dom.unitRow.appendChild(btn);
  });
}

/**
 * 处理单位选择
 */
function handleUnitSelect(unit) {
  appState.selectedUnit = unit;
  renderUnits();
}

/**
 * 渲染候选物料列表
 */
async function renderCandidates(keyword = '') {
  const lower = keyword.trim().toLowerCase();

  // [FIX] 当用户手动修改输入时，重置SKU选择，防止使用上一次选择的SKU ID提交不匹配的名称
  // 注意：这会导致单位重置为默认列表，这是符合预期的（因为没有确定的SKU约束）
  if (appState.selectedMaterial && appState.selectedMaterial.sku_name !== keyword) {
    appState.selectedMaterial = null;
    resetUnitsToDefault();
  }

  if (!lower) {
    dom.candidateList.innerHTML = '';
    dom.candidateList.style.display = 'none';
    return;
  }

  // 从 API 获取搜索结果
  const results = await api.searchSku(lower);

  // 存储搜索结果供事件处理器使用
  appState.materials = results;

  dom.candidateList.innerHTML = '';

  if (results.length === 0) {
    dom.candidateList.innerHTML = '<div class="candidate" style="cursor: default;"><div class="label">未找到匹配的物料</div></div>';
    dom.candidateList.style.display = 'block';
    return;
  }

  results.forEach((material) => {
    const row = document.createElement('div');
    row.className = 'candidate';
    row.dataset.skuId = material.sku_id;

    const unitInfo = material.case_unit_name
      ? `${formatNumber(material.case_to_standard_qty)} ${material.standard_unit}/${material.case_unit_name}`
      : material.standard_unit;

    row.innerHTML = `
      <div>
        <div><strong>${material.sku_name}</strong></div>
        <div class="label">${material.brand_name} | ${unitInfo}</div>
      </div>
      <div class="tag">${material.category_name || '物料'}</div>
    `;

    dom.candidateList.appendChild(row);
  });

  dom.candidateList.style.display = 'block';
}

/**
 * 处理候选物料选择
 */
function handleCandidateSelect(skuId) {
  const material = appState.materials.find(m => m.sku_id === parseInt(skuId));
  if (material) {
    // [FIX] 选中物料时，动态更新可用单位列表
    appState.selectedMaterial = material;
    updateUnitsForSku(material);

    dom.materialInput.value = material.sku_name;
    dom.candidateList.innerHTML = '';
    dom.candidateList.style.display = 'none';
    dom.qtyInput.focus();
  }
}

/**
 * [FIX] 根据SKU更新可用单位
 */
function updateUnitsForSku(sku) {
    const validUnits = [];
    if (sku.standard_unit) validUnits.push(sku.standard_unit);
    if (sku.case_unit_name) validUnits.push(sku.case_unit_name);

    // 如果没有配置单位（理论上不应发生），回退到默认
    if (validUnits.length > 0) {
        appState.units = validUnits;
        // 默认选中标准单位
        appState.selectedUnit = sku.standard_unit;
    } else {
        resetUnitsToDefault();
    }

    renderUnits();
}

/**
 * [FIX] 重置单位为默认列表
 */
function resetUnitsToDefault() {
    appState.units = ['瓶', '箱', '包', '件', '袋', 'kg', 'g'];
    // 尽量保持当前选择，如果不在默认列表中则重置
    if (!appState.units.includes(appState.selectedUnit)) {
        appState.selectedUnit = appState.units[0];
    }
    renderUnits();
}

/**
 * 加载批次记录
 */
async function loadBatchRecords() {
  if (!appState.currentBatch) {
    return;
  }

  const records = await api.getBatchRecords(appState.currentBatch.batch_id);
  appState.records = records;
  renderRecords();
}

/**
 * 渲染记录列表
 */
function renderRecords() {
  dom.recordsEl.innerHTML = '';

  if (appState.records.length === 0) {
    dom.recordsEl.innerHTML = '<div class="label">暂无记录</div>';
    renderSummary();
    return;
  }

  appState.records.forEach((record) => {
    const row = document.createElement('div');
    row.className = 'record-row';

    const time = new Date(record.recorded_at).toLocaleTimeString('zh-CN', { hour12: false });

    // 使用统一的数字格式化函数
    const displayQty = formatNumber(record.qty);

    row.innerHTML = `
      <div>
        <div><strong>${record.sku_name || '未知物料'}</strong></div>
        <div class="time">${time} - ${record.operator_name}</div>
      </div>
      <div class="value">${displayQty}</div>
      <div class="tag">${record.unit_name}</div>
    `;

    dom.recordsEl.appendChild(row);
  });

  renderSummary();
}

/**
 * 渲染汇总信息
 */
function renderSummary() {
  const grouped = appState.records.reduce((acc, cur) => {
    const key = `${cur.sku_name || '未知物料'}-${cur.unit_name}`;
    acc[key] = (acc[key] || 0) + Number(cur.qty || 0);
    return acc;
  }, {});

  const entries = Object.entries(grouped);

  if (entries.length === 0) {
    dom.summaryEl.innerHTML = '<span class="label">暂无汇总</span>';
    return;
  }

  dom.summaryEl.innerHTML = '<div style="margin-bottom: 8px;"><strong>本批次汇总：</strong></div>' +
    entries.map(([key, value]) => {
      const [material, unit] = key.split('-');
      // 使用统一的数字格式化函数
      const displayQty = formatNumber(value);

      return `<div style="margin-top: 4px;">• <strong>${material}</strong>：${displayQty} ${unit}</div>`;
    }).join('');
}

/**
 * 添加记录处理
 */
async function handleAddRecord() {
  const materialName = dom.materialInput.value.trim();
  const qty = dom.qtyInput.value.trim();

  if (!appState.currentBatch) {
    showAlert('warning', '请先选择批次');
    return;
  }

  if (!materialName) {
    showAlert('warning', '请输入物料名称');
    dom.materialInput.focus();
    return;
  }

  if (!qty || parseFloat(qty) <= 0) {
    showAlert('warning', '请输入有效的数量');
    dom.qtyInput.focus();
    return;
  }

  // 准备提交数据
  const recordData = {
    batch_id: appState.currentBatch.batch_id,
    sku_id: appState.selectedMaterial?.sku_id || null,
    sku_name: materialName,
    qty: parseFloat(qty),
    unit_name: appState.selectedUnit,
    operator_name: '操作员', // TODO: 从登录系统获取
    note: ''
  };

  // 提交到后端
  const result = await api.saveRecord(recordData);

  if (result.success) {
    showAlert('success', '保存成功');

    // 清空输入
    dom.materialInput.value = '';
    dom.qtyInput.value = '';
    appState.selectedMaterial = null;

    // [FIX] 保存成功后，重置单位选择，等待下一次输入
    resetUnitsToDefault();

    // 重新加载记录
    await loadBatchRecords();

    // 聚焦到物料输入框
    dom.materialInput.focus();
  } else {
    showAlert('error', '保存失败: ' + result.message);
  }
}

/**
 * 初始化应用
 */
async function initApp() {
  // 初始化 DOM 引用
  initDom();

  // 加载批次列表
  appState.batches = await api.getBatches();

  if (appState.batches.length > 0) {
    // 默认选中第一个批次
    appState.currentBatch = appState.batches[0];
    await loadBatchRecords();
  }

  // 渲染初始界面
  renderBatches();
  renderBatchInfo();
  renderUnits();
  renderCandidates(); // [FIX] 初始化候选列表（隐藏空列表）

  // 绑定事件
  dom.materialInput.addEventListener('input', (e) => {
    renderCandidates(e.target.value);
  });

  dom.btnAdd.addEventListener('click', handleAddRecord);

  // 回车快捷键
  dom.qtyInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      handleAddRecord();
    }
  });

  // 事件委托：批次选择
  dom.batchButtons.addEventListener('click', (e) => {
    const btn = e.target.closest('.batch-btn');
    if (btn && btn.dataset.batchId) {
      handleBatchSelect(btn.dataset.batchId);
    }
  });

  // 事件委托：单位选择
  dom.unitRow.addEventListener('click', (e) => {
    const btn = e.target.closest('.unit-btn');
    if (btn && btn.dataset.unit) {
      handleUnitSelect(btn.dataset.unit);
    }
  });

  // 事件委托：候选物料选择
  dom.candidateList.addEventListener('click', (e) => {
    const row = e.target.closest('.candidate');
    if (row && row.dataset.skuId) {
      handleCandidateSelect(row.dataset.skuId);
    }
  });

  // 聚焦到物料输入框
  dom.materialInput.focus();
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}