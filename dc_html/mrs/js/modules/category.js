/**
 * MRS Backend - Category Management Module
 * 品类管理模块
 */

import { modal, showAlert, escapeHtml, appState } from './core.js';
import { categoryAPI } from './api.js';

/**
 * 加载品类列表
 */
export async function loadCategories() {
  const filters = {
    search: document.getElementById('category-filter-search')?.value.trim() || ''
  };
  const result = await categoryAPI.getCategories(filters);
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
 * 显示新建品类模态框
 */
export function showNewCategoryModal() {
  document.getElementById('form-category').reset();
  document.getElementById('category-id').value = '';
  document.getElementById('modal-category-title').textContent = '新增品类';
  modal.show('modal-category');
}

/**
 * 保存品类
 */
export async function saveCategory(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  const result = await categoryAPI.saveCategory(data);
  if (result.success) {
    showAlert('success', '保存成功');
    modal.hide('modal-category');
    loadCategories();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

/**
 * 编辑品类
 */
export async function editCategory(categoryId) {
  const category = appState.categories.find(c => c.category_id === categoryId);
  if (!category) return;

  document.getElementById('category-id').value = category.category_id;
  document.getElementById('category-name').value = category.category_name;
  document.getElementById('category-code').value = category.category_code || '';
  document.getElementById('modal-category-title').textContent = '编辑品类';

  modal.show('modal-category');
}

/**
 * 删除品类
 */
export async function deleteCategory(categoryId) {
  if (!confirm('确定要删除该品类吗？')) return;

  const result = await categoryAPI.deleteCategory(categoryId);
  if (result.success) {
    showAlert('success', '删除成功');
    loadCategories();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}
