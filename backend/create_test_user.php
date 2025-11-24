<?php
/**
 * MRS 物料收发管理系统 - 创建测试用户
 * 文件路径: backend/create_test_user.php
 * 说明: 用于创建测试用户(生产环境应删除此文件)
 *
 * 警告: 此文件仅用于开发和测试,生产环境必须删除!
 */

// 定义入口常量
define('MRS_ENTRY', true);

// 加载配置文件
require_once __DIR__ . '/../app/mrs/config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 检查是否已经通过POST提交
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$result = null;

if ($isPost) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');

    if ($username && $password && $email && $displayName) {
        $userId = create_user($username, $password, $email, $displayName);
        if ($userId) {
            $result = [
                'success' => true,
                'message' => "用户创建成功! 用户ID: {$userId}",
                'user_id' => $userId
            ];
        } else {
            $result = [
                'success' => false,
                'message' => '用户创建失败,请检查日志'
            ];
        }
    } else {
        $result = [
            'success' => false,
            'message' => '所有字段都是必填的'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建测试用户 - MRS</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "PingFang SC", Arial, sans-serif;
            background: #f5f7fb;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        h1 {
            color: #1f2937;
            margin-bottom: 10px;
        }

        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #1f7aec;
        }

        button {
            background: #1f7aec;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background: #1565c0;
        }

        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #1f7aec;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .example {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 13px;
        }

        .example h3 {
            margin-bottom: 10px;
            color: #374151;
        }

        .example ul {
            margin-left: 20px;
        }

        .example li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>创建测试用户</h1>

        <div class="warning">
            <strong>⚠️ 警告:</strong> 此页面仅用于开发和测试。生产环境中必须删除此文件!
        </div>

        <?php if ($result): ?>
            <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($result['message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">用户名 <span style="color:red;">*</span></label>
                <input type="text" id="username" name="username" required placeholder="如: admin">
            </div>

            <div class="form-group">
                <label for="password">密码 <span style="color:red;">*</span></label>
                <input type="password" id="password" name="password" required placeholder="请输入密码">
            </div>

            <div class="form-group">
                <label for="email">邮箱 <span style="color:red;">*</span></label>
                <input type="email" id="email" name="email" required placeholder="如: admin@example.com">
            </div>

            <div class="form-group">
                <label for="display_name">显示名称 <span style="color:red;">*</span></label>
                <input type="text" id="display_name" name="display_name" required placeholder="如: 系统管理员">
            </div>

            <button type="submit">创建用户</button>
        </form>

        <div class="example">
            <h3>示例测试用户:</h3>
            <ul>
                <li>用户名: admin</li>
                <li>密码: Admin123!@#</li>
                <li>邮箱: admin@mrs.local</li>
                <li>显示名称: 系统管理员</li>
            </ul>
        </div>

        <div class="links">
            <a href="login_view.php">返回登录页面</a> |
            <a href="sku_management.html">SKU管理</a>
        </div>
    </div>
</body>
</html>
