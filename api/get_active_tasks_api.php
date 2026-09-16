<?php
/**
 * GET Tasks API Handler
 * Returns complete list of deliveries and collection runs
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $stmt = $pdo->prepare("
        SELECT t.*, d.name AS driver_name, v.plate AS vehicle_plate, s.name AS supplier_name
        FROM tasks t
        LEFT JOIN drivers d ON t.driver_id = d.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN suppliers s ON t.supplier_code = s.code
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll();

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