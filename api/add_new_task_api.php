<?php
/**
 * POST Create Task API Handler
 * Receives raw task entries from Employee Console and stores them as pending_approval
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    // Handle JSON payloads sent by modern vanilla fetch
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // Fallback if data is sent via URL-encoded form data
    if (!$input) {
        $input = $_POST;
    }

    // Server-side input validation
    if (empty($input['type']) || empty($input['customer']) || empty($input['area']) || empty($input['exact_address'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Incomplete request body. Missing type, customer, area, or exact_address parameters."
        ]);
        exit;
    }

    $id = "TSK-" . rand(1000, 9999);
    // Optional: supplier picked from the imported canaresai.com Supplier Master list
    $supplierCode = !empty($input['supplier_code']) ? $input['supplier_code'] : null;
    // Optional: shared id linking several stops created together as one
    // "multiple deliveries" dispatch (see index.html "+ Add Another Delivery")
    $batchId = !empty($input['batch_id']) ? $input['batch_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO tasks (id, type, customer, area, exact_address, supplier_code, batch_id, status)
        VALUES (:id, :type, :customer, :area, :exact_address, :supplier_code, :batch_id, 'pending_approval')
    ");

    $stmt->execute([
        ':id' => $id,
        ':type' => $input['type'],
        ':customer' => $input['customer'],
        ':area' => $input['area'],
        ':exact_address' => $input['exact_address'],
        ':supplier_code' => $supplierCode,
        ':batch_id' => $batchId
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Task created successfully",
        "data" => ["id" => $id]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to store task",
        "error" => $e->getMessage()
    ]);
}