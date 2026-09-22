<?php
/**
 * POST Update Driver API Handler
 * Edits an existing driver's name/phone from the Live Register's inline
 * Edit button. Does not touch id, status, or avatar.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    $id = strtolower(trim($input['id'] ?? ''));
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if ($id === '' || $name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing driver id or name"]);
        exit;
    }

    $existing = $pdo->prepare("SELECT id FROM drivers WHERE id = :id");
    $existing->execute([':id' => $id]);
    if (!$existing->fetch()) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Driver id '{$id}' not found"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE drivers SET name = :name, phone = :phone WHERE id = :id");
    $stmt->execute([':name' => $name, ':phone' => $phone, ':id' => $id]);

    echo json_encode([
        "status" => "success",
        "message" => "Driver updated",
        "data" => ["id" => $id, "name" => $name, "phone" => $phone],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update driver",
        "error" => $e->getMessage(),
    ]);
}
