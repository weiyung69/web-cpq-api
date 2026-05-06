<?php
error_reporting(0);
ini_set('display_errors', 0);

// --- CORS & Headers ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit(http_response_code(200)); }

$dataDir = __DIR__ . "/data";
$method  = $_SERVER["REQUEST_METHOD"];
$type = isset($_GET['type']) ? $_GET['type'] : 'orders';
$allowedFiles = ['orders', 'cpq']; 

if (!in_array($type, $allowedFiles)) {
    echo json_encode(["error" => "Invalid type"]);
    exit(http_response_code(403));
}

$dataFile = $dataDir . "/" . $type . ".json";
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));

// --- 1. 处理 GET ---
if ($method === "GET") {
    echo file_get_contents($dataFile) ?: json_encode([]);
    exit;
}

// --- 2. 处理 POST ---
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        echo json_encode(["error" => "Invalid JSON"]);
        exit(http_response_code(400));
    }

    /**
     * 💡 核心逻辑：智能判断保存模式
     */
    if ($type === 'cpq') {
        // CPQ 永远是 [全量覆盖]
        $finalData = $input;
    } else if ($type === 'orders') {
        // 判断是 Admin 还是 Frontend
        if (isset($input[0])) {
            // 情况 A: 传过来的是数组 [{}, {}...] -> 这是 Admin 在更新状态，执行 [全量覆盖]
            $finalData = $input;
        } else {
            // 情况 B: 传过来的是对象 {name:...} -> 这是 Frontend 提交新订单，执行 [追加模式]
            $currentData = json_decode(file_get_contents($dataFile), true) ?: [];
            
            // 自动补齐后端字段
            $input["server_time"] = date("Y-m-d H:i:s");
            $input["follow_up"] = false; // 新订单默认未跟进
            
            $currentData[] = $input;
            $finalData = $currentData;
        }
    }

    // 执行写入 (LOCK_EX 确保多人在改价或下单时文件不会写坏)
    if (file_put_contents($dataFile, json_encode($finalData, JSON_PRETTY_PRINT), LOCK_EX)) {
        echo json_encode(["status" => "success", "mode" => isset($input[0]) ? "overwrite" : "append"]);
    } else {
        echo json_encode(["error" => "Write failed"]);
        exit(http_response_code(500));
    }
    exit;
}