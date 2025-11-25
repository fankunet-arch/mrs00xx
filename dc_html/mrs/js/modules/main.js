/**
 * MRS Backend - Main Entry Point
 * 入口文件：初始化应用和事件委托
 */

import { initDom, showPage, modal, showAlert, appState } from './core.js';
import { batchAPI, skuAPI, categoryAPI } from './api.js';
import * as Inventory from './inventory.js';
import * as Batch from './batch.js';
import * as SKU from './sku.js';
import * as Category from './category.js';
import * as System from './system.js';
import * as Reports from './reports.js';

// 导出全局函数供 HTML 使用（过渡期）
window.MRS = window.MRS || {};

// 暴露模块函数到全局，使 HTML onclick 处理器可以访问
window.showPage = showPage;
window.modal = modal;
window.showAlert = showAlert;

// 批次管理
window.loadBatches = Batch.loadBatches;
window.showNewBatchModal = Batch.showNewBatchModal;
window.saveBatch = Batch.saveBatch;
window.editBatch = Batch.editBatch;
window.deleteBatch = Batch.deleteBatch;
window.viewBatch = Batch.viewBatch;
window.showMergePage = Batch.showMergePage;
window.confirmItem = Batch.confirmItem;
window.confirmAllMerge = Batch.confirmAllMerge;
window.viewRawRecords = Batch.viewRawRecords;

// SKU 管理
window.loadSkus = SKU.loadSkus;
window.showNewSkuModal = SKU.showNewSkuModal;
window.saveSku = SKU.saveSku;
window.editSku = SKU.editSku;
window.deleteSku = SKU.deleteSku;
window.toggleSkuStatus = SKU.toggleSkuStatus;
window.showImportSkuModal = SKU.showImportSkuModal;
window.showAiPromptHelper = SKU.showAiPromptHelper;
window.closeAiPromptHelper = SKU.closeAiPromptHelper;
window.copyAiPrompt = SKU.copyAiPrompt;
window.importSkus = SKU.importSkus;

// 品类管理
window.loadCategories = Category.loadCategories;
window.showNewCategoryModal = Category.showNewCategoryModal;
window.saveCategory = Category.saveCategory;
window.editCategory = Category.editCategory;
window.deleteCategory = Category.deleteCategory;

// 库存管理
window.loadInventoryList = Inventory.loadInventoryList;
window.refreshInventory = Inventory.refreshInventory;
window.viewSkuHistory = Inventory.viewSkuHistory;
window.showQuickOutboundModal = Inventory.showQuickOutboundModal;
window.saveQuickOutbound = Inventory.saveQuickOutbound;
window.showInventoryAdjustModal = Inventory.showInventoryAdjustModal;
window.saveInventoryAdjustment = Inventory.saveInventoryAdjustment;

// 报表
window.loadReports = Reports.loadReports;
window.exportReport = Reports.exportReport;

// 系统维护
window.loadSystemStatus = System.loadSystemStatus;
window.fixSystem = System.fixSystem;

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
      // 批次管理
      case 'loadBatches':
        await Batch.loadBatches();
        break;
      case 'showNewBatchModal':
        Batch.showNewBatchModal();
        break;
      case 'confirmAllMerge':
        await Batch.confirmAllMerge();
        break;
      case 'showBatchesPage':
        showPage('batches');
        break;

      // SKU 管理
      case 'loadSkus':
        await SKU.loadSkus();
        break;
      case 'showNewSkuModal':
        SKU.showNewSkuModal();
        break;
      case 'showImportSkuModal':
        SKU.showImportSkuModal();
        break;
      case 'importSkus':
        await SKU.importSkus();
        break;
      case 'showAiPromptHelper':
        SKU.showAiPromptHelper();
        break;
      case 'closeAiPromptHelper':
        SKU.closeAiPromptHelper();
        break;
      case 'copyAiPrompt':
        SKU.copyAiPrompt();
        break;

      // 品类管理
      case 'loadCategories':
        await Category.loadCategories();
        break;
      case 'showNewCategoryModal':
        Category.showNewCategoryModal();
        break;

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

      // 报表
      case 'loadReports':
        await Reports.loadReports();
        break;
      case 'exportReport':
        await Reports.exportReport();
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

    // 阻止默认提交行为
    e.preventDefault();

    // 处理不同表单
    switch (formId) {
      case 'form-batch':
        await Batch.saveBatch(e);
        break;
      case 'form-sku':
        await SKU.saveSku(e);
        break;
      case 'form-category':
        await Category.saveCategory(e);
        break;
      case 'form-quick-outbound':
        const quickOutboundData = new FormData(form);
        await Inventory.saveQuickOutbound(quickOutboundData);
        break;
      case 'form-inventory-adjust':
        const adjustData = new FormData(form);
        await Inventory.saveInventoryAdjustment(adjustData);
        break;
      default:
        console.warn('未知表单:', formId);
    }
  });

  // 页面切换监听
  document.addEventListener('pageChanged', async (e) => {
    const { pageName } = e.detail;
    await loadPageData(pageName);
  });

  // 搜索框回车键监听
  document.addEventListener('keypress', async (e) => {
    if (e.key === 'Enter') {
      const target = e.target;

      // 批次搜索
      if (target.id === 'filter-search' ||
          target.id === 'filter-date-start' ||
          target.id === 'filter-date-end' ||
          target.id === 'filter-status') {
        e.preventDefault();
        await Batch.loadBatches();
      }

      // SKU搜索
      if (target.id === 'catalog-filter-search' ||
          target.id === 'catalog-filter-category' ||
          target.id === 'catalog-filter-type') {
        e.preventDefault();
        await SKU.loadSkus();
      }

      // 品类搜索
      if (target.id === 'category-filter-search') {
        e.preventDefault();
        await Category.loadCategories();
      }

      // 库存搜索
      if (target.id === 'inventory-filter-search' ||
          target.id === 'inventory-filter-category') {
        e.preventDefault();
        await Inventory.loadInventoryList();
      }
    }
  });
}

/**
 * 加载页面数据
 */
async function loadPageData(pageName) {
  switch (pageName) {
    case 'batches':
      await Batch.loadBatches();
      break;
    case 'catalog':
      await loadCategoryFilterOptions();
      await SKU.loadSkus();
      break;
    case 'categories':
      await Category.loadCategories();
      break;
    case 'inventory':
      await loadCategoryFilterOptions();
      await Inventory.loadInventoryList();
      break;
    case 'reports':
      await Reports.loadReports();
      break;
    case 'system':
      await System.loadSystemStatus();
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
