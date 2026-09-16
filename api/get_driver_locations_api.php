<?php
/**
 * GET Driver Locations API Handler
 * Returns the latest known REAL GPS position (from update_driver_location_api.php)
 * for one driver (?driver_id=alex) or all drivers that have ever reported one.
 * Used by track_driver.html to render a live map for cross-checking.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $driverId = $_GET['driver_id'] ?? null;

    if ($driverId) {
        $stmt = $pdo->prepare("
            SELECT id, name, status, lat, lng, location_updated_at
            FROM drivers
            WHERE id = :driver_id
        ");
        $stmt->execute([':driver_id' => $driverId]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Driver not found"]);
            exit;
        }
        echo json_encode(["status" => "success", "data" => $row]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, status, lat, lng, location_updated_at
            FROM drivers
            WHERE lat IS NOT NULL AND lng IS NOT NULL
        ");
        $stmt->execute();
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve driver location(s)",
        "error" => $e->getMessage(),
    ]);
}
