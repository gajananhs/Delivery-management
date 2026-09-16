<?php
/**
 * POST Fuel Voucher Request
 * Files a driver refueling entry directly into the MySQL audit ledger
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    if (empty($input['amount']) || empty($input['station']) || empty($input['vehicle_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing refueling payload details"]);
        exit;
    }

    $id = "FR-" . rand(100, 999);
    $dateStr = "Today, " . date("H:i A");

    $stmt = $pdo->prepare("
        INSERT INTO fuel_requests (id, date, amount, status, station, vehicle_id) 
        VALUES (:id, :date, :amount, 'approved', :station, :vehicle_id)
    ");

    $stmt->execute([
        ':id' => $id,
        ':date' => $dateStr,
        ':amount' => $input['amount'] . " Liters",
        ':station' => $input['station'],
        ':vehicle_id' => $input['vehicle_id']
    ]);

    // Simulate refueling the vehicle to 100%
    $vehStmt = $pdo->prepare("UPDATE vehicles SET fuel = 100 WHERE id = :vehicle_id");
    $vehStmt->execute([':vehicle_id' => $input['vehicle_id']]);

    echo json_encode([
        "status" => "success",
        "message" => "Fuel request authorized!",
        "data" => [
            "id" => $id,
            "date" => $dateStr,
            "amount" => $input['amount'] . " Liters"
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to log fuel request",
        "error" => $e->getMessage()
    ]);
}