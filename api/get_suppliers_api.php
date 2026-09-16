<?php
/**
 * GET Suppliers API Handler
 * Returns the active supplier list (imported from canaresai.com's Supplier
 * Master via import_suppliers_api.php) for the Employee Console's
 * dispatch form "Supplier" picker.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $stmt = $pdo->prepare("
        SELECT code, name, contact_person, phone, gstin, city, state,
               payment_terms, lead_time_days, status
        FROM suppliers
        WHERE status = 'active'
        ORDER BY name
    ");
    $stmt->execute();
    $suppliers = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $suppliers
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve suppliers",
        "error" => $e->getMessage()
    ]);
}
