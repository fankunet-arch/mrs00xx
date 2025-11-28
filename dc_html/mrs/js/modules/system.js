/**
 * MRS Backend - System Maintenance Module
 * 系统维护模块
 */

import { showAlert } from './core.js';
import { systemAPI } from './api.js';

/**
 * 加载系统状态
 */
export async function loadSystemStatus() {
  const result = await systemAPI.getSystemStatus();
  const container = document.getElementById('system-status-container');
  if (!container) return;

  if (result.success) {
    container.innerHTML = `
      <div class="system-status">
        <p><strong>系统状态：</strong><span class="badge success">正常</span></p>
        <p><strong>数据库：</strong>${result.data.database || '未知'}</p>
        <p><strong>版本：</strong>${result.data.version || '1.0.0'}</p>
      </div>
    `;
  } else {
    container.innerHTML = `<p class="text-danger">系统检查失败: ${result.message}</p>`;
  }
}

/**
 * 修复系统
 */
export async function fixSystem() {
  if (!confirm('确定要执行系统修复吗？')) return;

  const result = await systemAPI.fixSystem();
  if (result.success) {
    showAlert('success', '修复完成');
    loadSystemStatus();
  } else {
    showAlert('danger', '修复失败: ' + result.message);
  }
}
