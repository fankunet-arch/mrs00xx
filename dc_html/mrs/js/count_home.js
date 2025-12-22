/**
 * MRS Count Home JavaScript
 * 文件路径: dc_html/mrs/js/count_home.js
 * 说明: 清点首页交互逻辑
 */

(function() {
    'use strict';

    // 更新当前时间
    function updateTime() {
        const now = new Date();
        const timeStr = now.toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
        const timeEl = document.getElementById('current-time');
        if (timeEl) {
            timeEl.textContent = timeStr;
        }
    }

    // 初始化时间显示
    updateTime();
    setInterval(updateTime, 1000);

    // 模态框元素
    const modal = document.getElementById('new-session-modal');
    const btnNewSession = document.getElementById('btn-new-session');
    const btnModalClose = document.getElementById('modal-close-btn');
    const btnModalCancel = document.getElementById('modal-cancel-btn');
    const btnModalConfirm = document.getElementById('modal-confirm-btn');

    // 表单元素
    const sessionNameInput = document.getElementById('session-name');
    const createdByInput = document.getElementById('created-by');
    const sessionRemarkInput = document.getElementById('session-remark');

    // 打开新建任务模态框
    if (btnNewSession) {
        btnNewSession.addEventListener('click', function() {
            modal.style.display = 'flex';
            sessionNameInput.value = '';
            createdByInput.value = '';
            sessionRemarkInput.value = '';
            sessionNameInput.focus();
        });
    }

    // 关闭模态框
    function closeModal() {
        modal.style.display = 'none';
    }

    if (btnModalClose) {
        btnModalClose.addEventListener('click', closeModal);
    }

    if (btnModalCancel) {
        btnModalCancel.addEventListener('click', closeModal);
    }

    // 点击模态框背景关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // 确认创建清点任务
    if (btnModalConfirm) {
        btnModalConfirm.addEventListener('click', function() {
            const sessionName = sessionNameInput.value.trim();

            if (!sessionName) {
                alert('请输入清点任务名称');
                sessionNameInput.focus();
                return;
            }

            // 禁用按钮防止重复提交
            btnModalConfirm.disabled = true;
            btnModalConfirm.textContent = '创建中...';

            // 发送请求创建任务
            const formData = new FormData();
            formData.append('session_name', sessionName);
            formData.append('created_by', createdByInput.value.trim());
            formData.append('remark', sessionRemarkInput.value.trim());

            fetch('/mrs/index.php?action=count_create_session', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 跳转到清点操作页面
                    window.location.href = '/mrs/index.php?action=count_ops&session_id=' + data.session_id;
                } else {
                    alert(data.message || '创建失败');
                    btnModalConfirm.disabled = false;
                    btnModalConfirm.textContent = '开始清点';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('网络错误，请重试');
                btnModalConfirm.disabled = false;
                btnModalConfirm.textContent = '开始清点';
            });
        });
    }

    // 继续清点按钮
    const btnsContinue = document.querySelectorAll('.btn-continue');
    btnsContinue.forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionId = this.getAttribute('data-session-id');
            window.location.href = '/mrs/index.php?action=count_ops&session_id=' + sessionId;
        });
    });

    // 查看报告按钮（未来功能）
    const btnsViewReport = document.querySelectorAll('.btn-view-report');
    btnsViewReport.forEach(btn => {
        btn.addEventListener('click', function() {
            alert('查看报告功能开发中');
        });
    });
})();
