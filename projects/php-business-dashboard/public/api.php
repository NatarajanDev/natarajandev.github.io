<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Report.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $report = new Report(Database::connect());
    echo json_encode([
        'monthly_sales' => $report->monthlySales(),
        'top_products' => $report->topProducts(),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to generate report.']);
}
