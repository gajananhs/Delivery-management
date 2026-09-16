<?php
/**
 * GET Customers API Handler
 * Returns the saved customer list so the dispatch form can offer a
 * "pick an existing customer" autocomplete instead of always retyping.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, address, city
        FROM customers
        WHERE status = 'active'
        ORDER BY name
    ");
    $stmt->execute();
    $customers = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $customers
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve customers",
        "error" => $e->getMessage()
    ]);
}
