<aside>
    <div style="padding: 10px; text-align: center; font-size: 20px; color: #fff; font-weight: bold;">MRS 系统</div>
    <a href="/mrs/be/index.php?action=dashboard" class="menu-item <?php echo ($action === 'dashboard') ? 'active' : ''; ?>">
        <span>仪表盘</span>
    </a>
    <a href="/mrs/be/index.php?action=batch_list" class="menu-item <?php echo (strpos($action, 'batch_') === 0) ? 'active' : ''; ?>">
        <span>入库管理</span>
    </a>
    <a href="/mrs/be/index.php?action=outbound_list" class="menu-item <?php echo (strpos($action, 'outbound_') === 0) ? 'active' : ''; ?>">
        <span>出库管理</span>
    </a>
    <a href="/mrs/be/index.php?action=inventory_list" class="menu-item <?php echo (strpos($action, 'inventory_') === 0) ? 'active' : ''; ?>">
        <span>库存管理</span>
    </a>
    <a href="/mrs/be/index.php?action=sku_list" class="menu-item <?php echo (strpos($action, 'sku_') === 0) ? 'active' : ''; ?>">
        <span>物料管理</span>
    </a>
    <a href="/mrs/be/index.php?action=category_list" class="menu-item <?php echo (strpos($action, 'category_') === 0) ? 'active' : ''; ?>">
        <span>品类管理</span>
    </a>
    <a href="/mrs/be/index.php?action=reports" class="menu-item <?php echo ($action === 'reports') ? 'active' : ''; ?>">
        <span>数据报表</span>
    </a>
</aside>
