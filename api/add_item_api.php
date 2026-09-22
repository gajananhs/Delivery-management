<?php
/**
 * POST Add Item API Handler
 * Registers a new item into the item master list, from the Live Register
 * tab's "+ Register New Item" form.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    $name = trim($input['name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing item name"]);
        exit;
    }

    $code = trim($input['code'] ?? '');
    if ($code === '') {
        $code = 'ITM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
    $unit = trim($input['unit'] ?? '');

    $existing = $pdo->prepare("SELECT code FROM items WHERE code = :code");
    $existing->execute([':code' => $code]);
    if ($existing->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Item code '{$code}' already exists"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO items (code, name, unit, status)
        VALUES (:code, :name, :unit, 'active')
    ");
    $stmt->execute([':code' => $code, ':name' => $name, ':unit' => $unit]);

    echo json_encode([
        "status" => "success",
        "message" => "Item registered",
        "data" => ["code" => $code, "name" => $name, "unit" => $unit, "status" => "active"],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to register item",
        "error" => $e->getMessage(),
    ]);
}
