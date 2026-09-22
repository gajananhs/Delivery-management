<?php
/**
 * POST Add Customer API Handler
 * Registers a new customer/company into the Live Register, for reuse in
 * the "Customer / Company" field on the dispatch form.
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
        echo json_encode(["status" => "error", "message" => "Missing customer name"]);
        exit;
    }

    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    $city = trim($input['city'] ?? '');

    $existing = $pdo->prepare("SELECT id FROM customers WHERE name = :name");
    $existing->execute([':name' => $name]);
    if ($existing->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "A customer named '{$name}' already exists"]);
        exit;
    }

    $id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    $stmt = $pdo->prepare("
        INSERT INTO customers (id, name, phone, email, address, city, status)
        VALUES (:id, :name, :phone, :email, :address, :city, 'active')
    ");
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':address' => $address,
        ':city' => $city,
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Customer registered",
        "data" => ["id" => $id, "name" => $name, "phone" => $phone, "email" => $email, "address" => $address, "city" => $city],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to register customer",
        "error" => $e->getMessage(),
    ]);
}
