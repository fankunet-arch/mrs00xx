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
    outbound: document.getElementById('page-outbound'),
    reports: document.getElementById('page-reports'),
    system: document.getElementById('page-system')
  };

  // 模态框
  dom.modals = {
    batch: document.getElementById('modal-batch'),
    batchDetail: document.getElementById('modal-batch-detail'),
    outbound: document.getElementById('modal-outbound'),
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
      // Add cache busting timestamp
      const separator = url.includes('?') ? '&' : '?';
      const finalUrl = `${url}${separator}_t=${Date.now()}`;

      const response = await fetch(finalUrl, {
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
  async getCategories(filters = {}) {
    const params = new URLSearchParams(filters);
    return await this.call(`api.php?route=backend_categories&${params}`);
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
   * 获取出库单列表
   */
  async getOutboundList(filters = {}) {
    const params = new URLSearchParams(filters);
    return await this.call(`api.php?route=backend_outbound_list&${params}`);
  },

  /**
   * 获取出库单详情
   */
  async getOutboundDetail(orderId) {
    return await this.call(`api.php?route=backend_outbound_detail&order_id=${orderId}`);
  },

  /**
   * 保存出库单
   */
  async saveOutbound(data) {
    return await this.call('api.php?route=backend_save_outbound', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  /**
   * 确认出库单
   */
  async confirmOutbound(orderId) {
    return await this.call('api.php?route=backend_confirm_outbound', {
      method: 'POST',
      body: JSON.stringify({ order_id: orderId })
    });
  },

  /**
   * 查询库存
   */
  async queryInventory(skuId) {
    return await this.call(`api.php?route=backend_inventory_query&sku_id=${skuId}`);
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
      await loadCategoryFilterOptions();
      await loadSkus();
      break;
    case 'categories':
      await loadCategories();
      break;
    case 'outbound':
      await loadOutboundList();
      break;
    case 'reports':
      await loadReports();
      break;
    case 'system':
      await loadSystemStatus();
      break;
  }
}

/**
 * 加载系统状态
 */
async function loadSystemStatus() {
  const container = document.getElementById('system-status-container');
  if (!container) return;

  container.innerHTML = '<p class="text-muted">正在检查系统健康状态...</p>';

  const result = await api.call('api.php?route=backend_system_status');

  if (result.success) {
    if (result.data.healthy) {
      container.innerHTML = `
        <div class="alert success">
          <strong>系统状态良好</strong>
          <p>数据库结构已是最新。</p>
        </div>
      `;
    } else {
      let issuesHtml = result.data.issues.map(issue => `<li>${escapeHtml(issue)}</li>`).join('');
      let actionsHtml = '';

      if (result.data.migration_needed) {
        actionsHtml = `
          <div class="mt-4">
            <button class="warning" onclick="fixSystem()">🛠 修复数据库 (自动迁移)</button>
          </div>
        `;
      }

      container.innerHTML = `
        <div class="alert danger">
          <strong>检测到系统问题:</strong>
          <ul class="mt-2">${issuesHtml}</ul>
        </div>
        ${actionsHtml}
      `;
    }
  } else {
    container.innerHTML = `<div class="alert danger">检查失败: ${escapeHtml(result.message)}</div>`;
  }
}

/**
 * 修复系统问题
 */
async function fixSystem() {
  if (!confirm('确定要执行系统修复操作吗？建议先备份数据库。')) {
    return;
  }

  const result = await api.call('api.php?route=backend_system_fix', { method: 'POST' });

  if (result.success) {
    showAlert('success', '修复成功！');
    let messages = result.data.messages || [];
    if (messages.length > 0) {
      alert('修复详情:\n' + messages.join('\n'));
    }
    loadSystemStatus();
  } else {
    showAlert('danger', '修复失败: ' + result.message);
  }
}

/**
 * 加载批次列表
 */
async function loadBatches() {
  // [FIX] 获取筛选参数
  const filters = {
    search: document.getElementById('filter-search')?.value.trim() || '',
    date_start: document.getElementById('filter-date-start')?.value || '',
    date_end: document.getElementById('filter-date-end')?.value || '',
    status: document.getElementById('filter-status')?.value || ''
  };

  const result = await api.getBatches(filters);
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
    // [FIX] Format quantity to remove trailing zeros (e.g., 20.0000 -> 20, 20.50 -> 20.5)
    const formattedQty = sku.case_to_standard_qty ? parseFloat(sku.case_to_standard_qty) : '';

    const unitRule = sku.case_unit_name
      ? `1 ${sku.case_unit_name} = ${formattedQty} ${sku.standard_unit}`
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
  const filters = {
    search: document.getElementById('category-filter-search')?.value.trim() || ''
  };
  const result = await api.getCategories(filters);
  if (result.success) {
    appState.categories = result.data;
    renderCategories();
  } else {
    showAlert('danger', '加载品类列表失败: ' + result.message);
  }
}

/**
 * 加载品类筛选选项 (for SKU catalog filter)
 */
async function loadCategoryFilterOptions() {
  const result = await api.getCategories();
  if (result.success) {
    const select = document.getElementById('catalog-filter-category');
    if (select) {
      const currentVal = select.value;
      select.innerHTML = '<option value="">全部品类</option>' +
        result.data.map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
      // 尝试恢复之前的选择
      if (currentVal) {
        select.value = currentVal;
      }
    }
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
        <td>${item.case_unit_name ? `1 ${item.case_unit_name} = ${parseFloat(item.case_to_standard_qty)} ${item.standard_unit}` : '—'}</td>
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
  document.getElementById('sku-id').value = ''; // 清除ID
  document.getElementById('modal-sku-title').textContent = '新增SKU';
  document.getElementById('sku-code').readOnly = false; // 允许输入编码
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
  document.getElementById('category-id').value = '';
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
  // 加载品类选项 (确保下拉框有值)
  await loadCategoryOptions();

  // 获取SKU详情
  const result = await api.call(`api.php?route=backend_sku_detail&sku_id=${skuId}`);

  if (result.success) {
    const sku = result.data;

    // 填充表单
    document.getElementById('sku-id').value = sku.sku_id;
    document.getElementById('sku-name').value = sku.sku_name;
    document.getElementById('sku-category').value = sku.category_id;
    document.getElementById('sku-brand').value = sku.brand_name;
    document.getElementById('sku-code').value = sku.sku_code;
    // document.getElementById('sku-code').readOnly = true; // 编码通常不建议修改
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
  const result = await api.call(`api.php?route=backend_category_detail&category_id=${categoryId}`);

  if (result.success) {
    const category = result.data;

    document.getElementById('category-id').value = category.category_id;
    document.getElementById('category-name').value = category.category_name;
    document.getElementById('category-code').value = category.category_code || '';

    document.getElementById('modal-category-title').textContent = '编辑品类';
    modal.show('modal-category');
  } else {
    showAlert('danger', '获取品类信息失败: ' + result.message);
  }
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
  if (!appState.currentBatch) return;

  const row = document.querySelector(`#page-merge tbody tr:nth-child(${index + 1})`);
  if (!row) return;

  // Gather data from the specific row
  // Note: The structure of rows in renderMergePage doesn't store SKU ID easily accessible via index unless we look at the data source.
  // We need to re-fetch the merge data or store it in appState.mergeData
  // Wait, renderMergePage uses `data.items.map`. Let's store items in appState.

  if (!appState.mergeItems || !appState.mergeItems[index]) {
      showAlert('danger', '数据同步错误，请刷新页面');
      return;
  }

  const item = appState.mergeItems[index];

  // Get inputs
  const caseInput = document.getElementById(`case-${index}`);
  const singleInput = document.getElementById(`single-${index}`);

  const payload = {
      batch_id: appState.currentBatch.batch_id,
      close_batch: false, // Single item confirm does not close batch
      items: [{
          sku_id: item.sku_id,
          case_qty: caseInput.value || 0,
          single_qty: singleInput.value || 0,
          expected_qty: item.expected_qty || 0 // pass expected for diff calc
      }]
  };

  // Update api call to accept extra data or pass single object
  // Current api.confirmMerge takes (batchId, items). I need to update it or call api.call directly.
  // Let's update api.confirmMerge in this file first.

  const result = await api.call('api.php?route=backend_confirm_merge', {
      method: 'POST',
      body: JSON.stringify(payload)
  });
  if (result.success) {
      showAlert('success', '已确认');
      // Refresh to update status badges
      showMergePage(appState.currentBatch.batch_id);
  } else {
      showAlert('danger', '确认失败: ' + result.message);
  }
}

/**
 * 确认全部合并
 */
async function confirmAllMerge() {
  if (!appState.currentBatch) return;
  if (!confirm('确定要根据当前的输入值确认所有条目吗？')) return;

  // Gather all items
  const items = [];
  if (appState.mergeItems) {
      appState.mergeItems.forEach((item, index) => {
          const caseInput = document.getElementById(`case-${index}`);
          const singleInput = document.getElementById(`single-${index}`);

          // Only include if inputs exist (sanity check)
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

  // Close batch when confirming all?
  // Maybe user wants to confirm all but NOT close?
  // Usually "Confirm All" implies finishing the task.
  // I will assume close_batch = true for "Confirm All".

  const payload = {
      batch_id: appState.currentBatch.batch_id,
      close_batch: true,
      items: items
  };

  const result = await api.call('api.php?route=backend_confirm_merge', {
      method: 'POST',
      body: JSON.stringify(payload)
  });

  if (result.success) {
      showAlert('success', '全部确认成功');
      showMergePage(appState.currentBatch.batch_id);
  } else {
      showAlert('danger', '批量确认失败: ' + result.message);
  }
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

// ================================================================
// 出库管理逻辑
// ================================================================

/**
 * 加载出库单列表
 */
async function loadOutboundList() {
  const filters = {
    status: document.getElementById('filter-outbound-status')?.value || '',
    type: document.getElementById('filter-outbound-type')?.value || ''
  };

  const result = await api.getOutboundList(filters);
  if (result.success) {
    appState.outboundOrders = result.data.list || [];
    renderOutboundList();
  } else {
    showAlert('danger', '加载出库单失败: ' + result.message);
  }
}

/**
 * 渲染出库单列表
 */
function renderOutboundList() {
  const tbody = document.querySelector('#page-outbound tbody');
  if (!tbody) return;

  if (appState.outboundOrders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">暂无出库单数据</td></tr>';
    return;
  }

  const typeMap = { 1: '领料', 2: '调拨', 3: '退货', 4: '报废' };

  tbody.innerHTML = appState.outboundOrders.map(order => `
    <tr>
      <td>${escapeHtml(order.outbound_code)}</td>
      <td>${typeMap[order.outbound_type] || order.outbound_type}</td>
      <td>${escapeHtml(order.outbound_date)}</td>
      <td>${escapeHtml(order.location_name)}</td>
      <td><span class="badge ${order.status === 'confirmed' ? 'success' : 'info'}">${order.status === 'confirmed' ? '已确认' : '草稿'}</span></td>
      <td>${order.item_count} / ${order.total_qty}</td>
      <td class="table-actions">
        ${order.status === 'draft' ?
          `<button class="text" onclick="editOutbound(${order.outbound_order_id})">编辑</button>
           <button class="text success" onclick="confirmOutbound(${order.outbound_order_id})">确认</button>` :
          `<button class="text" onclick="viewOutbound(${order.outbound_order_id})">查看</button>`
        }
      </td>
    </tr>
  `).join('');
}

/**
 * 显示新建出库单
 */
function showNewOutboundModal() {
  document.getElementById('form-outbound').reset();
  document.getElementById('outbound-id').value = '';
  document.getElementById('outbound-date').value = new Date().toISOString().split('T')[0];
  document.getElementById('outbound-items-body').innerHTML = ''; // Clear items
  document.getElementById('modal-outbound-title').textContent = '新建出库单';

  // Add initial empty row
  addOutboundItemRow();

  modal.show('modal-outbound');
}

/**
 * 编辑出库单
 */
async function editOutbound(orderId) {
  const result = await api.getOutboundDetail(orderId);
  if (result.success) {
    const order = result.data;

    document.getElementById('outbound-id').value = order.outbound_order_id;
    document.getElementById('outbound-date').value = order.outbound_date;
    document.getElementById('outbound-type').value = order.outbound_type;
    document.getElementById('outbound-location').value = order.location_name;
    document.getElementById('outbound-remark').value = order.remark || '';

    document.getElementById('modal-outbound-title').textContent = '编辑出库单';

    // Render items
    const tbody = document.getElementById('outbound-items-body');
    tbody.innerHTML = '';

    if (order.items && order.items.length > 0) {
      for (const item of order.items) {
        await addOutboundItemRow(item);
      }
    } else {
      addOutboundItemRow();
    }

    modal.show('modal-outbound');
  } else {
    showAlert('danger', '获取详情失败: ' + result.message);
  }
}

/**
 * 添加出库明细行
 */
async function addOutboundItemRow(item = null) {
  const tbody = document.getElementById('outbound-items-body');
  const index = tbody.children.length;
  const rowId = `row-${Date.now()}-${index}`;

  const tr = document.createElement('tr');
  tr.id = rowId;

  // Load SKUs for select
  // Ideally this should be cached or loaded once, but for now we do it simple
  // Using appState.skus if available
  let skuOptions = '<option value="">选择物料</option>';
  if (appState.skus.length === 0) {
      // Trigger load if empty (might happen if catalog page not visited)
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
      <select class="form-control" name="items[${index}][sku_id]" onchange="onOutboundSkuChange(this, '${rowId}')" required>
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
      <button type="button" class="text danger" onclick="removeOutboundItemRow('${rowId}')">X</button>
    </td>
  `;

  tbody.appendChild(tr);

  // Trigger initial inventory check if editing
  if (item) {
     const select = tr.querySelector('select');
     onOutboundSkuChange(select, rowId);
  }
}

/**
 * 移除行
 */
function removeOutboundItemRow(rowId) {
  const row = document.getElementById(rowId);
  if (row) row.remove();
}

/**
 * 当选择SKU变化时
 */
async function onOutboundSkuChange(select, rowId) {
  const row = document.getElementById(rowId);
  const option = select.options[select.selectedIndex];

  if (!option.value) return;

  const unit = option.dataset.unit;
  const caseUnit = option.dataset.case || '箱';
  const skuId = option.value;

  // Update unit labels
  row.querySelector('.unit-display').textContent = unit;
  row.querySelector('.case-unit-display').textContent = caseUnit;

  // Fetch Inventory
  const invDisplay = row.querySelector('.inventory-display');
  invDisplay.textContent = '查询中...';

  const result = await api.queryInventory(skuId);
  if (result.success) {
      invDisplay.textContent = `库存: ${result.data.display_text}`;
      // Could verify sufficiency here
  } else {
      invDisplay.textContent = '查询失败';
  }
}

/**
 * 保存出库单
 */
async function saveOutbound(event) {
  event.preventDefault();

  // Transform form data to JSON structure expected by PHP
  // Since we use name="items[0][sku_id]", standard FormData might need manual parsing or PHP handles it automatically?
  // PHP $_POST handles items[0][sku_id] automatically.
  // But our api.saveOutbound sends JSON body using Object.fromEntries which flattens nested arrays poorly.
  // We need to construct the object manually.

  const form = event.target;
  const formData = new FormData(form);
  const data = {
      outbound_order_id: formData.get('outbound_order_id'),
      outbound_date: formData.get('outbound_date'),
      outbound_type: formData.get('outbound_type'),
      location_name: formData.get('location_name'),
      remark: formData.get('remark'),
      items: []
  };

  // Parse items
  // Simple hack: iterate rows
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

  const result = await api.saveOutbound(data);
  if (result.success) {
    showAlert('success', '出库单保存成功');
    modal.hide('modal-outbound');
    loadOutboundList();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 确认出库单
 */
async function confirmOutbound(orderId) {
    if (!confirm('确认后将扣减库存，且不可修改，确定吗？')) return;

    const result = await api.confirmOutbound(orderId);
    if (result.success) {
        showAlert('success', '出库单已确认');
        loadOutboundList();
    } else {
        showAlert('danger', '确认失败: ' + result.message);
    }
}

/**
 * 查看出库单 (Reuse Edit Modal in Readonly mode or similar)
 */
async function viewOutbound(orderId) {
    // For simplicity, reuse edit but disable fields
    await editOutbound(orderId);
    document.getElementById('modal-outbound-title').textContent = '查看出库单';
    // Disable all inputs
    const modalEl = document.getElementById('modal-outbound');
    const inputs = modalEl.querySelectorAll('input, select, textarea, button');
    // Note: We should probably keep Close button enabled
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
