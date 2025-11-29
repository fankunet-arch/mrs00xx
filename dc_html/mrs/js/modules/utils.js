/**
 * MRS Backend - Utilities Module
 * 工具函数模块
 */

/**
 * 获取状态文本
 */
export function getStatusText(status) {
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
 * 获取状态徽章样式类
 */
export function getStatusBadgeClass(status) {
  const classMap = {
    'draft': 'secondary',
    'receiving': 'primary',
    'pending_merge': 'warning',
    'confirmed': 'success',
    'posted': 'success'
  };
  return classMap[status] || 'secondary';
}

/**
 * SKU 导入提示词
 */
export const SKU_IMPORT_PROMPT = `你是一个数据提取助手。请根据以下图片内容，提取收货单中的物料信息。

输出格式要求：
每行一个物料，格式为：[品名] | [箱规] | [单位] | [品类]

注意事项：
1. 品名：保留完整名称
2. 箱规：提取数量和单位（如：500g/30包）
3. 单位：箱/盒/包等
4. 品类：根据品名推断（茶叶/包材/五金/其他）

示例输出：
90-700注塑细磨砂杯 | 500 | 箱 | 包材
茉莉银毫 | 500g/30包 | 箱 | 茶叶`;
