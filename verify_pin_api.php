<?php
/**
 * POST Verify PIN API Handler
 * Checks driver PIN against DB task entry to authorize decryption of the exact address payload
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    if (empty($input['task_id']) || empty($input['pincode'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT exact_address, pincode FROM tasks WHERE id = :task_id");
    $stmt->execute([':task_id' => $input['task_id']]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Task not found"]);
        exit;
    }

    if ($task['pincode'] === $input['pincode']) {
        echo json_encode([
            "status" => "success",
            "message" => "Address security unlocked successfully",
            "data" => [
                "exact_address" => $task['exact_address']
            ]
        ]);
    } else {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid Route Code PIN. Access denied."
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Verification runtime failed",
        "error" => $e->getMessage()
    ]);
}