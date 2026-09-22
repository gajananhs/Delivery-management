<?php
/**
 * POST Import Suppliers API Handler
 *
 * Accepts either:
 *  - the CSV produced by canaresai.com's Supplier Master "Export" button
 *    (Procurement > Suppliers > Export), or
 *  - a .xlsx workbook with the same header row (e.g. that CSV opened and
 *    re-saved as Excel),
 * and upserts every row into the local `suppliers` table, keyed by `code`.
 *
 * Expects multipart/form-data with a single file field named "file".
 * Re-uploading the same or a refreshed export is safe: existing supplier
 * codes are updated in place, new codes are inserted.
 *
 * .xlsx parsing is done with a small dependency-free reader (PHP's built-in
 * ZipArchive + SimpleXML) instead of a Composer library, since most
 * Hostinger shared-hosting plans don't give shell/Composer access. It reads
 * the first worksheet only and handles shared strings, inline strings and
 * numeric cells - which covers a plain single-sheet supplier export.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database_configuration.php';

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "No file uploaded (expected multipart field named 'file')"
    ]);
    exit;
}

// Column order exactly as exported by canaresai.com (SUPPLIER_CSV_COLUMNS)
$expected = [
    "code", "name", "contact_person", "phone", "email",
    "gstin", "pan", "address", "city", "state", "country", "pin",
    "payment_terms", "lead_time_days", "material_categories",
    "bank_name", "bank_account", "ifsc",
    "status", "rating", "notes",
];

function xlsx_col_to_index(string $letters): int {
    $letters = strtoupper($letters);
    $result = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $result = $result * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $result - 1; // 0-based
}

/** Reads the first worksheet of a .xlsx file into an array of row arrays. */
function read_xlsx_rows(string $filePath): array {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Could not open the file as an XLSX (invalid zip container)');
    }

    // Shared strings table (most text cells reference into this by index)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ssDom = new SimpleXMLElement($ssXml);
        foreach ($ssDom->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // Locate the first worksheet (sheet1.xml, or the first xl/worksheets/*.xml found)
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->getFromName($sheetPath) === false) {
        $sheetPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetPath = $name;
                break;
            }
        }
    }

    $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
    $zip->close();
    if ($sheetXml === false) {
        throw new Exception('Could not find a worksheet inside the uploaded XLSX file');
    }

    $dom = new SimpleXMLElement($sheetXml);
    $rows = [];
    if (!isset($dom->sheetData->row)) {
        return $rows;
    }

    foreach ($dom->sheetData->row as $rowXml) {
        $cells = [];
        $maxCol = -1;
        foreach ($rowXml->c as $c) {
            $ref = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)\d+$/', $ref, $m)) {
                continue;
            }
            $colIndex = xlsx_col_to_index($m[1]);

            $type = (string)$c['t'];
            if ($type === 's') {
                $idx = (int)$c->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string)$c->is->t : '';
            } else {
                // 'str' (formula result) or numeric/plain value
                $value = isset($c->v) ? (string)$c->v : '';
            }

            $cells[$colIndex] = $value;
            if ($colIndex > $maxCol) {
                $maxCol = $colIndex;
            }
        }

        $line = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        // Skip fully blank trailing rows
        if ($maxCol >= 0) {
            $rows[] = $line;
        }
    }
    return $rows;
}

$filename = $_FILES['file']['name'];
$ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
$tmpPath = $_FILES['file']['tmp_name'];

$rows = [];
try {
    if ($ext === 'csv') {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            throw new Exception('Could not read the uploaded CSV file');
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    } elseif ($ext === 'xlsx') {
        $rows = read_xlsx_rows($tmpPath);
    } else {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Unsupported file type '.{$ext}'. Upload a .csv or .xlsx file.",
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Could not parse the uploaded file",
        "error" => $e->getMessage(),
    ]);
    exit;
}

if (empty($rows)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "The uploaded file has no data rows"]);
    exit;
}

$header = array_shift($rows);
if ($header) {
    // Strip a UTF-8 BOM some spreadsheet tools add to the first cell
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
    $header = array_map('trim', $header);
}

if ($header !== $expected) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Unexpected columns. Upload the file exported from canaresai.com's Suppliers 'Export' button (CSV or that file re-saved as XLSX), unmodified.",
        "expected" => $expected,
        "received" => $header,
    ]);
    exit;
}

$upsert = $pdo->prepare("
    INSERT INTO suppliers (
        code, name, contact_person, phone, email, gstin, pan, address,
        city, state, country, pin, payment_terms, lead_time_days,
        material_categories, bank_name, bank_account, ifsc, status, rating, notes
    ) VALUES (
        :code, :name, :contact_person, :phone, :email, :gstin, :pan, :address,
        :city, :state, :country, :pin, :payment_terms, :lead_time_days,
        :material_categories, :bank_name, :bank_account, :ifsc, :status, :rating, :notes
    )
    ON DUPLICATE KEY UPDATE
        name = VALUES(name), contact_person = VALUES(contact_person), phone = VALUES(phone),
        email = VALUES(email), gstin = VALUES(gstin), pan = VALUES(pan), address = VALUES(address),
        city = VALUES(city), state = VALUES(state), country = VALUES(country), pin = VALUES(pin),
        payment_terms = VALUES(payment_terms), lead_time_days = VALUES(lead_time_days),
        material_categories = VALUES(material_categories), bank_name = VALUES(bank_name),
        bank_account = VALUES(bank_account), ifsc = VALUES(ifsc), status = VALUES(status),
        rating = VALUES(rating), notes = VALUES(notes)
");

$existsStmt = $pdo->prepare("SELECT 1 FROM suppliers WHERE code = :code");

$created = 0;
$updated = 0;
$errors = [];
$rowNum = 1; // header was row 1

try {
    foreach ($rows as $row) {
        $rowNum++;
        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue; // skip blank lines
        }
        if (count($row) !== count($expected)) {
            $errors[] = "Row {$rowNum}: expected " . count($expected) . " columns, got " . count($row);
            continue;
        }

        $data = array_combine($expected, $row);
        if (empty($data['code'])) {
            $errors[] = "Row {$rowNum}: missing supplier code";
            continue;
        }

        $status = strtolower(trim((string)$data['status']) ?: 'active');
        if (!in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
            $status = 'active';
        }

        $existsStmt->execute([':code' => $data['code']]);
        $exists = (bool)$existsStmt->fetchColumn();

        $upsert->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':contact_person' => $data['contact_person'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':gstin' => $data['gstin'],
            ':pan' => $data['pan'],
            ':address' => $data['address'],
            ':city' => $data['city'],
            ':state' => $data['state'],
            ':country' => $data['country'] !== '' ? $data['country'] : 'India',
            ':pin' => $data['pin'],
            ':payment_terms' => $data['payment_terms'],
            ':lead_time_days' => (int)($data['lead_time_days'] ?: 0),
            ':material_categories' => $data['material_categories'],
            ':bank_name' => $data['bank_name'],
            ':bank_account' => $data['bank_account'],
            ':ifsc' => $data['ifsc'],
            ':status' => $status,
            ':rating' => (float)($data['rating'] ?: 0),
            ':notes' => $data['notes'],
        ]);

        if ($exists) {
            $updated++;
        } else {
            $created++;
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Supplier import complete",
        "data" => ["created" => $created, "updated" => $updated, "errors" => $errors],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to import suppliers",
        "error" => $e->getMessage(),
    ]);
}
