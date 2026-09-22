<?php
/**
 * POST Update Driver Location API Handler
 * Called from the Driver App with the browser/phone's REAL GPS coordinates
 * (navigator.geolocation), not the simulated on-screen map animation.
 * Stores the latest lat/lng + timestamp on the driver's row so the Employee
 * Console can look up where that driver actually is right now.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    if (empty($input['driver_id']) || !isset($input['lat']) || !isset($input['lng'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing driver_id, lat, or lng"]);
        exit;
    }

    $lat = (float)$input['lat'];
    $lng = (float)$input['lng'];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "lat/lng out of range"]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE drivers
        SET lat = :lat, lng = :lng, location_updated_at = CURRENT_TIMESTAMP
        WHERE id = :driver_id
    ");
    $stmt->execute([':lat' => $lat, ':lng' => $lng, ':driver_id' => $input['driver_id']]);

    echo json_encode(["status" => "success", "message" => "Location updated"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update location",
        "error" => $e->getMessage(),
    ]);
}
