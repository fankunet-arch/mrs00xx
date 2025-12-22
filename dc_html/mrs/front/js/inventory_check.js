(function () {
    const apiUrl = '/mrs/index.php?action=inventory_check_api';
    const form = document.getElementById('search-form');
    const keywordInput = document.getElementById('keyword');
    const onlyUncheckedInput = document.getElementById('only-unchecked');
    const resultList = document.getElementById('result-list');
    const createSection = document.getElementById('create-section');
    const createFeedback = document.getElementById('create-feedback');
    const createBtn = document.getElementById('create-btn');

    async function callApi(payload) {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        return response.json();
    }

    function formatDateTime(value) {
        if (!value) return '未盘点';
        return value.replace('T', ' ');
    }

    function setLoading(message) {
        resultList.innerHTML = `<div class="card">${message}</div>`;
    }

    function renderEmpty() {
        resultList.innerHTML = '<div class="card empty">没有匹配记录。现场有但系统没有？请补录。</div>';
        createSection.classList.remove('hidden');
    }

    function renderItems(items) {
        resultList.innerHTML = '';
        if (!items || items.length === 0) {
            renderEmpty();
            return;
        }

        createSection.classList.add('hidden');

        items.forEach((item) => {
            const card = document.createElement('div');
            card.className = 'card';

            const title = document.createElement('h3');
            title.className = 'card-title';
            title.textContent = item.box_number || '未填箱号';
            card.appendChild(title);

            const badges = document.createElement('div');
            badges.className = 'badges';
            const batchBadge = document.createElement('span');
            batchBadge.className = 'badge muted';
            batchBadge.textContent = item.batch_name;
            badges.appendChild(batchBadge);

            const statusBadge = document.createElement('span');
            statusBadge.className = item.last_counted_at ? 'badge success' : 'badge';
            statusBadge.textContent = item.last_counted_at ? '已盘点' : '未盘点';
            badges.appendChild(statusBadge);
            card.appendChild(badges);

            const grid = document.createElement('div');
            grid.className = 'card-content';
            grid.innerHTML = `
                <div><div class="label">追踪单号</div><div>${item.tracking_number || '--'}</div></div>
                <div><div class="label">位置</div><div>${item.warehouse_location || '未填写'}</div></div>
                <div><div class="label">数量</div><div>${item.quantity ?? '--'}</div></div>
                <div><div class="label">入库时间</div><div>${formatDateTime(item.inbound_time)}</div></div>
                <div><div class="label">最后盘点</div><div>${formatDateTime(item.last_counted_at)}</div></div>
                <div class="full"><div class="label">备注</div><div>${item.content_note || '—'}</div></div>
            `;
            card.appendChild(grid);

            const actions = document.createElement('div');
            actions.className = 'actions';

            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'ghost-btn';
            confirmBtn.textContent = '确认';
            confirmBtn.addEventListener('click', () => handleConfirm(item.ledger_id));

            const updateBtn = document.createElement('button');
            updateBtn.className = 'primary-btn';
            updateBtn.textContent = '修改数量';
            updateBtn.addEventListener('click', () => handleUpdate(item.ledger_id, item.quantity));

            const voidBtn = document.createElement('button');
            voidBtn.className = 'danger-btn';
            voidBtn.textContent = '丢失';
            voidBtn.addEventListener('click', () => handleVoid(item.ledger_id));

            actions.append(confirmBtn, updateBtn, voidBtn);
            card.appendChild(actions);

            resultList.appendChild(card);
        });
    }

    async function handleSearch(event) {
        event?.preventDefault();
        setLoading('查询中...');
        try {
            const payload = {
                op: 'search',
                keyword: keywordInput.value.trim(),
                only_unchecked: onlyUncheckedInput.checked ? 1 : 0,
            };
            const res = await callApi(payload);
            if (!res.success) {
                resultList.innerHTML = `<div class="card empty">${res.message || '查询失败'}</div>`;
                createSection.classList.add('hidden');
                return;
            }
            renderItems(res.data || []);
        } catch (e) {
            resultList.innerHTML = `<div class="card empty">查询异常：${e.message}</div>`;
        }
    }

    async function handleConfirm(ledgerId) {
        if (!ledgerId) return;
        const res = await callApi({ op: 'confirm', ledger_id: ledgerId });
        if (res.success) {
            handleSearch();
        } else {
            alert(res.message || '确认失败');
        }
    }

    async function handleUpdate(ledgerId, currentQty) {
        if (!ledgerId) return;
        const input = prompt('输入修正后的数量', currentQty ?? '');
        if (input === null) return;
        const qty = input.trim();
        if (qty === '') {
            alert('数量不能为空');
            return;
        }
        const res = await callApi({ op: 'update', ledger_id: ledgerId, qty });
        if (res.success) {
            handleSearch();
        } else {
            alert(res.message || '修改失败');
        }
    }

    async function handleVoid(ledgerId) {
        if (!ledgerId) return;
        const reason = prompt('请输入丢失原因（可留空使用默认文案）');
        if (reason === null) return;
        const res = await callApi({ op: 'void', ledger_id: ledgerId, reason });
        if (res.success) {
            handleSearch();
        } else {
            alert(res.message || '操作失败');
        }
    }

    async function handleCreate() {
        const boxNumber = document.getElementById('create-box-number').value.trim();
        const qty = document.getElementById('create-qty').value.trim();
        const contentNote = document.getElementById('create-content-note').value.trim();
        const location = document.getElementById('create-location').value.trim();

        if (!boxNumber) {
            createFeedback.textContent = '请填写箱号后再提交';
            return;
        }

        createBtn.disabled = true;
        createFeedback.textContent = '提交中...';
        try {
            const res = await callApi({
                op: 'create',
                box_number: boxNumber,
                qty: qty === '' ? null : qty,
                content_note: contentNote,
                warehouse_location: location,
            });

            if (res.success) {
                createFeedback.textContent = '登记成功，已使用系统时间生成批次与单号。';
                document.getElementById('create-box-number').value = '';
                document.getElementById('create-qty').value = '';
                document.getElementById('create-content-note').value = '';
                document.getElementById('create-location').value = '';
                handleSearch();
            } else {
                createFeedback.textContent = res.message || '登记失败';
            }
        } catch (e) {
            createFeedback.textContent = `登记异常：${e.message}`;
        } finally {
            createBtn.disabled = false;
        }
    }

    form.addEventListener('submit', handleSearch);
    createBtn.addEventListener('click', handleCreate);

    // auto search on load
    handleSearch();
})();
