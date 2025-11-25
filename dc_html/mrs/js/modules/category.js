import { appState, showAlert, escapeHtml, modal } from './core.js';
import { categoryAPI } from './api.js';

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
        <button class="text" data-action="editCategory" data-category-id="${category.category_id}">编辑</button>
        <button class="text danger" data-action="deleteCategory" data-category-id="${category.category_id}">删除</button>
      </td>
    </tr>
  `).join('');
}

export function showNewCategoryModal() {
  document.getElementById('form-category').reset();
  document.getElementById('category-id').value = '';
  document.getElementById('modal-category-title').textContent = '新增品类';
  modal.show('modal-category');
}

export async function saveCategory(event) {
  const form = document.getElementById('form-category');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  const result = await categoryAPI.saveCategory(data);
  if (result.success) {
    showAlert('success', '品类保存成功');
    modal.hide('modal-category');
    loadCategories();
  } else {
    showAlert('danger', '保存失败: ' + result.message);
  }
}

export async function editCategory(categoryId) {
  const result = await categoryAPI.getCategoryDetail(categoryId);

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

export async function deleteCategory(categoryId) {
  if (!confirm('确定要删除这个品类吗?')) {
    return;
  }

  const result = await categoryAPI.deleteCategory(categoryId);
  if (result.success) {
    showAlert('success', '品类删除成功');
    loadCategories();
  } else {
    showAlert('danger', '删除失败: ' + result.message);
  }
}

export async function loadCategoryFilterOptions() {
    const result = await categoryAPI.getCategories();
    if (result.success) {
      const catalogSelect = document.getElementById('catalog-filter-category');
      if (catalogSelect) {
        const currentVal = catalogSelect.value;
        catalogSelect.innerHTML = '<option value="">全部品类</option>' +
          result.data.map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
        if (currentVal) {
          catalogSelect.value = currentVal;
        }
      }

      const inventorySelect = document.getElementById('inventory-filter-category');
      if (inventorySelect) {
        const currentVal = inventorySelect.value;
        inventorySelect.innerHTML = '<option value="">全部品类</option>' +
          result.data.map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
        if (currentVal) {
          inventorySelect.value = currentVal;
        }
      }
    }
  }
