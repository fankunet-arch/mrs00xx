<?php
/**
 * MRS 物料收发管理系统 - 登录页面
 * 文件路径: backend/login_view.php
 */

// 定义入口常量
define('MRS_ENTRY', true);

// 加载配置文件
require_once __DIR__ . '/../app/mrs/config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 如果已登录,跳转到首页
if (is_user_logged_in()) {
    header('Location: /backend/sku_management.html');
    exit;
}

// 获取错误消息(如果有)
$error = $_GET['error'] ?? '';
$errorMessage = '';

switch ($error) {
    case 'invalid':
        $errorMessage = '用户名或密码错误';
        break;
    case 'inactive':
        $errorMessage = '账户未激活,请联系管理员';
        break;
    case 'system':
        $errorMessage = '系统错误,请稍后重试';
        break;
    case 'logout':
        $errorMessage = '您已成功退出登录';
        break;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - MRS 物料收发管理系统</title>
    <style>
        :root {
            --primary: #1f7aec;
            --primary-dark: #1565c0;
            --danger: #ef4444;
            --success: #10b981;
            --text: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "PingFang SC", "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: var(--primary);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .logo-icon svg {
            width: 36px;
            height: 36px;
            fill: white;
        }

        h1 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--text-light);
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 122, 236, 0.1);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .remember-me label {
            margin: 0;
            font-weight: normal;
            font-size: 14px;
            color: var(--text-light);
            cursor: pointer;
        }

        .forgot-password {
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        button[type="submit"]:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(31, 122, 236, 0.3);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        button[type="submit"]:disabled {
            background: var(--border);
            cursor: not-allowed;
            transform: none;
        }

        .footer-links {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 7h-4V5c0-1.103-.897-2-2-2h-4c-1.103 0-2 .897-2 2v2H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V9c0-1.103-.897-2-2-2zM10 5h4v2h-4V5zm10 13H4V9h16v9z"/>
                    <path d="M12 11c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zm0 4c-.551 0-1-.449-1-1s.449-1 1-1 1 .449 1 1-.449 1-1 1z"/>
                </svg>
            </div>
            <h1>MRS 物料收发管理系统</h1>
            <p class="subtitle">请登录以继续</p>
        </div>

        <?php if ($errorMessage): ?>
        <div class="alert <?php echo ($error === 'logout' ? 'alert-success' : 'alert-danger'); ?> show" id="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>

        <form id="login-form" method="POST" action="../dc_html/mrs/api.php?route=login_process">
            <div class="form-group">
                <label for="username">用户名</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autocomplete="username"
                    placeholder="请输入用户名"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="请输入密码"
                >
            </div>

            <div class="form-footer">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">记住我</label>
                </div>
                <a href="#" class="forgot-password">忘记密码?</a>
            </div>

            <button type="submit" id="submit-btn">
                登录
            </button>
        </form>

        <div class="footer-links">
            <a href="/">返回首页</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('login-form');
        const submitBtn = document.getElementById('submit-btn');
        const alert = document.getElementById('alert');

        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span>登录中...';
        });

        // 3秒后自动隐藏成功消息
        if (alert && alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
