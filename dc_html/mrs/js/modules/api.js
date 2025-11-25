/**
 * MRS Backend - API Module
 * API 调用封装
 */

/**
 * 通用API调用
 */
async function call(url, options = {}) {
  try {
    const separator = url.includes('?') ? '&' : '?';
    const finalUrl = `${url}${separator}_t=${Date.now()}`;

    const response = await fetch(finalUrl, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      }
    });

    if (response.status === 401) {
      window.location.href = 'login.php';
      return { success: false, message: '登录失效，正在跳转...' };
    }

    return await response.json();
  } catch (error) {
    console.error('API错误:', error);
    return { success: false, message: '网络错误' };
  }
}

// 批次相关 API
export const batchAPI = {
  async getBatches(filters = {}) {
    const params = new URLSearchParams(filters);
    return await call(`api.php?route=backend_batches&${params}`);
  },

  async getBatchDetail(batchId) {
    return await call(`api.php?route=backend_batch_detail&batch_id=${batchId}`);
  },

  async saveBatch(data) {
    return await call('api.php?route=backend_save_batch', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  async deleteBatch(batchId) {
    return await call('api.php?route=backend_delete_batch', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId })
    });
  },

  async getMergeData(batchId) {
    return await call(`api.php?route=backend_merge_data&batch_id=${batchId}`);
  },

  async confirmMerge(batchId, items, closeBatch = false) {
    return await call('api.php?route=backend_confirm_merge', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId, items, close_batch: closeBatch })
    });
  },

  async getRawRecords(batchId, skuId) {
    return await call(`api.php?route=backend_raw_records&batch_id=${batchId}&sku_id=${skuId}`);
  }
};

// SKU 相关 API
export const skuAPI = {
  async getSkus(filters = {}) {
    const cleanFilters = {};
    for (const [key, value] of Object.entries(filters)) {
      if (value !== '' && value !== null && value !== undefined) {
        cleanFilters[key] = value;
      }
    }

    const params = new URLSearchParams(cleanFilters);
    const queryString = params.toString();
    const url = `api.php?route=backend_skus${queryString ? '&' + queryString : ''}`;
    return await call(url);
  },

  async saveSku(data) {
    return await call('api.php?route=backend_save_sku', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  async deleteSku(skuId) {
    return await call('api.php?route=backend_delete_sku', {
      method: 'POST',
      body: JSON.stringify({ sku_id: skuId })
    });
  },

  async updateSkuStatus(skuId, status) {
    return await call('api.php?route=backend_save_sku', {
      method: 'POST',
      body: JSON.stringify({ sku_id: skuId, status: status })
    });
  },

  async getSkuHistory(skuId) {
    return await call(`api.php?route=backend_sku_history&sku_id=${skuId}`);
  },

  async importSkusText(text) {
    return await call('api.php?route=backend_import_skus_text', {
      method: 'POST',
      body: JSON.stringify({ text })
    });
  },

  async getSkuDetail(skuId) {
    return await call(`api.php?route=backend_sku_detail&sku_id=${skuId}`);
  }
};

// 品类相关 API
export const categoryAPI = {
  async getCategories(filters = {}) {
    const params = new URLSearchParams(filters);
    return await call(`api.php?route=backend_categories&${params}`);
  },

  async saveCategory(data) {
    return await call('api.php?route=backend_save_category', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  async deleteCategory(categoryId) {
    return await call('api.php?route=backend_delete_category', {
      method: 'POST',
      body: JSON.stringify({ category_id: categoryId })
    });
  },

  async getCategoryDetail(categoryId) {
    return await call(`api.php?route=backend_category_detail&category_id=${categoryId}`);
  }
};

// 库存相关 API
export const inventoryAPI = {
  async getInventoryList(filters = {}) {
    const params = new URLSearchParams(filters);
    return await call(`api.php?route=backend_inventory_list&${params}`);
  },

  async queryInventory(skuId) {
    return await call(`api.php?route=backend_inventory_query&sku_id=${skuId}`);
  },

  async quickOutbound(data) {
    return await call('api.php?route=backend_quick_outbound', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  async adjustInventory(data) {
    return await call('api.php?route=backend_adjust_inventory', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }
};

// 出库相关 API
export const outboundAPI = {
  async getOutboundList(filters = {}) {
    const params = new URLSearchParams(filters);
    return await call(`api.php?route=backend_outbound_list&${params}`);
  },

  async getOutboundDetail(orderId) {
    return await call(`api.php?route=backend_outbound_detail&order_id=${orderId}`);
  },

  async saveOutbound(data) {
    return await call('api.php?route=backend_save_outbound', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  async confirmOutbound(orderId) {
    return await call('api.php?route=backend_confirm_outbound', {
      method: 'POST',
      body: JSON.stringify({ order_id: orderId })
    });
  }
};

// 报表相关 API
export const reportsAPI = {
  async getReports(type, filters = {}) {
    const params = new URLSearchParams({ type, ...filters });
    return await call(`api.php?route=backend_reports&${params}`);
  }
};

// 系统相关 API
export const systemAPI = {
  async getSystemStatus() {
    return await call('api.php?route=backend_system_status');
  },

  async fixSystem(action) {
    return await call('api.php?route=backend_system_fix', {
      method: 'POST',
      body: JSON.stringify({ action })
    });
  }
};
