/**
 * MRS 物料收发管理系统 - 后台管理交互逻辑
 * 文件路径: dc_html/mrs/js/backend.js
 * 说明: 后台管理页面的所有交互逻辑
 * Update: Implemented Batch Import JS Logic + AI Prompt Helper
 */

// 全局状态
const appState = {
  currentPage: 'batches',
  batches: [],
  categories: [],
  skus: [],
  currentBatch: null,
  currentSku: null,
  currentCategory: null
};

// P1 Task: AI Prompt
const SKU_IMPORT_PROMPT = `
你是一个WMS数据专员。请识别图片中的物料清单。
输出格式要求（使用 "|" 分隔）：
[品名] | [箱规/规格字符串] | [单位] | [品类]
注意：
- 箱规列原样输出图片内容（如 "500" 或 "500g/30包"），不要计算结果。
- 如果没有品类，留空。
- 不要输出表头和Markdown格式。
`;

// DOM 元素引用
const dom = {};

/**
 * 初始化 DOM 引用
 */
function initDom() {
  // 菜单项
  dom.menuItems = document.querySelectorAll('.menu-item');

  // 页面容器
  dom.pages = {
    batches: document.getElementById('page-batches'),
    merge: document.getElementById('page-merge'),
    catalog: document.getElementById('page-catalog'),
    categories: document.getElementById('page-categories'),
    reports: document.getElementById('page-reports')
  };

  // 模态框
  dom.modals = {
    batch: document.getElementById('modal-batch'),
    batchDetail: document.getElementById('modal-batch-detail'),
    sku: document.getElementById('modal-sku'),
    category: document.getElementById('modal-category'),
    importSku: document.getElementById('modal-import-sku'),
    aiPrompt: document.getElementById('modal-ai-prompt')
  };
}

/**
 * API 调用封装
 */
