<?php
/**
 * POST Delete Task API Handler
 * Removes a task (and its item lines) from the Fleet Activity log. Only
 * allowed once the task is 'completed' - dispatch (the person who assigned
 * it) can clean up finished routes, but an active route can't be deleted
 * out from under a driver.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    $taskId = trim($input['task_id'] ?? '');
    if ($taskId === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing task_id"]);
        exit;
    }

    $taskStmt = $pdo->prepare("SELECT status FROM tasks WHERE id = :task_id");
    $taskStmt->execute([':task_id' => $taskId]);
    $task = $taskStmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Task not found"]);
        exit;
    }

    if ($task['status'] !== 'completed') {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Only completed tasks can be deleted."
        ]);
        exit;
    }

    $pdo->beginTransaction();
    $delItems = $pdo->prepare("DELETE FROM task_items WHERE task_id = :task_id");
    $delItems->execute([':task_id' => $taskId]);

    $delTask = $pdo->prepare("DELETE FROM tasks WHERE id = :task_id");
    $delTask->execute([':task_id' => $taskId]);
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Task deleted"
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete task",
        "error" => $e->getMessage(),
    ]);
}
