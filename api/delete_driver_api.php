<?php
/**
 * POST Delete Driver API Handler
 * Removes a driver from the Live Register. Refuses if the driver still has
 * an active (assigned/in_transit) task, so dispatch doesn't lose track of
 * a route mid-flight - complete or reassign those first.
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
    if ($id === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing driver id"]);
        exit;
    }

    $activeStmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks WHERE driver_id = :id AND status IN ('assigned', 'in_transit')
    ");
    $activeStmt->execute([':id' => $id]);
    if ((int)$activeStmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "This driver still has an active task. Complete or reassign it before deleting."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Driver id '{$id}' not found"]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Driver deleted"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete driver",
        "error" => $e->getMessage(),
    ]);
}
