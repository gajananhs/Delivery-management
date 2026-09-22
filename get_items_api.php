<?php
/**
 * GET Items API Handler
 * Returns the active item master list (what CAPL collects/supplies on
 * delivery & collection runs) for the dispatch form's item picker.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $stmt = $pdo->prepare("
        SELECT code, name, unit, status
        FROM items
        WHERE status = 'active'
        ORDER BY name
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $items
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve items",
        "error" => $e->getMessage()
    ]);
}
