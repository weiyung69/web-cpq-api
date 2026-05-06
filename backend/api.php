<?php
error_reporting(0);
ini_set('display_errors', 0);

// --- CORS & Headers ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit(http_response_code(200)); }

// --- 基础配置 ---
$dataDir = __DIR__ . "/data";
$method  = $_SERVER["REQUEST_METHOD"];

// 获取请求类型（默认为 orders）
$type = isset($_GET['type']) ? $_GET['type'] : 'orders';

/**
 * 💡 以后如果增加新的 JSON 文件（如 products.json）
 * 只需在下面的数组里加上 'products' 即可
 */
$allowedFiles = ['orders', 'cpq']; 

if (!in_array($type, $allowedFiles)) {
    echo json_encode(["error" => "Invalid type"]);
    exit(http_response_code(403));
}

$dataFile = $dataDir . "/" . $type . ".json";

// 初始化环境
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));

// --- 1. 处理 GET (读取) ---
if ($method === "GET") {
    echo file_get_contents($dataFile) ?: json_encode([]);
    exit;
}

// --- 2. 处理 POST (写入) ---
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        echo json_encode(["error" => "Invalid JSON"]);
        exit(http_response_code(400));
    }

    /**
     * 💡 逻辑区分：
     * 'orders' 使用 [追加模式] (保存历史记录)
     * 'cpq' 或其他配置类文件 使用 [覆盖模式] (保存最新设置)
     */
    if ($type === 'orders') {
        $currentData = json_decode(file_get_contents($dataFile), true) ?: [];
        // 如果是订单，我们帮它加个服务器时间
        $input["server_time"] = date("Y-m-d H:i:s");
        $currentData[] = $input;
        $finalData = $currentData;
    } else {
        // 配置类文件直接保存传过来的整个 JSON 对象
        $finalData = $input;
    }

    // 执行写入
    if (file_put_contents($dataFile, json_encode($finalData, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "success", "saved_to" => $type]);
    } else {
        echo json_encode(["error" => "Write failed"]);
        exit(http_response_code(500));
    }
    exit;
}