<?php
/**
 * GET Customers API Handler
 * Returns the saved customer list so the dispatch form can offer a
 * "pick an existing customer" autocomplete/dropdown instead of retyping.
 *
 * Filtered to Bangalore only, per request — change or remove the WHERE
 * clause below if you later want other cities included too.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

// Matches city name variants ("Bangalore", "Bengaluru", "BANGALORE RURAL", ...)
// and Bangalore-area PIN codes (560xxx). Adjust freely.
const CUSTOMER_CITY_FILTER = '%bangalore%';
const CUSTOMER_CITY_FILTER_ALT = '%bengaluru%';
const CUSTOMER_PIN_PREFIX = '560%';

try {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, address, city, pin
        FROM customers
        WHERE status = 'active'
          AND (
                LOWER(city) LIKE :city_filter
             OR LOWER(city) LIKE :city_filter_alt
             OR pin LIKE :pin_prefix
          )
        ORDER BY name
    ");
    $stmt->execute([
        ':city_filter' => CUSTOMER_CITY_FILTER,
        ':city_filter_alt' => CUSTOMER_CITY_FILTER_ALT,
        ':pin_prefix' => CUSTOMER_PIN_PREFIX,
    ]);
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
