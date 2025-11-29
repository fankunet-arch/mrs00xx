<?php
/**
 * Backend Login Page
 * 文件路径: app/express/views/login.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$message = '';
$messageType = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $message = '用户名或密码错误';
            $messageType = 'error';
            break;
        case 'too_many_attempts':
            $message = '尝试次数过多，请稍后再试';
            $messageType = 'error';
            break;
        case 'system':
            $message = '系统错误，请稍后再试';
            $messageType = 'error';
            break;
        default:
            $message = '登录失败，请重试';
            $messageType = 'error';
            break;
    }
} elseif (isset($_GET['status']) && $_GET['status'] === 'logout') {
    $message = '已安全注销';
    $messageType = 'info';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Express 管理员登录</title>
  <style>
    :root {
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --bg: #f3f4f6;
      --text: #1f2937;
      --border: #e5e7eb;
    }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: system-ui, -apple-system, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .login-card {
      background: white;
      padding: 2rem;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
    }
    .login-title {
      font-size: 1.5rem;
      font-weight: bold;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }
    .form-group input[type="text"],
    .form-group input[type="password"] {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid var(--border);
      border-radius: 0.25rem;
      box-sizing: border-box;
    }
    .btn {
      width: 100%;
      padding: 0.75rem;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 0.25rem;
      font-weight: bold;
      cursor: pointer;
    }
    .btn:hover {
      background-color: var(--primary-hover);
    }
    .notice {
      font-size: 0.875rem;
      text-align: center;
      margin-top: 0.75rem;
      color: #6b7280;
    }
    .message {
      font-size: 0.875rem;
      text-align: center;
      margin-bottom: 1rem;
      padding: 0.75rem;
      border-radius: 0.25rem;
      display: none;
    }
    .message.error {
      display: block;
      color: #b91c1c;
      background: #fee2e2;
      border: 1px solid #fecaca;
    }
    .message.info {
      display: block;
      color: #2563eb;
      background: #e0e7ff;
      border: 1px solid #c7d2fe;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-title">Express 管理员登录</div>

    <?php if ($message): ?>
      <div id="server-message" class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div id="client-message" class="message"></div>

    <form id="login-form">
      <div class="form-group">
        <label for="username">用户名</label>
        <input type="text" id="username" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="form-group">
        <label>
          <input type="checkbox" name="remember" value="1" id="remember"> 记住我
        </label>
      </div>
      <button type="submit" class="btn">登录</button>
    </form>
    <div class="notice">使用与 MRS 相同的账户完成登录。</div>
  </div>

  <script>
    const form = document.getElementById('login-form');
    const clientMessage = document.getElementById('client-message');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clientMessage.className = 'message';
      clientMessage.style.display = 'none';

      const payload = {
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value,
        remember: document.getElementById('remember').checked ? 1 : 0,
      };

      try {
        const response = await fetch('/express/exp/index.php?action=do_login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (data.success) {
          clientMessage.className = 'message info';
          clientMessage.textContent = data.message || '登录成功，正在跳转...';
          clientMessage.style.display = 'block';
          window.location.href = (data.data && data.data.redirect) ? data.data.redirect : '/express/exp/index.php?action=batch_list';
        } else {
          clientMessage.className = 'message error';
          clientMessage.textContent = data.message || '登录失败，请重试';
          clientMessage.style.display = 'block';
        }
      } catch (error) {
        clientMessage.className = 'message error';
        clientMessage.textContent = '网络错误，请重试';
        clientMessage.style.display = 'block';
      }
    });
  </script>
</body>
</html>
