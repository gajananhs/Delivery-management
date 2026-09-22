<?php
/**
 * GET Fleet Registry API Handler
 * Returns available driver and vehicle logs for real-time employee assignment lists
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    // 1. Query drivers list
    $drv_stmt = $pdo->prepare("SELECT id, name, phone, status, avatar FROM drivers");
    $drv_stmt->execute();
    $drivers = $drv_stmt->fetchAll();

    // 2. Query vehicles list
    $veh_stmt = $pdo->prepare("SELECT id, plate, model, status, fuel FROM vehicles");
    $veh_stmt->execute();
    $vehicles = $veh_stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => [
            "drivers" => $drivers,
            "vehicles" => $vehicles
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve fleet logs",
        "error" => $e->getMessage()
    ]);
}