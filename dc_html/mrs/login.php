<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MRS 管理员登录</title>
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
    .form-group input {
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
    .error-msg {
      color: #ef4444;
      font-size: 0.875rem;
      text-align: center;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-title">MRS 管理员登录</div>

    <?php if (isset($_GET['error'])): ?>
      <div class="error-msg">
        <?php
          switch($_GET['error']) {
            case 'invalid': echo '用户名或密码错误'; break;
            case 'too_many_attempts': echo '尝试次数过多，请稍后再试'; break;
            case 'logout': echo '已安全注销'; break;
            default: echo '登录失败，请重试';
          }
        ?>
      </div>
    <?php endif; ?>

    <form action="api.php?route=login_process" method="POST">
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
          <input type="checkbox" name="remember" value="1"> 记住我
        </label>
      </div>
      <button type="submit" class="btn">登录</button>
    </form>
  </div>
</body>
</html>
