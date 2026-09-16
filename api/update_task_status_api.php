<?php
/**
 * POST Update Task Status API Handler
 * Progresses status (assigned -> in_transit -> completed)
 * Automatically releases drivers & vehicles back to 'Available' once marked complete
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    if (empty($input['task_id']) || empty($input['status'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing task_id or status"]);
        exit;
    }

    $taskId = $input['task_id'];
    $status = $input['status'];

    $pdo->beginTransaction();

    // 1. Update task record
    $completedAtUpdate = ($status === 'completed') ? ", completed_at = CURRENT_TIMESTAMP" : "";
    $stmt = $pdo->prepare("UPDATE tasks SET status = :status {$completedAtUpdate} WHERE id = :task_id");
    $stmt->execute([
        ':status' => $status,
        ':task_id' => $taskId
    ]);

    // 2. Fetch driver & vehicle linked to release them if completed
    if ($status === 'completed') {
        $metaStmt = $pdo->prepare("SELECT driver_id, vehicle_id FROM tasks WHERE id = :task_id");
        $metaStmt->execute([':task_id' => $taskId]);
        $task = $metaStmt->fetch();

        if ($task) {
            // Release Driver
            $relDrv = $pdo->prepare("UPDATE drivers SET status = 'Available' WHERE id = :driver_id");
            $relDrv->execute([':driver_id' => $task['driver_id']]);

            // Release Vehicle
            $relVeh = $pdo->prepare("UPDATE vehicles SET status = 'Available' WHERE id = :vehicle_id");
            $relVeh->execute([':vehicle_id' => $task['vehicle_id']]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Status successfully progressed to {$status}"
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update status",
        "error" => $e->getMessage()
    ]);
}