<?php
/**
 * Backend Batch Detail Page
 * 文件路径: app/express/views/batch_detail.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;

if (empty($batch_id)) {
    die('批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    die('批次不存在');
}

$packages = express_get_packages_by_batch($pdo, $batch_id, 'all');
$content_summary = express_get_content_summary($pdo, $batch_id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次详情 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>批次详情: <?= htmlspecialchars($batch['batch_name']) ?></h1>
            <div class="header-actions">
                <a href="/express/exp/index.php?action=batch_list" class="btn btn-secondary">返回列表</a>
            </div>
        </header>

        <div class="content-wrapper">
            <!-- 批次信息卡片 -->
            <div class="info-card">
                <h2>批次信息</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">批次ID:</span>
                        <span class="info-value"><?= $batch['batch_id'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">状态:</span>
                        <span class="info-value">
                            <span class="badge badge-<?= $batch['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= $batch['status'] === 'active' ? '进行中' : '已关闭' ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">创建时间:</span>
                        <span class="info-value"><?= $batch['created_at'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">创建人:</span>
                        <span class="info-value"><?= htmlspecialchars($batch['created_by'] ?? '-') ?></span>
                    </div>
                </div>

                <?php if ($batch['notes']): ?>
                    <div class="info-notes">
                        <strong>备注:</strong>
                        <p><?= nl2br(htmlspecialchars($batch['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $batch['total_count'] ?></div>
                    <div class="stat-label">总包裹数</div>
                </div>
                <div class="stat-card stat-verified">
                    <div class="stat-number"><?= $batch['verified_count'] ?></div>
                    <div class="stat-label">已核实</div>
                </div>
                <div class="stat-card stat-counted">
                    <div class="stat-number"><?= $batch['counted_count'] ?></div>
                    <div class="stat-label">已清点</div>
                </div>
                <div class="stat-card stat-adjusted">
                    <div class="stat-number"><?= $batch['adjusted_count'] ?></div>
                    <div class="stat-label">已调整</div>
                </div>
            </div>

            <!-- 批量导入区域 -->
            <div class="bulk-import-section">
                <h2>批量导入快递单号</h2>
                <form id="bulk-import-form">
                    <div class="form-group">
                        <label for="tracking_numbers">快递单号列表（每行一个）:</label>
                        <textarea id="tracking_numbers" class="form-control" rows="10"
                                  placeholder="111111&#10;222222&#10;333333"></textarea>
                        <small class="form-text">
                            请每行输入一个快递单号，系统会自动过滤空行和重复单号
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">批量导入</button>
                </form>
                <div id="import-message" class="message" style="display: none; margin-top: 15px;"></div>
            </div>

            <!-- 包裹列表 -->
            <div class="packages-section">
                <h2>包裹列表 (共 <?= count($packages) ?> 个)</h2>
                <div id="update-message" class="message" style="display: none;"></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>包裹ID</th>
                            <th>快递单号</th>
                            <th>状态</th>
                            <th>内容备注</th>
                            <th>调整备注</th>
                            <th>创建时间</th>
                            <th>核实时间</th>
                            <th>清点时间</th>
                            <th>调整时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($packages)): ?>
                            <tr>
                                <td colspan="9" class="text-center">暂无包裹数据</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($packages as $package): ?>
                                <tr>
                                    <td><?= $package['package_id'] ?></td>
                                    <td><?= htmlspecialchars($package['tracking_number']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $package['package_status'] ?>">
                                            <?php
                                            $status_map = [
                                                'pending' => '待处理',
                                                'verified' => '已核实',
                                                'counted' => '已清点',
                                                'adjusted' => '已调整'
                                            ];
                                            echo $status_map[$package['package_status']] ?? $package['package_status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($package['content_note'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($package['adjustment_note'] ?? '-') ?></td>
                                    <td><?= $package['created_at'] ? date('Y-m-d H:i', strtotime($package['created_at'])) : '-' ?></td>
                                    <td><?= $package['verified_at'] ? date('Y-m-d H:i', strtotime($package['verified_at'])) : '-' ?></td>
                                    <td><?= $package['counted_at'] ? date('Y-m-d H:i', strtotime($package['counted_at'])) : '-' ?></td>
                                    <td><?= $package['adjusted_at'] ? date('Y-m-d H:i', strtotime($package['adjusted_at'])) : '-' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-edit-content"
                                                data-package-id="<?= $package['package_id'] ?>"
                                                data-current-note="<?= htmlspecialchars($package['content_note'] ?? '', ENT_QUOTES) ?>">
                                            修改内容
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 内容备注统计 -->
            <div class="packages-section" style="margin-top: 20px;">
                <h2>批次内物品内容统计</h2>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th style="width: 70%;">内容备注</th>
                        <th style="width: 30%;">数量（单）</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($content_summary)): ?>
                        <tr>
                            <td colspan="2" class="text-center">暂无内容备注数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($content_summary as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['content_note']) ?></td>
                                <td><?= $item['package_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('bulk-import-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const trackingNumbers = document.getElementById('tracking_numbers').value;
            const messageDiv = document.getElementById('import-message');

            if (!trackingNumbers.trim()) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '请输入至少一个快递单号';
                messageDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('/express/exp/index.php?action=bulk_import_save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: <?= $batch_id ?>,
                        tracking_numbers: trackingNumbers
                    })
                });

                const data = await response.json();

                if (data.success) {
                    let msg = `导入成功！导入: ${data.data.imported} 个，重复: ${data.data.duplicates} 个`;
                    if (data.data.errors.length > 0) {
                        msg += `，失败: ${data.data.errors.length} 个`;
                    }

                    messageDiv.className = 'message success';
                    messageDiv.textContent = msg;
                    messageDiv.style.display = 'block';

                    document.getElementById('tracking_numbers').value = '';

                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '导入失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误：' + error.message;
                messageDiv.style.display = 'block';
            }
        });

        // 修改内容备注
        document.querySelectorAll('.btn-edit-content').forEach(button => {
            button.addEventListener('click', async () => {
                const packageId = button.getAttribute('data-package-id');
                const currentNote = button.getAttribute('data-current-note') || '';
                const newNote = prompt('请输入新的内容备注', currentNote);

                const messageDiv = document.getElementById('update-message');
                messageDiv.style.display = 'none';

                if (newNote === null) {
                    return;
                }

                if (newNote.trim() === '') {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = '内容备注不能为空';
                    messageDiv.style.display = 'block';
                    return;
                }

                try {
                    const resp = await fetch('/express/exp/index.php?action=update_content_note', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            package_id: packageId,
                            content_note: newNote.trim()
                        })
                    });

                    const data = await resp.json();

                    if (!data.success) {
                        messageDiv.className = 'message error';
                        messageDiv.textContent = data.message || '更新失败';
                        messageDiv.style.display = 'block';
                        return;
                    }

                    // 更新行内容
                    const row = button.closest('tr');
                    if (row) {
                        row.querySelectorAll('td')[3].textContent = newNote.trim();
                        button.setAttribute('data-current-note', newNote.trim());
                    }

                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';

                    // 刷新统计信息
                    setTimeout(() => window.location.reload(), 800);
                } catch (error) {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = '网络错误：' + error.message;
                    messageDiv.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>
