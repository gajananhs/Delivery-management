<?php
/**
 * POST Update Customer API Handler
 * Edits an existing customer's name/phone/city from the Live Register's
 * inline Edit button. Does not touch id, email, address, or status.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    $id = trim($input['id'] ?? '');
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $city = trim($input['city'] ?? '');

    if ($id === '' || $name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing customer id or name"]);
        exit;
    }

    $existing = $pdo->prepare("SELECT id FROM customers WHERE id = :id");
    $existing->execute([':id' => $id]);
    if (!$existing->fetch()) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Customer id '{$id}' not found"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE customers SET name = :name, phone = :phone, city = :city WHERE id = :id");
    $stmt->execute([':name' => $name, ':phone' => $phone, ':city' => $city, ':id' => $id]);

    echo json_encode([
        "status" => "success",
        "message" => "Customer updated",
        "data" => ["id" => $id, "name" => $name, "phone" => $phone, "city" => $city],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update customer",
        "error" => $e->getMessage(),
    ]);
}
