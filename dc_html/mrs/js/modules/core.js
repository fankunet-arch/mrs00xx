/**
 * MRS Backend - Core Module
 * 核心功能：全局状态、DOM管理、模态框、工具函数
 */

// 全局状态
export const appState = {
  currentPage: 'batches',
  batches: [],
  categories: [],
  skus: [],
  inventory: [],
  currentBatch: null,
  currentSku: null,
  currentCategory: null,
  rawRecords: [],
  currentRawRecordSkuId: null
};

// DOM 元素引用
export const dom = {};

/**
 * 初始化 DOM 引用
 */
export function initDom() {
  // 菜单项
  dom.menuItems = document.querySelectorAll('.menu-item');

  // 页面容器
  dom.pages = {
    batches: document.getElementById('page-batches'),
    merge: document.getElementById('page-merge'),
    catalog: document.getElementById('page-catalog'),
    categories: document.getElementById('page-categories'),
    inventory: document.getElementById('page-inventory'),
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
    aiPrompt: document.getElementById('modal-ai-prompt'),
    skuHistory: document.getElementById('modal-sku-history'),
    quickOutbound: document.getElementById('modal-quick-outbound'),
    inventoryAdjust: document.getElementById('modal-inventory-adjust'),
    rawRecordEdit: document.getElementById('modal-raw-record-edit')
  };
}

/**
 * 模态框管理
 */
export const modal = {
  show(modalId) {
    const modalEl = dom.modals[modalId] || document.getElementById(modalId);
    if (modalEl) {
      modalEl.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
  },

  hide(modalId) {
    const modalEl = dom.modals[modalId] || document.getElementById(modalId);
    if (modalEl) {
      modalEl.classList.remove('show');
      document.body.style.overflow = '';
    }
  }
};

/**
 * 显示通知消息
 */
export function showAlert(type, message) {
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
  let container = document.getElementById('alert-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'alert-container';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }
  return container;
}

/**
 * HTML 转义
 */
export function escapeHtml(unsafe) {
  if (unsafe === null || unsafe === undefined) return '';
  return String(unsafe)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * 页面导航
 */
export function showPage(pageName) {
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

  // 触发自定义事件，通知其他模块页面已切换
  document.dispatchEvent(new CustomEvent('pageChanged', { detail: { pageName } }));
}

/**
 * 获取 JSON 输入
 */
export function getJsonInput() {
  try {
    const formData = new FormData(event.target);
    const data = {};
    formData.forEach((value, key) => {
      data[key] = value;
    });
    return data;
  } catch (e) {
    return null;
  }
}
