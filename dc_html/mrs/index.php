<?php
/**
 * MRS Package Management System - Public Router
 * 文件路径: dc_html/mrs/index.php
 * 说明: 面向外部访问的入口，委托到 app/mrs/index.php 进行路由
 */

// 定义系统入口标识
define('MRS_ENTRY', true);

// 定义项目根目录 (dc_html 的上级目录)
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// 委托到核心路由
require_once PROJECT_ROOT . '/app/mrs/index.php';
