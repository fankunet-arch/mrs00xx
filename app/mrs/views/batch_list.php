<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>MRS 管理系统 - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mrs/css/backend.css">
</head>
<body>
    <header>
        <div class="title"><?php echo htmlspecialchars($page_title); ?></div>
        <div class="user">
            欢迎, <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? '用户'); ?> | <a href="/mrs/be/index.php?action=logout">登出</a>
        </div>
    </header>
    <div class="layout">
        <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>
        <main class="content">

<?php
/**
 * Generates table header with sorting links.
 * @param string $column_name The database column name.
 * @param string $display_name The name to display.
 * @param string $current_sort The current sorting column.
 * @param string $current_order The current sorting order.
 * @return string HTML for the table header.
 */
function sortable_th($column_name, $display_name, $current_sort, $current_order)
{
    $order = ($current_sort === $column_name && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort === $column_name) {
        $icon = $current_order === 'ASC' ? ' <i class="fa fa-sort-asc"></i>' : ' <i class="fa fa-sort-desc"></i>';
    }
    return '<th><a href="?action=batch_list&sort=' . $column_name . '&order=' . $order . '">' . $display_name . $icon . '</a></th>';
}

/**
 * Determines the display properties for a batch based on its state.
 * @param array $batch The batch data array.
 * @return array An array containing class, badge_class, text, and button info.
 */
function get_batch_display_properties($batch)
{
    $status = $batch['batch_status'];
    $raw_record_count = $batch['raw_record_count'] ?? 0;
    $confirmed_item_count = $batch['confirmed_item_count'] ?? 0;

    $properties = [
        'row_class' => '',
        'badge_class' => 'badge-secondary',
        'status_text' => '未知',
        'button_text' => '查看',
        'button_class' => '',
        'action' => 'batch_detail'
    ];

    // 规则1: 批次建立后，没有任何收货记录时显示蓝色的"等待收货"（无按钮）
    if ($raw_record_count == 0) {
        $properties['badge_class'] = 'badge-primary';
        $properties['status_text'] = '等待收货';
        $properties['button_text'] = '';  // 不显示按钮
        $properties['button_class'] = '';
        $properties['action'] = '';
    }
    // 规则2: 存在前台清点收货时（有收货但没有全部确认入库的），显示红色的"收货中"
    elseif ($raw_record_count > 0 && in_array($status, ['draft', 'receiving', 'pending_merge'])) {
        $properties['row_class'] = 'row-danger';
        $properties['badge_class'] = 'badge-danger';
        $properties['status_text'] = '收货中';
        $properties['button_text'] = '确认入库';
        $properties['button_class'] = 'btn-warning';
        $properties['action'] = 'batch_detail';
    }
    // 规则3: 全部收货完成后显示绿色的"已收货"
    elseif ($status === 'confirmed' || $status === 'posted') {
        $properties['badge_class'] = 'badge-success';
        $properties['status_text'] = '已收货';
        $properties['button_text'] = '查看详情';
        $properties['button_class'] = 'btn-info';
        $properties['action'] = 'batch_detail';
    }
    else {
        // Default catch-all
        $properties['status_text'] = ucfirst($status);
        $properties['button_text'] = '查看';
        $properties['button_class'] = 'btn-secondary';
        $properties['action'] = 'batch_detail';
    }

    return $properties;
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">收货批次列表</h4>
                    <a href="?action=batch_create" class="btn btn-primary btn-sm float-right">创建新批次</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php echo sortable_th('batch_code', '参考号', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('batch_status', '状态', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('raw_record_count', '待确认数', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('created_at', '创建时间', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('updated_at', '更新时间', $sort_column, $sort_order); ?>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batches)) : ?>
                                    <tr>
                                        <td colspan="7" class="text-center">没有找到任何批次。</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($batches as $batch) :
                                        $props = get_batch_display_properties($batch);
                                    ?>
                                        <tr class="<?php echo $props['row_class']; ?>">
                                            <td><?php echo $batch['batch_id']; ?></td>
                                            <td><?php echo htmlspecialchars($batch['batch_code']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $props['badge_class']; ?>">
                                                    <?php echo $props['status_text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $batch['raw_record_count']; ?></td>
                                            <td><?php echo $batch['created_at']; ?></td>
                                            <td><?php echo $batch['updated_at']; ?></td>
                                            <td>
                                                <?php if (!empty($props['button_text'])): ?>
                                                    <a href="?action=<?php echo $props['action']; ?>&id=<?php echo $batch['batch_id']; ?>" class="btn <?php echo $props['button_class']; ?> btn-sm">
                                                        <?php echo $props['button_text']; ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </main>
    </div>
</body>
</html>