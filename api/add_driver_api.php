<?php
/**
 * POST Add Driver API Handler
 * Registers a new driver into the Live Register. This file did not exist in
 * the original bundle even though the dashboard's "+ Register New Driver"
 * form already called it (api/add_driver.php) - that mismatch was one of
 * the reasons the app never left "Sandbox (Offline Mode)".
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    if (empty($input['id']) || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing driver id or name"]);
        exit;
    }

    $id = strtolower(trim($input['id']));
    $name = trim($input['name']);
    $avatar = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($id) . "&backgroundColor=transparent";

    $existing = $pdo->prepare("SELECT id FROM drivers WHERE id = :id");
    $existing->execute([':id' => $id]);
    if ($existing->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Driver id '{$id}' already exists"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO drivers (id, name, status, avatar)
        VALUES (:id, :name, 'Available', :avatar)
    ");
    $stmt->execute([':id' => $id, ':name' => $name, ':avatar' => $avatar]);

    echo json_encode([
        "status" => "success",
        "message" => "Driver registered",
        "data" => ["id" => $id, "name" => $name, "status" => "Available", "avatar" => $avatar],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to register driver",
        "error" => $e->getMessage(),
    ]);
}
