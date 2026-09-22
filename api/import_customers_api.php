<?php
/**
 * POST Import Customers CSV API Handler
 *
 * Accepts the CSV produced by canaresai.com's Customer Master "Export"
 * button (Sales > Customers > Export) and upserts every row into the
 * local `customers` table, keyed by the customer `code` (used as this
 * table's `id`, e.g. code "01" becomes id "01").
 *
 * Only customer name/phone/email/address/city/pin are kept locally — this
 * app doesn't need GSTIN, billing/shipping line detail, or credit limit.
 * Re-uploading a newer export is safe: existing codes are updated in
 * place, new codes are inserted. Manually-added customers (from the
 * "+ Register New Customer" form, which have no canaresai.com code) are
 * left untouched.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "No CSV file uploaded (expected multipart field named 'file')"
    ]);
    exit;
}

// Column order exactly as exported by canaresai.com's Customer Master (CUSTOMER_CSV_COLUMNS)
$expected = [
    "code", "name", "gstin", "contact", "email", "phone", "address",
    "billing_line1", "billing_line2", "billing_city", "billing_state", "billing_country", "billing_pin",
    "shipping_line1", "shipping_line2", "shipping_city", "shipping_state", "shipping_country", "shipping_pin",
    "credit_limit",
];

$handle = fopen($_FILES['file']['tmp_name'], 'r');
if (!$handle) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Could not read the uploaded file"]);
    exit;
}

$header = fgetcsv($handle);
if ($header) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
    $header = array_map('trim', $header);
}

if ($header !== $expected) {
    fclose($handle);
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Unexpected columns. Upload the file exported from canaresai.com's Customers 'Export' button, unmodified.",
        "expected" => $expected,
        "received" => $header,
    ]);
    exit;
}

$upsert = $pdo->prepare("
    INSERT INTO customers (id, name, phone, email, address, city, pin, status)
    VALUES (:id, :name, :phone, :email, :address, :city, :pin, 'active')
    ON DUPLICATE KEY UPDATE
        name = VALUES(name), phone = VALUES(phone), email = VALUES(email),
        address = VALUES(address), city = VALUES(city), pin = VALUES(pin)
");

$created = 0;
$updated = 0;
$errors = [];
$rowNum = 1;

try {
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue; // skip blank lines
        }
        if (count($row) !== count($expected)) {
            $errors[] = "Row {$rowNum}: expected " . count($expected) . " columns, got " . count($row);
            continue;
        }

        $data = array_combine($expected, $row);
        $code = trim($data['code']);
        if (!$code || !trim($data['name'])) {
            $errors[] = "Row {$rowNum}: missing code or name";
            continue;
        }

        $address = trim($data['billing_line1'] . ' ' . $data['billing_line2']) ?: trim($data['address']);

        $existing = $pdo->prepare("SELECT id FROM customers WHERE id = :id");
        $existing->execute([':id' => $code]);
        $exists = (bool)$existing->fetch();

        $upsert->execute([
            ':id' => $code,
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':address' => $address,
            ':city' => $data['billing_city'],
            ':pin' => $data['billing_pin'],
        ]);

        if ($exists) { $updated++; } else { $created++; }
    }
    fclose($handle);

    echo json_encode([
        "status" => "success",
        "message" => "Customer import complete",
        "data" => ["created" => $created, "updated" => $updated, "errors" => $errors],
    ]);
} catch (PDOException $e) {
    fclose($handle);
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to import customers",
        "error" => $e->getMessage(),
    ]);
}
