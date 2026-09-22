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

    // Optional: customer's contact number, so the driver can see who to call
    // for this stop. Autofilled client-side when picking a saved customer,
    // but also accepted free-text for a one-off customer.
    $customerPhone = trim($input['customer_phone'] ?? '') ?: null;

    // Optional: items being collected/supplied on this stop - array of
    // {item_code?, item_name, quantity?, unit?}. item_code is only set when
    // picked from the registered Item master; a one-off item still works
    // with just a name, same pattern as the free-text customer field.
    $items = is_array($input['items'] ?? null) ? $input['items'] : [];

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO tasks (id, type, customer, customer_phone, area, exact_address, supplier_code, batch_id, status)
        VALUES (:id, :type, :customer, :customer_phone, :area, :exact_address, :supplier_code, :batch_id, 'pending_approval')
    ");

    $stmt->execute([
        ':id' => $id,
        ':type' => $input['type'],
        ':customer' => $input['customer'],
        ':customer_phone' => $customerPhone,
        ':area' => $input['area'],
        ':exact_address' => $input['exact_address'],
        ':supplier_code' => $supplierCode,
        ':batch_id' => $batchId
    ]);

    if (!empty($items)) {
        $itemStmt = $pdo->prepare("
            INSERT INTO task_items (task_id, item_code, item_name, quantity, unit)
            VALUES (:task_id, :item_code, :item_name, :quantity, :unit)
        ");
        foreach ($items as $item) {
            $itemName = trim($item['item_name'] ?? '');
            if ($itemName === '') continue; // skip blank rows silently
            $itemStmt->execute([
                ':task_id' => $id,
                ':item_code' => !empty($item['item_code']) ? $item['item_code'] : null,
                ':item_name' => $itemName,
                ':quantity' => is_numeric($item['quantity'] ?? null) ? $item['quantity'] : 1,
                ':unit' => !empty($item['unit']) ? trim($item['unit']) : null,
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Task created successfully",
        "data" => ["id" => $id]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to store task",
        "error" => $e->getMessage()
    ]);
}