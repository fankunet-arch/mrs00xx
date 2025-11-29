<?php
/**
 * Backend Batch List Page
 * 文件路径: app/express/views/batch_list.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batches = express_get_batches($pdo, 'all', 100);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次列表 - Express Backend</title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>批次列表</h1>
            <div class="header-actions">
                <a href="/express/exp/index.php?action=batch_create" class="btn btn-primary">创建新批次</a>
            </div>
        </header>

        <div class="content-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>批次ID</th>
                        <th>批次名称</th>
                        <th>状态</th>
                        <th>总包裹数</th>
                        <th>已核实</th>
                        <th>已清点</th>
                        <th>已调整</th>
                        <th>创建时间</th>
                        <th>创建人</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="10" class="text-center">暂无批次数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><?= $batch['batch_id'] ?></td>
                                <td><?= htmlspecialchars($batch['batch_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $batch['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= $batch['status'] === 'active' ? '进行中' : '已关闭' ?>
                                    </span>
                                </td>
                                <td><?= $batch['total_count'] ?></td>
                                <td><?= $batch['verified_count'] ?></td>
                                <td><?= $batch['counted_count'] ?></td>
                                <td><?= $batch['adjusted_count'] ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($batch['created_at'])) ?></td>
                                <td><?= htmlspecialchars($batch['created_by'] ?? '-') ?></td>
                                <td>
                                    <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch['batch_id'] ?>"
                                       class="btn btn-sm btn-info">详情</a>
                                    <a href="/express/exp/index.php?action=batch_edit&batch_id=<?= $batch['batch_id'] ?>"
                                       class="btn btn-sm btn-primary">编辑</a>
                                    <button type="button"
                                            class="btn btn-sm btn-danger btn-delete-batch"
                                            data-batch-id="<?= $batch['batch_id'] ?>">
                                        删除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="list-message" class="message" style="display: none;"></div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.btn-delete-batch').forEach(button => {
            button.addEventListener('click', async function() {
                const batchId = this.dataset.batchId;

                if (!confirm('确认删除该批次及其所有包裹记录？')) {
                    return;
                }

                const messageBox = document.getElementById('list-message');

                try {
                    const response = await fetch('/express/exp/index.php?action=batch_delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ batch_id: batchId })
                    });

                    const data = await response.json();

                    messageBox.className = 'message ' + (data.success ? 'success' : 'error');
                    messageBox.textContent = data.message || (data.success ? '删除成功' : '删除失败');
                    messageBox.style.display = 'block';

                    if (data.success) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 800);
                    }
                } catch (error) {
                    messageBox.className = 'message error';
                    messageBox.textContent = '删除失败：' + error.message;
                    messageBox.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>
