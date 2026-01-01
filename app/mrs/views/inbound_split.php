<?php
/**
 * MRS View: inbound_split.php
 * 拆分入库界面（Express包裹拆分入库到SKU系统）
 * 文件路径: app/mrs/views/inbound_split.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取 Express 批次列表
$express_batches = mrs_get_express_batches();

// 过滤批次：只显示有可拆分包裹的批次
$available_batches = [];
foreach ($express_batches as $batch) {
    // 跳过没有清点包裹的批次
    if ($batch['counted_count'] == 0) {
        continue;
    }

    // 检查是否还有可拆分的包裹
    $available_pkgs = mrs_get_splittable_packages($pdo, $batch['batch_name']);
    if (count($available_pkgs) > 0) {
        $batch['available_count'] = count($available_pkgs);
        $available_batches[] = $batch;
    }
}

// 选中的批次名称
$selected_batch = $_GET['batch'] ?? '';
$available_packages = [];

if (!empty($selected_batch)) {
    // 获取该批次中可拆分的包裹
    $available_packages = mrs_get_splittable_packages($pdo, $selected_batch);
}

// 设置页面变量
$page_title = '拆分入库 - MRS 系统';
$page_css = ['/mrs/ap/css/inbound_split.css'];

// 包含页面头部
include MRS_VIEW_PATH . '/shared/header.php';
?>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>拆分入库（Express批次 → SKU系统）</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inbound" class="btn btn-secondary">切换到整箱入库</a>
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>📦 拆分入库流程:</strong><br>
                1. 选择 Express 批次（已清点且有产品明细的包裹）<br>
                2. 勾选要拆分入库的包裹（系统自动读取产品明细）<br>
                3. 预览拆分结果<br>
                4. 确认入库到 SKU 系统（支持后续按件出库）
            </div>

            <div class="warning-box">
                <strong>⚠️ 注意事项:</strong><br>
                • 拆分入库后，包裹将转换为 SKU 收货记录，快递单号可释放给其他货物<br>
                • 拆分入库的货物支持按件出库（散装 + 整箱混合）<br>
                • 如需整箱入库，请使用"整箱入库"功能
            </div>

            <!-- 第一步：选择批次 -->
            <div class="form-group">
                <label for="batch_select">选择 Express 批次 <span class="required">*</span></label>
                <select id="batch_select" class="form-control" onchange="window.location.href='/mrs/ap/index.php?action=inbound_split&batch=' + this.value">
                    <option value="">-- 请选择批次 --</option>
                    <?php if (empty($available_batches)): ?>
                        <option value="" disabled>暂无可拆分入库的批次</option>
                    <?php else: ?>
                        <?php foreach ($available_batches as $batch): ?>
                            <option value="<?= htmlspecialchars($batch['batch_name']) ?>"
                                    <?= $batch['batch_name'] === $selected_batch ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['batch_name']) ?>
                                (可拆分: <?= $batch['available_count'] ?> 个包裹)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small class="form-text text-gray">
                    只显示有产品明细且未拆分入库的包裹
                </small>
            </div>

            <?php if (!empty($selected_batch)): ?>
                <?php if (empty($available_packages)): ?>
                    <div class="empty-state">
                        <div class="empty-state-text">批次 "<?= htmlspecialchars($selected_batch) ?>" 中没有可拆分的包裹</div>
                        <small>（可能已全部拆分入库或没有产品明细）</small>
                    </div>
                <?php else: ?>
                    <!-- 第二步：选择包裹 -->
                    <form id="splitInboundForm">
                        <input type="hidden" name="batch_name" value="<?= htmlspecialchars($selected_batch) ?>">

                        <h3 class="mt-30">可拆分包裹列表 (共 <?= count($available_packages) ?> 个)</h3>

                        <div class="select-all-container">
                            <label>
                                <input type="checkbox" id="selectAll">
                                全选 / 全不选
                            </label>
                        </div>

                        <!-- 货架位置输入 (三段式) -->
                        <div class="shelf-location-box">
                            <label class="shelf-location-label">
                                <span class="text-orange-dark">📦 货架位置 (可选)</span>
                                <small class="shelf-hint-text">格式: 排号-架号-层号 (每段2位数字)</small>
                            </label>
                            <div class="shelf-input-row">
                                <input type="text"
                                       id="shelf_row"
                                       class="form-control shelf-segment shelf-segment-input"
                                       placeholder="排"
                                       maxlength="2"
                                       autocomplete="off">
                                <span class="shelf-separator">-</span>
                                <input type="text"
                                       id="shelf_rack"
                                       class="form-control shelf-segment shelf-segment-input"
                                       placeholder="架"
                                       maxlength="2"
                                       autocomplete="off">
                                <span class="shelf-separator">-</span>
                                <input type="text"
                                       id="shelf_level"
                                       class="form-control shelf-segment shelf-segment-input"
                                       placeholder="层"
                                       maxlength="2"
                                       autocomplete="off">
                                <input type="hidden" id="shelf_location" name="shelf_location">
                            </div>
                            <small class="shelf-location-hint">
                                💡 此位置将应用到所有选中的包裹 (例如: 01-02-03)
                            </small>
                        </div>

                        <div class="package-list">
                            <?php foreach ($available_packages as $pkg): ?>
                                <div class="package-item">
                                    <div class="package-header">
                                        <input type="checkbox"
                                               name="selected_packages[]"
                                               value="<?= htmlspecialchars(json_encode([
                                                   'batch_name' => $pkg['batch_name'],
                                                   'tracking_number' => $pkg['tracking_number'],
                                                   'package_id' => $pkg['package_id'],
                                                   'items' => $pkg['items'] ?? []
                                               ])) ?>"
                                               class="package-checkbox"
                                               data-items='<?= htmlspecialchars(json_encode($pkg['items'] ?? [])) ?>'>
                                        <div class="flex-1">
                                            <strong>单号:</strong> <?= htmlspecialchars($pkg['tracking_number']) ?> |
                                            <strong>清点时间:</strong> <?= date('Y-m-d H:i', strtotime($pkg['counted_at'])) ?>
                                        </div>
                                    </div>

                                    <div class="ml-30">
                                        <?php if (!empty($pkg['items']) && is_array($pkg['items'])): ?>
                                            <strong>产品明细:</strong><br>
                                            <?php foreach ($pkg['items'] as $item): ?>
                                                <span class="item-tag">
                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                    <?php if (!empty($item['quantity'])): ?>
                                                        ×<?= htmlspecialchars($item['quantity']) ?> 件
                                                    <?php endif; ?>
                                                </span>
                                                <?php if (!empty($item['expiry_date'])): ?>
                                                    <span class="item-tag expiry">
                                                        保质期: <?= htmlspecialchars($item['expiry_date']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted-lighter">无产品明细</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 第三步：预览拆分明细 -->
                        <div class="preview-box hidden" id="previewBox">
                            <h4>📋 拆分入库预览</h4>
                            <div id="previewContent"></div>
                        </div>

                        <div class="form-actions mt-20">
                            <button type="submit" class="btn btn-primary">确认拆分入库</button>
                            <button type="reset" class="btn btn-secondary">重置</button>
                        </div>
                    </form>

                    <div id="resultMessage" class="mt-15"></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php
// 设置内联JavaScript
$inline_js = <<<'JAVASCRIPT'
// 全选功能
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.package-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updatePreview();
});

// 监听单个包裹选择变化
document.querySelectorAll('.package-checkbox').forEach(cb => {
    cb.addEventListener('change', updatePreview);
});

// 更新预览
function updatePreview() {
    const checkboxes = document.querySelectorAll('.package-checkbox:checked');
    const previewBox = document.getElementById('previewBox');
    const previewContent = document.getElementById('previewContent');

    if (checkboxes.length === 0) {
        previewBox.classList.add('hidden');
        return;
    }

    // 收集所有选中的产品
    const allItems = {};
    let totalPackages = 0;

    checkboxes.forEach(cb => {
        const items = JSON.parse(cb.dataset.items || '[]');
        totalPackages++;

        items.forEach(item => {
            const name = item.product_name;
            const qty = parseFloat(item.quantity || 0);

            if (allItems[name]) {
                allItems[name] += qty;
            } else {
                allItems[name] = qty;
            }
        });
    });

    // 生成预览内容
    let html = '<p><strong>将拆分 ' + totalPackages + ' 个包裹，入库以下物料：</strong></p>';

    for (const [name, qty] of Object.entries(allItems)) {
        html += '<div class="preview-item">• <strong>' + name + '</strong>: ' + qty + ' 件</div>';
    }

    html += '<p class="preview-hint"><small>这些物料将创建为 SKU 收货记录，可在后台管理中匹配 SKU 并确认入库。</small></p>';

    previewContent.innerHTML = html;
    previewBox.classList.remove('hidden');
}

// 提交表单
document.getElementById('splitInboundForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const selectedPackages = formData.getAll('selected_packages[]');

    if (selectedPackages.length === 0) {
        document.getElementById('resultMessage').innerHTML =
            '<div class="message error">请至少选择一个包裹</div>';
        return;
    }

    // 确认对话框
    if (!confirm('确认要拆分入库 ' + selectedPackages.length + ' 个包裹吗？\n\n拆分后包裹将转换为 SKU 收货记录。')) {
        return;
    }

    // 解析选中的包裹数据
    const packages = selectedPackages.map(p => JSON.parse(p));

    const data = {
        batch_name: formData.get('batch_name'),
        packages: packages,
        shelf_location: formData.get('shelf_location') || ''
    };

    // 显示加载中
    document.getElementById('resultMessage').innerHTML =
        '<div class="message info">正在处理拆分入库，请稍候...</div>';

    fetch('/mrs/ap/index.php?action=inbound_split_save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        const messageDiv = document.getElementById('resultMessage');

        if (result.success) {
            let msg = '<div class="message success">拆分入库成功！<br>';
            msg += '批次ID: ' + result.batch_id + '<br>';
            msg += '创建了 ' + result.records_created + ' 条收货记录。<br>';

            if (result.errors && result.errors.length > 0) {
                msg += '<br><strong>部分错误:</strong><br>' + result.errors.join('<br>');
            }

            msg += '<br><br>请前往 <a href="/mrs/ap/index.php?action=backend_manage">后台管理</a> 匹配 SKU 并确认入库。';
            msg += '</div>';
            messageDiv.innerHTML = msg;

            // 3秒后刷新页面
            setTimeout(() => {
                window.location.href = '/mrs/ap/index.php?action=inbound_split&batch=' + encodeURIComponent(data.batch_name);
            }, 3000);
        } else {
            messageDiv.innerHTML = '<div class="message error">拆分入库失败: ' + (result.message || '未知错误') + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('resultMessage').innerHTML =
            '<div class="message error">网络错误: ' + error + '</div>';
    });
});

// 三段式货架位置输入处理
(function() {
    const rowInput = document.getElementById('shelf_row');
    const rackInput = document.getElementById('shelf_rack');
    const levelInput = document.getElementById('shelf_level');
    const hiddenInput = document.getElementById('shelf_location');

    if (!rowInput || !rackInput || !levelInput || !hiddenInput) return;

    const segments = [rowInput, rackInput, levelInput];

    // 更新隐藏字段
    function updateShelfLocation() {
        const row = rowInput.value.trim();
        const rack = rackInput.value.trim();
        const level = levelInput.value.trim();

        // 如果都为空，隐藏字段也为空
        if (!row && !rack && !level) {
            hiddenInput.value = '';
            return;
        }

        // 组合成格式化字符串
        const parts = [];
        if (row) parts.push(row.padStart(2, '0'));
        if (rack) parts.push(rack.padStart(2, '0'));
        if (level) parts.push(level.padStart(2, '0'));

        hiddenInput.value = parts.join('-');
    }

    // 为每个输入框添加事件监听
    segments.forEach((input, index) => {
        // 只允许输入数字
        input.addEventListener('input', function(e) {
            // 过滤非数字字符
            this.value = this.value.replace(/\D/g, '');

            // 限制最多2位
            if (this.value.length > 2) {
                this.value = this.value.substring(0, 2);
            }

            // 更新隐藏字段
            updateShelfLocation();

            // 输入满2位后立即跳转到下一个输入框
            if (this.value.length === 2 && index < segments.length - 1) {
                // 使用setTimeout确保DOM更新后再跳转
                setTimeout(() => {
                    segments[index + 1].focus();
                    segments[index + 1].select();
                }, 0);
            }
        });

        // 同时监听keyup事件以处理单字符输入的跳转
        input.addEventListener('keyup', function(e) {
            // 如果已经是2位数字且不是导航键，跳转
            if (this.value.length === 2 && index < segments.length - 1) {
                const navKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Backspace', 'Delete'];
                if (!navKeys.includes(e.key)) {
                    setTimeout(() => {
                        segments[index + 1].focus();
                        segments[index + 1].select();
                    }, 0);
                }
            }
        });

        // 支持键盘导航
        input.addEventListener('keydown', function(e) {
            // Backspace: 如果当前为空，跳到上一个
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                e.preventDefault();
                segments[index - 1].focus();
                segments[index - 1].value = '';
                updateShelfLocation();
            }

            // 左箭头: 跳到上一个
            if (e.key === 'ArrowLeft' && this.selectionStart === 0 && index > 0) {
                e.preventDefault();
                segments[index - 1].focus();
                segments[index - 1].setSelectionRange(segments[index - 1].value.length, segments[index - 1].value.length);
            }

            // 右箭头: 跳到下一个
            if (e.key === 'ArrowRight' && this.selectionStart === this.value.length && index < segments.length - 1) {
                e.preventDefault();
                segments[index + 1].focus();
                segments[index + 1].setSelectionRange(0, 0);
            }
        });

        // 粘贴处理：自动拆分格式化字符串
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').trim();

            // 如果是格式化字符串（如 "01-02-03"）
            if (pasteData.includes('-')) {
                const parts = pasteData.split('-').map(p => p.trim().replace(/\D/g, ''));
                if (parts[0]) rowInput.value = parts[0].substring(0, 2);
                if (parts[1]) rackInput.value = parts[1].substring(0, 2);
                if (parts[2]) levelInput.value = parts[2].substring(0, 2);
                updateShelfLocation();
            } else {
                // 否则只粘贴数字到当前框
                const numbers = pasteData.replace(/\D/g, '');
                this.value = numbers.substring(0, 2);
                if (numbers.length > 2 && index < segments.length - 1) {
                    segments[index + 1].focus();
                }
                updateShelfLocation();
            }
        });
    });
})();
JAVASCRIPT;

// 包含页面尾部
include MRS_VIEW_PATH . '/shared/footer.php';
?>
