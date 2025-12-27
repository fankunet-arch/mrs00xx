<?php
/**
 * Backend Create Batch Page
 * 文件路径: app/express/views/batch_create.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建批次 - Express Backend</title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>创建新批次</h1>
            <small style="color: #666;">批次编号将自动生成（000-999循环）</small>
        </header>

        <div class="content-wrapper">
            <form id="batch-create-form" class="form-horizontal">
                <div class="form-group">
                    <label for="tracking_numbers">快递单号:</label>
                    <textarea id="tracking_numbers" name="tracking_numbers" class="form-control" rows="8"
                              placeholder="每行一个快递单号，或批量粘贴（可选格式：单号|有效期|数量）&#10;例如：&#10;SF1234567890&#10;YT9876543210|2025-12-31|5&#10;JD1122334455"></textarea>
                    <small class="form-text">可以创建空批次后再添加快递单号，也可以直接录入或批量导入</small>
                </div>

                <div class="form-group">
                    <label for="notes">备注:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"
                              placeholder="批次备注信息（可选）"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">创建批次</button>
                    <a href="/express/exp/index.php?action=batch_list" class="btn btn-secondary">返回列表</a>
                </div>
            </form>

            <div id="message" class="message" style="display: none;"></div>
        </div>
    </div>

    <script>
        document.getElementById('batch-create-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const trackingNumbersText = document.getElementById('tracking_numbers').value.trim();
            const notes = document.getElementById('notes').value.trim();
            const messageDiv = document.getElementById('message');

            // 解析快递单号
            const trackingNumbers = [];
            if (trackingNumbersText) {
                const lines = trackingNumbersText.split('\n');
                for (const line of lines) {
                    const trimmedLine = line.trim();
                    if (!trimmedLine) continue;

                    // 支持格式：单号|有效期|数量
                    const parts = trimmedLine.split('|').map(p => p.trim());
                    if (parts.length === 1) {
                        trackingNumbers.push(parts[0]);
                    } else {
                        trackingNumbers.push({
                            tracking_number: parts[0],
                            expiry_date: parts[1] || null,
                            quantity: parts[2] ? parseInt(parts[2]) : null
                        });
                    }
                }
            }

            try {
                const response = await fetch('/express/exp/index.php?action=batch_create_save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tracking_numbers: trackingNumbers,
                        notes: notes || null,
                        created_by: '<?= $_SESSION['user_login'] ?? 'admin' ?>'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.className = 'message success';
                    let msg = `批次 ${data.data.batch_name} 创建成功！`;
                    if (data.data.imported_count > 0) {
                        msg += ` 导入 ${data.data.imported_count} 个快递单号`;
                    }
                    if (data.data.duplicates > 0) {
                        msg += `，${data.data.duplicates} 个重复`;
                    }
                    messageDiv.textContent = msg + ' 正在跳转...';
                    messageDiv.style.display = 'block';

                    setTimeout(() => {
                        window.location.href = '/express/exp/index.php?action=batch_detail&batch_id=' + data.data.batch_id;
                    }, 1500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '创建失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误：' + error.message;
                messageDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>
