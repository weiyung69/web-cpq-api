<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$dataFile = __DIR__ . "/data/orders.json";

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($method === "GET") {
    echo file_get_contents($dataFile);
    exit;
}

if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    $orders = json_decode(file_get_contents($dataFile), true);
    $input["time"] = date("Y-m-d H:i:s");
    $orders[] = $input;

    file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT));
    echo json_encode(["status" => "ok"]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);