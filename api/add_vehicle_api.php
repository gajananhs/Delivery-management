<?php
/**
 * POST Add Vehicle API Handler
 * Registers a new vehicle into the Live Register. Also missing from the
 * original bundle (see add_driver_api.php note).
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    if (empty($input['id']) || empty($input['plate']) || empty($input['model'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing vehicle id, plate, or model"]);
        exit;
    }

    $id = strtoupper(trim($input['id']));
    $plate = strtoupper(trim($input['plate']));
    $model = trim($input['model']);
    $fuel = isset($input['fuel']) ? max(1, min(100, (int)$input['fuel'])) : 100;

    $existing = $pdo->prepare("SELECT id FROM vehicles WHERE id = :id");
    $existing->execute([':id' => $id]);
    if ($existing->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Vehicle id '{$id}' already exists"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO vehicles (id, plate, model, status, fuel)
        VALUES (:id, :plate, :model, 'Available', :fuel)
    ");
    $stmt->execute([':id' => $id, ':plate' => $plate, ':model' => $model, ':fuel' => $fuel]);

    echo json_encode([
        "status" => "success",
        "message" => "Vehicle registered",
        "data" => ["id" => $id, "plate" => $plate, "model" => $model, "status" => "Available", "fuel" => $fuel],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to register vehicle",
        "error" => $e->getMessage(),
    ]);
}
