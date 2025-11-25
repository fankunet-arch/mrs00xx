/**
 * MRS Backend - Main Entry Point
 * 入口文件：初始化应用和事件委托
 */

import { initDom, showPage, modal, showAlert, appState } from './core.js';
import { batchAPI, skuAPI, categoryAPI } from './api.js';
import * as Inventory from './inventory.js';
import './compat.js'; // 导入兼容层，将函数暴露到全局

// 导出全局函数供 HTML 使用（过渡期）
window.MRS = window.MRS || {};

/**
 * 应用初始化
 */
async function initApp() {
  // 初始化 DOM 引用
  initDom();

  // 设置事件委托
  setupEventDelegation();

  // 加载初始页面
  showPage('batches');
}

/**
 * 设置事件委托系统
 */
function setupEventDelegation() {
  // 菜单切换事件委托
  document.addEventListener('click', async (e) => {
    const menuItem = e.target.closest('.menu-item');
    if (menuItem && menuItem.dataset.target) {
      showPage(menuItem.dataset.target);
      return;
    }
  });

  // 委托所有按钮点击事件
  document.addEventListener('click', async (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;
    const skuId = target.dataset.skuId ? parseInt(target.dataset.skuId) : null;
    const batchId = target.dataset.batchId ? parseInt(target.dataset.batchId) : null;

    // 根据 action 执行对应操作
    switch (action) {
      // 库存相关操作
      case 'viewHistory':
        if (skuId) await Inventory.viewSkuHistory(skuId);
        break;
      case 'quickOutbound':
        if (skuId) await Inventory.showQuickOutboundModal(skuId);
        break;
      case 'inventoryAdjust':
        if (skuId) await Inventory.showInventoryAdjustModal(skuId);
        break;
      case 'refreshInventory':
        await Inventory.refreshInventory();
        break;
      case 'searchInventory':
        await Inventory.loadInventoryList();
        break;

      // 模态框操作
      case 'closeModal':
        const modalId = target.dataset.modalId;
        if (modalId) modal.hide(modalId);
        break;

      default:
        console.warn('未知操作:', action);
    }
  });

  // 表单提交事件委托
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    const formId = form.id;

    // 处理库存相关表单
    if (formId === 'form-quick-outbound') {
      e.preventDefault();
      const formData = new FormData(form);
      await Inventory.saveQuickOutbound(formData);
    } else if (formId === 'form-inventory-adjust') {
      e.preventDefault();
      const formData = new FormData(form);
      await Inventory.saveInventoryAdjustment(formData);
    }
  });

  // 页面切换监听
  document.addEventListener('pageChanged', async (e) => {
    const { pageName } = e.detail;
    await loadPageData(pageName);
  });
}

/**
 * 加载页面数据
 */
async function loadPageData(pageName) {
  switch (pageName) {
    case 'batches':
      await window.loadBatches();
      break;
    case 'catalog':
      await loadCategoryFilterOptions();
      await window.loadSkus();
      break;
    case 'categories':
      await window.loadCategories();
      break;
    case 'inventory':
      await loadCategoryFilterOptions();
      await Inventory.loadInventoryList();
      break;
    case 'reports':
      await window.loadReports();
      break;
    case 'system':
      await window.loadSystemStatus();
      break;
    // merge 页面通过 showMergePage 函数单独加载
  }
}

/**
 * 加载品类筛选选项
 */
async function loadCategoryFilterOptions() {
  const result = await categoryAPI.getCategories();
  if (result.success) {
    // 更新 SKU 页面的筛选器
    const catalogSelect = document.getElementById('catalog-filter-category');
    if (catalogSelect) {
      const currentVal = catalogSelect.value;
      catalogSelect.innerHTML = '<option value="">全部品类</option>' +
        result.data.map(cat => `<option value="${cat.category_id}">${cat.category_name}</option>`).join('');
      if (currentVal) catalogSelect.value = currentVal;
    }

    // 更新库存页面的筛选器
    const inventorySelect = document.getElementById('inventory-filter-category');
    if (inventorySelect) {
      const currentVal = inventorySelect.value;
      inventorySelect.innerHTML = '<option value="">全部品类</option>' +
        result.data.map(cat => `<option value="${cat.category_id}">${cat.category_name}</option>`).join('');
      if (currentVal) inventorySelect.value = currentVal;
    }
  }
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

// 导出供调试使用
export { initApp, showPage, modal, showAlert, appState };