const api = {
  /**
   * 通用API调用
   */
  async call(url, options = {}) {
    try {
      const response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        }
      });

      // 处理 401 未授权
      if (response.status === 401) {
        window.location.href = 'login.php';
        return { success: false, message: '登录失效，正在跳转...' };
      }

      return await response.json();
    } catch (error) {
      console.error('API错误:', error);
      return { success: false, message: '网络错误' };
    }
  },

  /**
   * 获取批次列表
   */
  async getBatches(filters = {}) {
    const params = new URLSearchParams(filters);
    return await this.call(`api.php?route=backend_batches&${params}`);
  },

  /**
   * 获取批次详情
   */
  async getBatchDetail(batchId) {
    return await this.call(`api.php?route=backend_batch_detail&batch_id=${batchId}`);
  },

  /**
   * 保存批次
   */
  async saveBatch(data) {
    return await this.call('api.php?route=backend_save_batch', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  /**
   * 删除批次
   */
  async deleteBatch(batchId) {
    return await this.call('api.php?route=backend_delete_batch', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId })
    });
  },

  /**
   * 获取批次合并数据
   */
  async getMergeData(batchId) {
    return await this.call(`api.php?route=backend_merge_data&batch_id=${batchId}`);
  },

  /**
   * 确认批次合并
   */
  async confirmMerge(batchId, items) {
    return await this.call('api.php?route=backend_confirm_merge', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId, items })
    });
  },

  /**
   * 获取SKU列表
   */
  async getSkus(filters = {}) {
    // [FIX] 过滤空值参数，避免发送 ?search=&category_id= 这样的无效参数
    const cleanFilters = {};
    for (const [key, value] of Object.entries(filters)) {
      if (value !== '' && value !== null && value !== undefined) {
        cleanFilters[key] = value;
      }
    }

    const params = new URLSearchParams(cleanFilters);
    const queryString = params.toString();
    const url = `api.php?route=backend_skus${queryString ? '&' + queryString : ''}`;
    return await this.call(url);
  },

  /**
   * 保存SKU
   */
  async saveSku(data) {
    return await this.call('api.php?route=backend_save_sku', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  /**
   * 删除SKU
   */
  async deleteSku(skuId) {
    return await this.call('api.php?route=backend_delete_sku', {
      method: 'POST',
      body: JSON.stringify({ sku_id: skuId })
    });
  },

  /**
   * 获取品类列表
   */
  async getCategories() {
    return await this.call('api.php?route=backend_categories');
  },

  /**
   * 保存品类
   */
  async saveCategory(data) {
    return await this.call('api.php?route=backend_save_category', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  /**
   * 批量导入SKU (P1 Task)
   */
  async importSkusText(text) {
    return await this.call('api.php?route=backend_import_skus_text', {
      method: 'POST',
      body: JSON.stringify({ text })
    });
  },

  /**
   * 删除品类
   */
  async deleteCategory(categoryId) {
    return await this.call('api.php?route=backend_delete_category', {
      method: 'POST',
      body: JSON.stringify({ category_id: categoryId })
    });
  },

  /**
   * 获取统计报表数据
   */
  async getReports(type, filters = {}) {
    const params = new URLSearchParams({ type, ...filters });
    return await this.call(`api.php?route=backend_reports&${params}`);
  }
};

/**
 * 页面导航
 */
function showPage(pageName) {
  // 更新状态
  appState.currentPage = pageName;

  // 隐藏所有页面
  Object.values(dom.pages).forEach(page => {
    if (page) page.classList.remove('active');
  });

  // 显示目标页面
  if (dom.pages[pageName]) {
    dom.pages[pageName].classList.add('active');
  }

  // 更新菜单激活状态
  dom.menuItems.forEach(item => {
    if (item.dataset.target === pageName) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });

  // 加载页面数据
  loadPageData(pageName);
}

/**
 * 加载页面数据
 */
async function loadPageData(pageName) {
  switch (pageName) {
    case 'batches':
      await loadBatches();
      break;
    case 'catalog':
      await loadSkus();
      break;
    case 'categories':
      await loadCategories();
      break;
    case 'reports':
      await loadReports();
      break;
  }
}

/**
 * 加载批次列表
 */
async function loadBatches() {
  const result = await api.getBatches();
  if (result.success) {
    // [FIX] API 返回结构是 { batches: [], pagination: {} }
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
 * 加载SKU列表
 * [FIX] 修复搜索功能：读取筛选条件并传递给API
 */
async function loadSkus() {
  // [FIX] 读取搜索输入框的值
  const filters = {
    search: document.getElementById('catalog-filter-search')?.value.trim() || '',
    category_id: document.getElementById('catalog-filter-category')?.value || '',
    is_precise_item: document.getElementById('catalog-filter-type')?.value || ''
  };

  // [FIX] 传递筛选参数给API
  const result = await api.getSkus(filters);

  if (result.success) {
    appState.skus = result.data;
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
    const unitRule = sku.case_unit_name
      ? `1 ${sku.case_unit_name} = ${sku.case_to_standard_qty} ${sku.standard_unit}`
      : '—';

    return `
      <tr>
        <td>${escapeHtml(sku.sku_name)}</td>
        <td>${escapeHtml(sku.category_name || '-')}</td>
        <td>${escapeHtml(sku.brand_name)}</td>
        <td>${sku.is_precise_item ? '精计' : '粗计'}</td>
        <td>${escapeHtml(sku.standard_unit)}</td>
        <td>${escapeHtml(unitRule)}</td>
        <td><span class="badge success">启用</span></td>
        <td class="table-actions">
          <button class="text" onclick="editSku(${sku.sku_id})">编辑</button>
          <button class="text danger" onclick="deleteSku(${sku.sku_id})">删除</button>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * 加载品类列表
 */
async function loadCategories() {
  const result = await api.getCategories();
  if (result.success) {
    appState.categories = result.data;
    renderCategories();
  } else {
    showAlert('danger', '加载品类列表失败: ' + result.message);
  }
}

/**
 * 渲染品类列表
 */
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

/**
 * 加载统计报表
 */
async function loadReports() {
  // TODO: 实现报表加载逻辑
}

/**
 * 显示合并页面
 */
async function showMergePage(batchId) {
  appState.currentBatch = appState.batches.find(b => b.batch_id === batchId);

  const result = await api.getMergeData(batchId);
  if (result.success) {
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

  // 渲染合并数据表格
  const tbody = document.querySelector('#page-merge tbody');
  if (tbody && data.items) {
    tbody.innerHTML = data.items.map((item, index) => `
      <tr>
        <td>${escapeHtml(item.sku_name)}</td>
        <td>${escapeHtml(item.category_name || '-')}</td>
        <td>${item.is_precise_item ? '精计' : '粗计'}</td>
        <td>${item.case_unit_name ? `1 ${item.case_unit_name} = ${item.case_to_standard_qty} ${item.standard_unit}` : '—'}</td>
        <td>${item.expected_qty || '-'}</td>
        <td>${escapeHtml(item.raw_summary || '-')}</td>
        <td><span class="pill">${escapeHtml(item.suggested_qty || '-')}</span></td>
        <td><span class="badge ${item.status === 'normal' ? 'success' : item.status === 'over' ? 'warning' : 'danger'}">${item.status_text || '正常'}</span></td>
        <td>
          <div class="table-actions">
            <button class="text" onclick="viewRawRecords(${item.sku_id})">查看明细</button>
            <input type="number" id="case-${index}" value="${item.confirmed_case || 0}" style="width: 70px;" placeholder="箱数" />
            <input type="number" id="single-${index}" value="${item.confirmed_single || 0}" style="width: 70px;" placeholder="散件" />
            <button class="secondary" onclick="confirmItem(${index})">确认</button>
          </div>
        </td>
      </tr>
    `).join('');
  }
}

/**
 * 辅助函数: HTML转义
 */
function escapeHtml(text) {
  if (text === null || text === undefined) return '';
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * 辅助函数: 获取状态文本
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
 * 辅助函数: 获取状态徽章样式
 */
function getStatusBadgeClass(status) {
  const classMap = {
    'draft': 'info',
    'receiving': 'info',
    'pending_merge': 'warning',
    'confirmed': 'success',
    'posted': 'success'
  };
  return classMap[status] || 'info';
}

/**
 * 显示提示信息
 */
function showAlert(type, message) {
  // 创建或获取alert容器
  let alertContainer = document.querySelector('.alert-container');
  if (!alertContainer) {
    alertContainer = document.createElement('div');
    alertContainer.className = 'alert-container';
    alertContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 1000; max-width: 400px;';
    document.body.appendChild(alertContainer);
  }

  // 创建alert元素
  const alert = document.createElement('div');
  alert.className = `alert ${type}`;
  alert.textContent = message;
  alertContainer.appendChild(alert);

  // 3秒后自动移除
  setTimeout(() => {
    alert.remove();
  }, 3000);
}

/**
 * 模态框管理
 */
const modal = {
  show(modalId) {
    const backdrop = document.getElementById(modalId);
    if (backdrop) {
      backdrop.classList.add('show');
    }
  },

  hide(modalId) {
    const backdrop = document.getElementById(modalId);
    if (backdrop) {
      backdrop.classList.remove('show');
    }
  }
};

// ================================================================
// 全局函数供 HTML onclick 调用
// ================================================================

/**
 * 显示新建批次模态框
 */
function showNewBatchModal() {
  document.getElementById('form-batch').reset();
  // 清除 hidden ID 防止变成更新
  document.getElementById('batch-id').value = '';
  document.getElementById('modal-batch-title').textContent = '新建批次';

  // [SECURITY FIX] 移除前端生成批次编号逻辑，改为后端生成
  const today = new Date().toISOString().split('T')[0];
  const batchCodeInput = document.getElementById('batch-code');

  // 清空值并设置占位符，由后端生成
  batchCodeInput.value = '';
  batchCodeInput.placeholder = '系统自动生成';
  batchCodeInput.readOnly = false;

  document.getElementById('batch-date').value = today;
  modal.show('modal-batch');
}

/**
 * 显示新建SKU模态框
 */
function showNewSkuModal() {
  document.getElementById('form-sku').reset();
  document.getElementById('modal-sku-title').textContent = '新增SKU';
  // 加载品类选项
  loadCategoryOptions();
  modal.show('modal-sku');
}

/**
 * 显示批量导入SKU模态框 (P1 Task)
 */
function showImportSkuModal() {
  document.getElementById('import-sku-text').value = '';
  // 可以在这里打印Prompt供开发者调试，或在UI显示复制按钮
  console.log('Use this prompt for AI:', SKU_IMPORT_PROMPT);
  modal.show('modal-import-sku');
}

/**
 * 显示AI提示词助手 (P1 Task)
 */
function showAiPromptHelper() {
  // 填充提示词
  const textarea = document.getElementById('ai-prompt-text');
  if (textarea) {
    textarea.value = SKU_IMPORT_PROMPT;
  }

  // 显示模态框
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.add('show');
  }
}

/**
 * 关闭AI提示词助手 (P1 Task)
 */
function closeAiPromptHelper() {
  const modalEl = document.getElementById('modal-ai-prompt');
  if (modalEl) {
    modalEl.classList.remove('show');
  }
}

/**
 * 复制AI提示词 (P1 Task)
 */
function copyAiPrompt() {
  const textarea = document.getElementById('ai-prompt-text');
  if (!textarea) return;

  // 选中文本
  textarea.select();
  textarea.setSelectionRange(0, 99999); // 适配移动端

  // 尝试使用现代 Clipboard API
  if (navigator.clipboard) {
    navigator.clipboard.writeText(textarea.value).then(() => {
      showAlert('success', '复制成功');
    }).catch(err => {
      console.error('Clipboard API failed', err);
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
    console.error('Fallback copy failed', err);
    showAlert('danger', '浏览器不支持自动复制');
  }
}

/**
 * 执行批量导入 (P1 Task)
 */
async function importSkus() {
  const textarea = document.getElementById('import-sku-text');
  const text = textarea.value.trim();

  if (!text) {
    showAlert('warning', '请粘贴内容');
    return;
  }

  const result = await api.importSkusText(text);
  if (result.success) {
    showAlert('success', result.message);
    modal.hide('modal-import-sku');
    loadSkus();
  } else {
    showAlert('danger', '导入失败: ' + result.message);
  }
}

/**
 * 显示新建品类模态框
 */
function showNewCategoryModal() {
  document.getElementById('form-category').reset();
  document.getElementById('modal-category-title').textContent = '新增品类';
  modal.show('modal-category');
}

/**
 * 加载品类选项到下拉框
 */
async function loadCategoryOptions() {
  const result = await api.getCategories();
  if (result.success) {
    const select = document.getElementById('sku-category');
    select.innerHTML = '<option value="">请选择</option>' +
      result.data.map(cat => `<option value="${cat.category_id}">${cat.category_name}</option>`).join('');
  }
}

/**
 * 保存批次
 */
async function saveBatch(event) {
  event.preventDefault();
  const formData = new FormData(event.target);
  const data = Object.fromEntries(formData);

  const result = await api.saveBatch(data);
  if (result.success) {
    showAlert('success', '批次保存成功');
    modal.hide('modal-batch');
    loadBatches();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 保存SKU
 */
async function saveSku(event) {
  event.preventDefault();
  const formData = new FormData(event.target);
  const data = Object.fromEntries(formData);

  const result = await api.saveSku(data);
  if (result.success) {
    showAlert('success', 'SKU保存成功');
    modal.hide('modal-sku');
    loadSkus();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 保存品类
 */
async function saveCategory(event) {
  event.preventDefault();
  const formData = new FormData(event.target);
  const data = Object.fromEntries(formData);

  const result = await api.saveCategory(data);
  if (result.success) {
    showAlert('success', '品类保存成功');
    modal.hide('modal-category');
    loadCategories();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 查看批次详情
 */
async function viewBatch(batchId) {
  const result = await api.getBatchDetail(batchId);
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

/**
 * 编辑批次
 */
async function editBatch(batchId) {
  const result = await api.getBatchDetail(batchId);
  if (result.success) {
    const batch = result.data.batch;

    // 填充表单
    document.getElementById('batch-id').value = batch.batch_id;
    document.getElementById('batch-code').value = batch.batch_code;
    // 批次号通常不允许修改，或者设为只读
    // document.getElementById('batch-code').readOnly = true;
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

/**
 * 删除批次
 */
async function deleteBatch(batchId) {
  if (!confirm('确定要删除这个批次吗?此操作不可撤销!')) {
    return;
  }

  const result = await api.deleteBatch(batchId);
  if (result.success) {
    showAlert('success', '批次删除成功');
    loadBatches();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

/**
 * 编辑SKU
 */
async function editSku(skuId) {
  showAlert('info', '编辑SKU功能开发中...');
}

/**
 * 删除SKU
 */
async function deleteSku(skuId) {
  if (!confirm('确定要删除这个SKU吗?')) {
    return;
  }

  const result = await api.deleteSku(skuId);
  if (result.success) {
    showAlert('success', 'SKU删除成功');
    loadSkus();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

/**
 * 编辑品类
 */
async function editCategory(categoryId) {
  showAlert('info', '编辑品类功能开发中...');
}

/**
 * 删除品类
 */
async function deleteCategory(categoryId) {
  if (!confirm('确定要删除这个品类吗?')) {
    return;
  }

  const result = await api.deleteCategory(categoryId);
  if (result.success) {
    showAlert('success', '品类删除成功');
    loadCategories();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

/**
 * 确认单个合并项
 */
async function confirmItem(index) {
  showAlert('info', '确认单项功能开发中...');
}

/**
 * 确认全部合并
 */
async function confirmAllMerge() {
  showAlert('info', '确认全部合并功能开发中...');
}

/**
 * 查看原始记录
 */
async function viewRawRecords(skuId) {
  showAlert('info', '查看原始记录功能开发中...');
}

/**
 * 导出报表
 */
async function exportReport() {
  showAlert('info', '导出报表功能开发中...');
}

/**
 * 初始化应用
 */
async function initApp() {
  // 初始化 DOM 引用
  initDom();

  // 绑定菜单点击事件
  dom.menuItems.forEach(item => {
    item.addEventListener('click', () => {
      const target = item.dataset.target;
      if (target) {
        showPage(target);
      }
    });
  });

  // 绑定按钮点击事件（通过事件委托）
  document.body.addEventListener('click', (e) => {
    const target = e.target;

    // 处理带 data-target 的按钮
    if (target.dataset.target) {
      const page = target.dataset.target;
      if (page === 'merge' && target.dataset.batchId) {
        showMergePage(parseInt(target.dataset.batchId));
      } else {
        showPage(page);
      }
    }
  });

  // 加载初始页面
  showPage('batches');
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}
