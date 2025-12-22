(function () {
    const searchInput = document.getElementById('search-input');
    const onlyUncheckedInput = document.getElementById('only-unchecked');
    const cardContainer = document.getElementById('card-container');
    const toastEl = document.getElementById('toast');

    let keyword = '';
    let onlyUnchecked = false;
    let lastTimer = null;

    function debounceSearch() {
        clearTimeout(lastTimer);
        lastTimer = setTimeout(() => {
            keyword = searchInput.value.trim();
            fetchList();
        }, 300);
    }

    function showToast(msg) {
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        setTimeout(() => toastEl.classList.remove('show'), 1800);
    }

    function formatTime(value) {
        if (!value) return '--';
        return value.replace('T', ' ');
    }

    function countedToday(lastCountedAt) {
        if (!lastCountedAt) return false;
        const ts = new Date(lastCountedAt);
        const todayStart = new Date();
        todayStart.setHours(0, 0, 0, 0);
        return ts >= todayStart;
    }

    async function fetchList() {
        cardContainer.innerHTML = '<div class="meta">正在加载...</div>';
        try {
            const res = await fetch('/mrs/index.php?action=inventory_check_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    op: 'search',
                    keyword,
                    only_unchecked: onlyUnchecked ? 1 : 0
                })
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || '加载失败');
            }
            renderList(data.data.items || []);
        } catch (err) {
            cardContainer.innerHTML = `<div class="meta" style="color:#dc2626">${err.message}</div>`;
        }
    }

    function renderList(items) {
        cardContainer.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.innerHTML = '<div>没有找到包裹记录</div>';
            const createBtn = document.createElement('button');
            createBtn.className = 'create-btn';
            createBtn.textContent = '+ 登记新货';
            createBtn.addEventListener('click', openCreateDialog);
            empty.appendChild(createBtn);
            cardContainer.appendChild(empty);
            return;
        }

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'card';

            const header = document.createElement('div');
            header.className = 'card-header';

            const boxTitle = document.createElement('div');
            boxTitle.className = 'box-number';
            boxTitle.textContent = item.box_number || '无箱号';

            const pill = document.createElement('div');
            pill.className = 'status-pill';
            if (countedToday(item.last_counted_at)) {
                pill.classList.add('status-done');
                pill.textContent = '今日已盘✅';
            } else {
                pill.textContent = '待盘点';
            }

            header.appendChild(boxTitle);
            header.appendChild(pill);
            card.appendChild(header);

            const metaLines = [
                `单号: ${item.tracking_number || '--'}`,
                `批次: ${item.batch_name || '--'}`,
                `位置: ${item.warehouse_location || '未标记'}`,
                `入库: ${formatTime(item.inbound_time)}`
            ];
            metaLines.forEach(text => {
                const div = document.createElement('div');
                div.className = 'meta';
                div.textContent = text;
                card.appendChild(div);
            });

            const note = document.createElement('div');
            note.className = 'note';
            note.textContent = item.content_note || '无备注';
            card.appendChild(note);

            const qty = document.createElement('div');
            qty.className = 'meta';
            qty.textContent = `数量: ${item.quantity ?? '--'}`;
            card.appendChild(qty);

            const actions = document.createElement('div');
            actions.className = 'actions';

            const btnConfirm = document.createElement('button');
            btnConfirm.className = 'btn btn-confirm';
            btnConfirm.textContent = '确认';
            btnConfirm.addEventListener('click', () => handleAction('confirm', item));

            const btnUpdate = document.createElement('button');
            btnUpdate.className = 'btn btn-update';
            btnUpdate.textContent = '修改';
            btnUpdate.addEventListener('click', () => handleUpdate(item));

            const btnVoid = document.createElement('button');
            btnVoid.className = 'btn btn-void';
            btnVoid.textContent = '丢失';
            btnVoid.addEventListener('click', () => handleVoid(item));

            actions.append(btnConfirm, btnUpdate, btnVoid);
            card.appendChild(actions);

            cardContainer.appendChild(card);
        });
    }

    async function handleAction(op, item, extra = {}) {
        const payload = Object.assign({ op, ledger_id: item.ledger_id }, extra);
        try {
            const res = await fetch('/mrs/index.php?action=inventory_check_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || '操作失败');
            showToast('操作成功');
            fetchList();
        } catch (err) {
            showToast(err.message);
        }
    }

    function handleUpdate(item) {
        const qtyStr = prompt('请输入实际数量', item.quantity ?? '');
        if (qtyStr === null) return;
        const qty = qtyStr.trim();
        if (qty === '' || isNaN(Number(qty))) {
            showToast('请输入有效数量');
            return;
        }
        handleAction('update', item, { qty: qty });
    }

    function handleVoid(item) {
        const confirmVoid = confirm('确认标记为丢失/作废？');
        if (!confirmVoid) return;
        const reason = prompt('丢失原因（可留空）', '盘点确认丢失');
        handleAction('void', item, { reason: reason || undefined });
    }

    function openCreateDialog() {
        const boxNum = prompt('箱号（必填）');
        if (!boxNum || !boxNum.trim()) {
            showToast('箱号必填');
            return;
        }
        const content = prompt('内容/备注', '');
        const qty = prompt('数量（可选）', '');
        const location = prompt('仓库位置（可选）', '');
        const batchName = prompt('批次号（可选，不填自动生成）', '');
        const tracking = prompt('单号/运单号（可选，不填自动生成）', '');

        createNewItem({
            box_num: boxNum.trim(),
            content_note: content ? content.trim() : '',
            qty: qty && qty.trim() !== '' ? qty.trim() : null,
            warehouse_location: location ? location.trim() : '',
            batch_name: batchName ? batchName.trim() : null,
            tracking_number: tracking ? tracking.trim() : null
        });
    }

    async function createNewItem(payload) {
        try {
            const res = await fetch('/mrs/index.php?action=inventory_check_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ op: 'create' }, payload))
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || '创建失败');
            showToast('登记成功');
            fetchList();
        } catch (err) {
            showToast(err.message);
        }
    }

    searchInput.addEventListener('input', debounceSearch);
    onlyUncheckedInput.addEventListener('change', () => {
        onlyUnchecked = onlyUncheckedInput.checked;
        fetchList();
    });

    fetchList();
})();
