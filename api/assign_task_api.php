<?php
/**
 * POST Assign Task API Handler
 * Approves a task, sets the driver and vehicle, generates the security PIN, and marks as "assigned"
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    if (empty($input['task_id']) || empty($input['driver_id']) || empty($input['vehicle_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    $taskId = $input['task_id'];
    $driverId = $input['driver_id'];
    $vehicleId = $input['vehicle_id'];

    // Auto-generate secure 4-digit PINCODE
    $pincode = (string)rand(1000, 9999);
    $distance = number_format(rand(10, 120) / 10, 1) . " km";
    $eta = rand(15, 60) . " mins";

    // Start isolated SQL transaction
    $pdo->beginTransaction();

    // 1. Update task attributes
    $taskStmt = $pdo->prepare("
        UPDATE tasks 
        SET driver_id = :driver_id, 
            vehicle_id = :vehicle_id, 
            pincode = :pincode, 
            distance = :distance, 
            eta = :eta, 
            status = 'assigned' 
        WHERE id = :task_id
    ");
    $taskStmt->execute([
        ':driver_id' => $driverId,
        ':vehicle_id' => $vehicleId,
        ':pincode' => $pincode,
        ':distance' => $distance,
        ':eta' => $eta,
        ':task_id' => $taskId
    ]);

    // 2. Lock driver status to Busy
    $drvStmt = $pdo->prepare("UPDATE drivers SET status = 'Busy' WHERE id = :driver_id");
    $drvStmt->execute([':driver_id' => $driverId]);

    // 3. Lock vehicle status to In Use
    $vehStmt = $pdo->prepare("UPDATE vehicles SET status = 'In Use' WHERE id = :vehicle_id");
    $vehStmt->execute([':vehicle_id' => $vehicleId]);

    // Commit Transaction safely
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Route successfully assigned directly to driver.",
        "data" => [
            "pincode" => $pincode,
            "distance" => $distance,
            "eta" => $eta
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database Transaction Failed",
        "error" => $e->getMessage()
    ]);
}