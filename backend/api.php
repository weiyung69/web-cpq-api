<?php
/**
 * 解决 Azure 环境下的 CORS 问题及文件写入权限
 */

// 1. 禁用错误输出，防止报错信息干扰 Header 发送
error_reporting(0);
ini_set('display_errors', 0);

// 2. 设置 CORS 响应头
// 允许所有来源（测试阶段建议用 *，上线后可改为你的前端域名）
header("Access-Control-Allow-Origin: *");
// 允许的请求方法
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// 允许的请求头（添加了 X-Requested-With 等常用头）
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// 声明返回内容为 JSON
header("Content-Type: application/json");

// 3. 立即处理浏览器的 OPTIONS 预检请求
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// 4. 定义数据文件路径
$dataDir = __DIR__ . "/data";
$dataFile = $dataDir . "/orders.json";

// 5. 自动检查并创建 data 目录
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// 6. 初始化 JSON 文件
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

$method = $_SERVER["REQUEST_METHOD"];

// --- 处理 GET 请求 ---
if ($method === "GET") {
    $content = file_get_contents($dataFile);
    echo $content ? $content : json_encode([]);
    exit;
}

// --- 处理 POST 请求 ---
if ($method === "POST") {
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON received", "received" => $rawInput]);
        exit;
    }

    // 读取现有数据
    $currentData = file_get_contents($dataFile);
    $orders = json_decode($currentData, true) ?: [];
    
    // 添加时间戳并压入数组
    $input["time"] = date("Y-m-d H:i:s");
    $orders[] = $input;

    // 写入文件
    if (file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "ok", "message" => "Order saved"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Server failed to write to data/orders.json. Check folder permissions."]);
    }
    exit;
}

// --- 处理其他请求 ---
http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
