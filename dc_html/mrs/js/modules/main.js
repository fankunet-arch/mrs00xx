/**
 * MRS Backend - Main Entry Point
 * 入口文件：初始化应用和事件委托
 */

import { initDom, showPage, modal, showAlert, appState, escapeHtml } from './core.js';
import { systemAPI, reportsAPI } from './api.js';
import * as Inventory from './inventory.js';
import * as Batch from './batch.js';
import * as Sku from './sku.js';
import * as Category from './category.js';
import * as Outbound from './outbound.js';


// P1 Task: AI Prompt
export const SKU_IMPORT_PROMPT = `
你是一个WMS数据专员。请识别图片中的物料清单。
输出格式要求（使用 "|" 分隔）：
[品名] | [箱规/规格字符串] | [单位] | [品类]
注意：
- 箱规列原样输出图片内容（如 "500" 或 "500g/30包"），不要计算结果。
- 如果没有品类，留空。
- 不要输出表头和Markdown格式。
- 每一行末尾必须, 必须, 必须加上 #END# 作为结束符。
`;


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
  // 委托所有按钮点击事件
  document.addEventListener('click', async (e) => {
    // 优先处理页面切换
    const pageTarget = e.target.closest('[data-target]');
    if (pageTarget) {
      e.preventDefault();
      const pageName = pageTarget.dataset.target;
      if (pageName) {
        showPage(pageName);
      }
      return;
    }

    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;
    const skuId = target.dataset.skuId ? parseInt(target.dataset.skuId) : null;
    const batchId = target.dataset.batchId ? parseInt(target.dataset.batchId) : null;
    const categoryId = target.dataset.categoryId ? parseInt(target.dataset.categoryId) : null;


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

      case 'loadBatches':
        await Batch.loadBatches();
        break;
      case 'showNewBatchModal':
        await Batch.showNewBatchModal();
        break;
      case 'showBatchesPage':
        showPage('batches');
        break;
      case 'confirmAllMerge':
        await Batch.confirmAllMerge();
        break;
      case 'loadSkus':
        await Sku.loadSkus();
        break;
      case 'showImportSkuModal':
        await Sku.showImportSkuModal();
        break;
      case 'showNewSkuModal':
        await Sku.showNewSkuModal();
        break;
      case 'loadCategories':
        await Category.loadCategories();
        break;
      case 'showNewCategoryModal':
        await Category.showNewCategoryModal();
        break;
      case 'loadReports':
        await loadReports();
        break;
      case 'exportReport':
        await exportReport();
        break;
      case 'showAiPromptHelper':
        await showAiPromptHelper();
        break;
      case 'closeAiPromptHelper':
        await closeAiPromptHelper();
        break;
      case 'copyAiPrompt':
        await copyAiPrompt();
        break;
      case 'importSkus':
        await Sku.importSkus();
        break;
      case 'addOutboundItemRow':
        await Outbound.addOutboundItemRow();
        break;
      case 'viewBatch':
        if(batchId) await Batch.viewBatch(batchId);
        break;
      case 'showMergePage':
        if(batchId) await Batch.showMergePage(batchId);
        break;
      case 'editBatch':
        if(batchId) await Batch.editBatch(batchId);
        break;
      case 'deleteBatch':
        if(batchId) await Batch.deleteBatch(batchId);
        break;
      case 'toggleSkuStatus':
        const status = target.dataset.status;
        if(skuId && status) await Sku.toggleSkuStatus(skuId, status);
        break;
      case 'editSku':
        if(skuId) await Sku.editSku(skuId);
        break;
      case 'deleteSku':
        if(skuId) await Sku.deleteSku(skuId);
        break;
      case 'editCategory':
        if(categoryId) await Category.editCategory(categoryId);
        break;
      case 'deleteCategory':
        if(categoryId) await Category.deleteCategory(categoryId);
        break;
      case 'viewRawRecords':
        if(skuId) await viewRawRecords(skuId);
        break;
      case 'confirmItem':
        if(skuId) await Batch.confirmItem(skuId);
        break;
      case 'removeOutboundItemRow':
        const rowId = target.dataset.rowId;
        if(rowId) await Outbound.removeOutboundItemRow(rowId);
        break;
      default:
        console.warn('未知操作:', action);
    }
  });

  document.addEventListener('change', async (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;
    const rowId = target.dataset.rowId;

    switch (action) {
        case 'onOutboundSkuChange':
            if(rowId) await Outbound.onOutboundSkuChange(e.target, rowId);
            break;
    }
    });

  // 表单提交事件委托
  document.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formId = form.id;

    switch (formId) {
      case 'form-quick-outbound':
        const formDataQuick = new FormData(form);
        await Inventory.saveQuickOutbound(formDataQuick);
        break;
      case 'form-inventory-adjust':
        const formDataAdjust = new FormData(form);
        await Inventory.saveInventoryAdjustment(formDataAdjust);
        break;
      case 'form-batch':
        await Batch.saveBatch(e);
        break;
      case 'form-sku':
        await Sku.saveSku(e);
        break;
      case 'form-category':
        await Category.saveCategory(e);
        break;
      case 'form-outbound':
        await Outbound.saveOutbound(e);
        break;
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
      await Batch.loadBatches();
      break;
    case 'catalog':
      await Category.loadCategoryFilterOptions();
      await Sku.loadSkus();
      break;
    case 'categories':
      await Category.loadCategories();
      break;
    case 'inventory':
      await Category.loadCategoryFilterOptions(); // 为筛选器加载品类选项
      await Inventory.loadInventoryList();
      break;
    case 'reports':
      // await loadReports();
      break;
    case 'system':
      await loadSystemStatus();
      break;
  }
}

async function loadSystemStatus() {
  const container = document.getElementById('system-status-container');
  if (!container) return;

  container.innerHTML = '<p class="text-muted">正在检查系统健康状态...</p>';

  const result = await systemAPI.getSystemStatus();

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
            <button class="warning" data-action="fixSystem">🛠 修复数据库 (自动迁移)</button>
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

async function fixSystem() {
  if (!confirm('确定要执行系统修复操作吗？建议先备份数据库。')) {
    return;
  }

  const result = await systemAPI.fixSystem();

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

function showAiPromptHelper() {
    const textarea = document.getElementById('ai-prompt-text');
    if (textarea) {
      textarea.value = SKU_IMPORT_PROMPT;
    }
    modal.show('modal-ai-prompt');
  }

  function closeAiPromptHelper() {
    modal.hide('modal-ai-prompt');
  }

  function copyAiPrompt() {
    const textarea = document.getElementById('ai-prompt-text');
    if (!textarea) return;

    textarea.select();
    textarea.setSelectionRange(0, 99999);

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

  async function viewRawRecords(skuId) {
    showAlert('info', '查看原始记录功能开发中...');
  }

  async function exportReport() {
    showAlert('info', '导出报表功能开发中...');
  }

  async function loadReports() {
    showAlert('info', '报表功能正在开发中...');
  }


// 页面加载完成后初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

// 导出供调试使用
export { initApp, showPage, modal, showAlert, appState };
