<?php
/**
 * POST Upload Task PDF API Handler
 * Accepts a "whole items list" PDF from the Create & Assign dispatch form
 * (an alternative to typing item rows one by one) and stores it under
 * /uploads/task_pdfs/, returning a public URL to save on the task record.
 * Doesn't touch the tasks table itself - add_new_task_api.php does that
 * with the URL this endpoint returns.
 */
require_once __DIR__ . '/../database_configuration.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit;
    }

    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "No PDF file received"]);
        exit;
    }

    $file = $_FILES['pdf'];

    // 10 MB cap
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "PDF is too large (10 MB max)"]);
        exit;
    }

    // Verify it's actually a PDF, not just named .pdf
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mimeType !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Only PDF files are accepted"]);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/task_pdfs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'ITEMS-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.pdf';
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to save the uploaded file"]);
        exit;
    }

    // Build a public URL relative to this script's own location, so it
    // works whether the app lives at /Delivery_management/ or elsewhere.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // .../Delivery_management
    $url = "{$protocol}://{$host}{$baseDir}/uploads/task_pdfs/{$filename}";

    echo json_encode([
        "status" => "success",
        "message" => "PDF uploaded",
        "data" => ["url" => $url]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to upload PDF",
        "error" => $e->getMessage()
    ]);
}
