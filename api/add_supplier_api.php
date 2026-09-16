<?php
/**
 * POST Add Supplier API Handler
 * Manually registers a single supplier from the Live Register tab's
 * "+ Register New Supplier" form - a quicker alternative to bulk-importing
 * the full canaresai.com CSV/XLSX export via import_suppliers_api.php.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    $code = trim($input['code'] ?? '');
    $name = trim($input['name'] ?? '');
    if ($code === '' || $name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing supplier code or name"]);
        exit;
    }

    $phone = trim($input['phone'] ?? '');       // Contact number
    $city = trim($input['city'] ?? '');

    $existing = $pdo->prepare("SELECT code FROM suppliers WHERE code = :code");
    $existing->execute([':code' => $code]);
    if ($existing->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Supplier code '{$code}' already exists"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO suppliers (code, name, phone, city, country, status)
        VALUES (:code, :name, :phone, :city, 'India', 'active')
    ");
    $stmt->execute([':code' => $code, ':name' => $name, ':phone' => $phone, ':city' => $city]);

    echo json_encode([
        "status" => "success",
        "message" => "Supplier registered",
        "data" => ["code" => $code, "name" => $name, "phone" => $phone, "city" => $city, "status" => "active"],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to register supplier",
        "error" => $e->getMessage(),
    ]);
}
