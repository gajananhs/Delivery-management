<?php
/**
 * Database Configuration & PDO Initialization File
 * 
 * HOSTINGER HPANEL DEPLOYMENT GUIDE:
 * 1. Log in to your Hostinger hPanel.
 * 2. Navigate to "MySQL Databases" under the Database section.
 * 3. Create a new Database and a MySQL User. Set a secure password.
 * 4. Replace the placeholders below with the exact details provided by Hostinger:
 *    - Host: Usually "localhost" (or "127.0.0.1") for standard installations.
 *    - Database Name: Will look like "u123456789_dbname"
 *    - Username: Will look like "u123456789_user"
 *    - Password: The database password you configured.
 */

// --- Database Credentials ---
$db_host = 'localhost';             // Hostinger default is usually localhost
$db_name = 'u924350731_Delivery';  // Replace with your Hostinger Database Name
$db_user = 'u924350731_Delivery'; // Replace with your Hostinger Database User
$db_pass = 'Delivery@2026'; // Replace with your Hostinger Database Password

// --- CORS Headers (Allows decoupled testing if needed) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Browsers send a CORS "preflight" OPTIONS request before every cross-origin
// POST with a JSON body — e.g. https://gajananhs.github.io calling this
// https://canaresonline.com API. It must get a plain 200 with the headers
// above and no body, and must NEVER reach the database logic below — a
// slow or failed DB connection would fail the preflight itself and silently
// break every POST call from the GitHub Pages app (GETs would still work).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Establish connection using PHP Data Objects (PDO)
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch database rows as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
        ]
    );
} catch (PDOException $e) {
    // Gracefully handle database connection failures and prevent raw details leakage
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Critical Database Connection Failure",
        "error_details" => $e->getMessage() // In production, replace this with a generic statement
    ]);
    exit;
}
