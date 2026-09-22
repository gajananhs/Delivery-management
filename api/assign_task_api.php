<?php
/**
 * POST Assign Task API Handler
 * Approves a task, sets the driver and vehicle, resolves today's ONE security
 * PIN for this driver (see driver_daily_otp below), and marks as "assigned"
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

    $distance = number_format(rand(10, 120) / 10, 1) . " km";
    $eta = rand(15, 60) . " mins";

    // Start isolated SQL transaction
    $pdo->beginTransaction();

    // One OTP per driver per calendar day, regardless of how many tasks they
    // get. driver_daily_otp is keyed on (driver_id, otp_date): if a row for
    // today already exists we reuse its pincode; otherwise this atomically
    // creates one. This is completely separate from GPS/location tracking -
    // update_driver_location_api.php / get_driver_locations_api.php key off
    // drivers.id only and never touch pincode, so live tracking is unaffected.
    $today = date('Y-m-d');
    $candidatePin = (string)rand(1000, 9999);
    $otpStmt = $pdo->prepare("
        INSERT INTO driver_daily_otp (driver_id, otp_date, pincode)
        VALUES (:driver_id, :otp_date, :pincode)
        ON DUPLICATE KEY UPDATE pincode = pincode
    ");
    $otpStmt->execute([
        ':driver_id' => $driverId,
        ':otp_date' => $today,
        ':pincode' => $candidatePin,
    ]);
    $fetchOtp = $pdo->prepare("
        SELECT pincode FROM driver_daily_otp WHERE driver_id = :driver_id AND otp_date = :otp_date
    ");
    $fetchOtp->execute([':driver_id' => $driverId, ':otp_date' => $today]);
    $pincode = $fetchOtp->fetchColumn();

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