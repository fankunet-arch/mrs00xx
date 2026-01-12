<?php
/**
 * SKU Edit/Create Page
 * 文件路径: app/mrs/views/sku_edit.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取SKU ID（如果是编辑）
$sku_id = $_GET['sku_id'] ?? null;
$sku = null;
$is_edit = false;

if ($sku_id) {
    $stmt = $pdo->prepare("SELECT * FROM mrs_sku WHERE sku_id = ?");
    $stmt->execute([$sku_id]);
    $sku = $stmt->fetch();

    if ($sku) {
        $is_edit = true;
        // 兼容旧字段
        if (empty($sku['sku_name_cn']) && !empty($sku['sku_name'])) {
            $sku['sku_name_cn'] = $sku['sku_name'];
        }
    }
}

$page_title = $is_edit ? '编辑SKU' : '新增SKU';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><?= $is_edit ? '✏️ 编辑SKU' : '➕ 新增SKU' ?></h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=sku_manage" class="btn btn-secondary">
                    ← 返回列表
                </a>
            </div>
        </div>

        <div class="content-wrapper">
            <form id="skuForm" method="POST">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="sku_id" value="<?= $sku['sku_id'] ?>">
                <?php endif; ?>

                <!-- 基本信息 -->
                <div class="form-section">
                    <div class="form-section-title">📝 基本信息</div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">
                                中文名称<span class="required">*</span>
                            </label>
                            <input type="text" name="sku_name_cn" class="form-control" required
                                   value="<?= htmlspecialchars($sku['sku_name_cn'] ?? '') ?>"
                                   placeholder="请输入产品中文名称">
                        </div>
                        <div>
                            <label class="form-label">西班牙语名称</label>
                            <input type="text" name="sku_name_es" class="form-control"
                                   value="<?= htmlspecialchars($sku['sku_name_es'] ?? '') ?>"
                                   placeholder="请输入产品西班牙语名称">
                        </div>
                        <div>
                            <label class="form-label">SKU编码</label>
                            <input type="text" name="sku_code" class="form-control"
                                   value="<?= htmlspecialchars($sku['sku_code'] ?? '') ?>"
                                   placeholder="请输入SKU编码">
                        </div>
                        <div>
                            <label class="form-label">条码</label>
                            <input type="text" name="barcode" class="form-control"
                                   value="<?= htmlspecialchars($sku['barcode'] ?? '') ?>"
                                   placeholder="请输入产品条码">
                        </div>
                    </div>
                </div>

                <!-- 分类信息 -->
                <div class="form-section">
                    <div class="form-section-title">📂 分类信息</div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">产品类别</label>
                            <select name="product_category" class="form-control">
                                <option value="">请选择</option>
                                <option value="packaging" <?= ($sku['product_category'] ?? '') === 'packaging' ? 'selected' : '' ?>>包材</option>
                                <option value="raw_material" <?= ($sku['product_category'] ?? '') === 'raw_material' ? 'selected' : '' ?>>原物料</option>
                                <option value="semi_finished" <?= ($sku['product_category'] ?? '') === 'semi_finished' ? 'selected' : '' ?>>半成品</option>
                                <option value="finished_product" <?= ($sku['product_category'] ?? '') === 'finished_product' ? 'selected' : '' ?>>成品</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">品牌名称</label>
                            <input type="text" name="brand_name" class="form-control"
                                   value="<?= htmlspecialchars($sku['brand_name'] ?? '') ?>"
                                   placeholder="请输入品牌名称">
                        </div>
                    </div>
                </div>

                <!-- 规格信息 -->
                <div class="form-section">
                    <div class="form-section-title">📦 规格信息</div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">单品规格</label>
                            <input type="text" name="spec_info" class="form-control"
                                   value="<?= htmlspecialchars($sku['spec_info'] ?? '') ?>"
                                   placeholder="例如：500ml、1kg等">
                        </div>
                        <div>
                            <label class="form-label">保质期效（月）</label>
                            <input type="number" name="shelf_life_months" class="form-control"
                                   value="<?= htmlspecialchars($sku['shelf_life_months'] ?? '') ?>"
                                   placeholder="请输入保质期月数" min="1">
                            <div class="help-text">产品的保质期（以月为单位）</div>
                        </div>
                        <div>
                            <label class="form-label">标准单位</label>
                            <input type="text" name="standard_unit" class="form-control"
                                   value="<?= htmlspecialchars($sku['standard_unit'] ?? '件') ?>"
                                   placeholder="例如：件、个、瓶等">
                        </div>
                        <div>
                            <label class="form-label">箱单位名称</label>
                            <input type="text" name="case_unit_name" class="form-control"
                                   value="<?= htmlspecialchars($sku['case_unit_name'] ?? '箱') ?>"
                                   placeholder="例如：箱、盒等">
                        </div>
                        <div>
                            <label class="form-label">整箱规格（每箱数量）</label>
                            <input type="number" name="case_to_standard_qty" class="form-control"
                                   value="<?= htmlspecialchars($sku['case_to_standard_qty'] ?? '') ?>"
                                   placeholder="例如：12" step="0.01" min="0">
                            <div class="help-text">一箱包含多少个标准单位</div>
                        </div>
                        <div>
                            <label class="form-label">默认货架位置</label>
                            <input type="text" name="default_shelf_location" class="form-control"
                                   value="<?= htmlspecialchars($sku['default_shelf_location'] ?? '') ?>"
                                   placeholder="例如：A01、B02等">
                        </div>
                    </div>
                </div>

                <!-- 供应商信息 -->
                <div class="form-section">
                    <div class="form-section-title">🌍 供应商信息</div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">供货商所属国家</label>
                            <select name="supplier_country" class="form-control">
                                <option value="">请选择</option>
                                <option value="china" <?= ($sku['supplier_country'] ?? '') === 'china' ? 'selected' : '' ?>>🇨🇳 中国</option>
                                <option value="spain" <?= ($sku['supplier_country'] ?? '') === 'spain' ? 'selected' : '' ?>>🇪🇸 西班牙</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">状态</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= ($sku['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>✓ 使用中</option>
                                <option value="inactive" <?= ($sku['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>✗ 已停用</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 备注 -->
                <div class="form-section">
                    <div class="form-section-title">📄 备注信息</div>
                    <div class="form-grid-full">
                        <label class="form-label">备注</label>
                        <textarea name="remark" class="form-control" rows="4"
                                  placeholder="请输入备注信息..."><?= htmlspecialchars($sku['remark'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- 操作按钮 -->
                <div class="form-actions">
                    <a href="/mrs/ap/index.php?action=sku_manage" class="btn btn-secondary">取消</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $is_edit ? '💾 保存更改' : '➕ 创建SKU' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
        document.getElementById('skuForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('/mrs/ap/index.php?action=sku_save_api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    await showAlert(
                        '<?= $is_edit ? "SKU更新成功！" : "SKU创建成功！" ?>',
                        '成功',
                        'success'
                    );
                    window.location.href = '/mrs/ap/index.php?action=sku_manage';
                } else {
                    await showAlert('保存失败: ' + result.message, '错误', 'error');
                }
            } catch (error) {
                await showAlert('网络错误: ' + error.message, '错误', 'error');
            }
        });
    </script>
</body>
</html>
