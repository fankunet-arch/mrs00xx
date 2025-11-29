# Express 本地验证与测试指南

## 路径与入口
- 前台入口：`http://127.0.0.1/express/index.php`（物理路径 `dc_html/express/index.php`）。
- 后台入口：`http://127.0.0.1/express/exp/index.php`（物理路径 `dc_html/express/exp/index.php`）。
- 代码根目录：`app/express`，入口文件通过 `EXPRESS_ENTRY` 保护并路由到 `actions`、`views` 与 `api`。

## 数据库配置
- 默认读取 `app/express/config_express/env_express.php`，如需本地调试可暂时改用 `bootstrap_mock.php` 或 `env_express_mock.php`，但交付前请恢复生产连接配置，避免指向测试库。

## 最低测试流程
1. 前台：选择批次、切换操作类型、提交快递单号，确认提示与历史记录正常展示。
2. 后台：登录后创建批次，查看详情后执行编辑与删除（删除含二次确认）。
3. 错误路径校验：访问未注册的 `action` 应返回 404/JSON 错误，不应暴露文件结构。

## 已执行检查
- 使用 `php -l` 对 `app/express` 下全部 PHP 文件进行语法检查，确认无语法错误。
