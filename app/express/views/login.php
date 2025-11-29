<?php
/**
 * Backend Login Page
 * 文件路径: app/express/views/login.php
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
    <title>登录 - Express Backend</title>
    <link rel="stylesheet" href="/dc_html/express/css/backend.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>快递单管理系统</h1>
        <h2>后台登录</h2>

        <form id="login-form">
            <div class="form-group">
                <label for="username">用户名:</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="password">密码:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">登录</button>
        </form>

        <div id="message" class="message" style="display: none;"></div>

        <p class="login-note">测试账号: admin / admin123</p>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('message');

            try {
                const response = await fetch('/express/exp/index.php?action=do_login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = '登录成功，正在跳转...';
                    messageDiv.style.display = 'block';

                    setTimeout(() => {
                        window.location.href = '/express/exp/index.php?action=batch_list';
                    }, 500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '登录失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误，请重试';
                messageDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>
