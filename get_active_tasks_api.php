<?php
/**
 * GET Tasks API Handler
 * Returns complete list of deliveries and collection runs
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $stmt = $pdo->prepare("
        SELECT t.*, d.name AS driver_name, d.phone AS driver_phone,
               v.plate AS vehicle_plate,
               s.name AS supplier_name, s.phone AS supplier_phone
        FROM tasks t
        LEFT JOIN drivers d ON t.driver_id = d.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN suppliers s ON t.supplier_code = s.code
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll();

    // Attach each task's item lines (what's being collected/supplied) in one
    // extra query rather than N+1 - only bother if there are tasks at all.
    if ($tasks) {
        $taskIds = array_column($tasks, 'id');
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $itemStmt = $pdo->prepare("
            SELECT task_id, item_code, item_name, quantity, unit
            FROM task_items
            WHERE task_id IN ($placeholders)
            ORDER BY id
        ");
        $itemStmt->execute($taskIds);

        $itemsByTask = [];
        foreach ($itemStmt->fetchAll() as $row) {
            $itemsByTask[$row['task_id']][] = $row;
        }

        foreach ($tasks as &$task) {
            $task['items'] = $itemsByTask[$task['id']] ?? [];
        }
        unset($task);
    }

    echo json_encode([
        "status" => "success",
        "data" => $tasks
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve tasks",
        "error" => $e->getMessage()
    ]);
}