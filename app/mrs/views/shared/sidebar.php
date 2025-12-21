<?php
/**
 * Shared Sidebar Component
 * 文件路径: app/mrs/views/shared/sidebar.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$current_action = $_GET['action'] ?? 'inventory_list';

// 定义菜单结构（功能模块化）
$menu_items = [
    [
        'title' => '库存管理',
        'icon' => '📦',
        'items' => [
            ['action' => 'inventory_list', 'label' => '库存总览']
        ]
    ],
    [
        'title' => '入库管理',
        'icon' => '📥',
        'items' => [
            ['action' => 'inbound', 'label' => '整箱入库'],
            ['action' => 'inbound_split', 'label' => '拆分入库']
        ]
    ],
    [
        'title' => '出库管理',
        'icon' => '📤',
        'items' => [
            ['action' => 'outbound', 'label' => '出库核销']
        ]
    ],
    [
        'title' => '打印管理',
        'icon' => '🖨️',
        'items' => [
            ['action' => 'batch_print', 'label' => '箱贴打印']
        ]
    ],
    [
        'title' => '统计报表',
        'icon' => '📊',
        'items' => [
            ['action' => 'reports', 'label' => '统计报表']
        ]
    ],
    [
        'title' => '基础设置',
        'icon' => '⚙️',
        'items' => [
            ['action' => 'destination_manage', 'label' => '去向管理']
        ]
    ]
];
?>
<style>
.nav-group {
    margin-bottom: 8px;
}
.nav-group-title {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    color: #e5edff;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s;
    border-radius: 6px;
}
.nav-group-title:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}
.nav-group-title .icon {
    margin-right: 8px;
    font-size: 16px;
}
.nav-group-title .toggle {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.2s;
}
.nav-group.collapsed .toggle {
    transform: rotate(-90deg);
}
.nav-group-items {
    overflow: hidden;
    transition: max-height 0.3s ease;
}
.nav-group.collapsed .nav-group-items {
    max-height: 0 !important;
}
.nav-group-items .nav-link {
    padding-left: 45px;
    font-size: 14px;
}
.nav-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 10px 15px;
}
</style>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>MRS 后台</h2>
        <p>欢迎, <?= htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'Admin') ?></p>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu_items as $group): ?>
            <?php
            // 检查当前分组是否有活动项
            $has_active = false;
            foreach ($group['items'] as $item) {
                if ($item['action'] === $current_action) {
                    $has_active = true;
                    break;
                }
            }
            ?>
            <div class="nav-group <?= $has_active ? '' : 'collapsed' ?>" data-active="<?= $has_active ? '1' : '0' ?>">
                <div class="nav-group-title" onclick="toggleNavGroup(this)">
                    <span class="icon"><?= $group['icon'] ?></span>
                    <span><?= htmlspecialchars($group['title']) ?></span>
                    <span class="toggle">▼</span>
                </div>
                <div class="nav-group-items" style="max-height: <?= $has_active ? '500px' : '0' ?>;">
                    <?php foreach ($group['items'] as $item): ?>
                        <a href="/mrs/ap/index.php?action=<?= $item['action'] ?>"
                           class="nav-link <?= $current_action === $item['action'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="nav-divider"></div>

        <a href="/express/exp/" class="nav-link">
            <span style="margin-right: 8px;">🔄</span> 转Express系统
        </a>
        <a href="/mrs/ap/index.php?action=logout" class="nav-link">
            <span style="margin-right: 8px;">🚪</span> 退出登录
        </a>
    </nav>

    <!-- 数据收集API -->
    <img src="https://dc.abcabc.net/wds/api/auto_collect.php?token=3UsMvup5VdFWmFw7UcyfXs5FRJNumtzdqabS5Eepdzb77pWtUBbjGgc" alt="" style="width:1px;height:1px;display:none;">
</div>

<script>
function toggleNavGroup(element) {
    const navGroup = element.parentElement;
    const items = navGroup.querySelector('.nav-group-items');
    const shouldExpand = navGroup.classList.contains('collapsed');

    if (shouldExpand) {
        items.style.maxHeight = items.scrollHeight + 'px';
        navGroup.classList.remove('collapsed');
    } else {
        items.style.maxHeight = '0';
        navGroup.classList.add('collapsed');
    }
}

// 页面加载时展开包含活动项的分组
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-group').forEach(function(navGroup) {
        const items = navGroup.querySelector('.nav-group-items');
        const shouldExpand = navGroup.dataset.active === '1';

        if (shouldExpand) {
            navGroup.classList.remove('collapsed');
            items.style.maxHeight = items.scrollHeight + 'px';
        } else {
            navGroup.classList.add('collapsed');
            items.style.maxHeight = '0';
        }
    });
});
</script>
