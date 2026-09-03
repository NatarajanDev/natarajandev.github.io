<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Report.php';

try {
    $report = new Report(Database::connect());
    $sales = $report->monthlySales();
} catch (Throwable $e) {
    http_response_code(500);
    $sales = [];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Business Dashboard Demo</title>
<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:40px auto;padding:0 20px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #ddd}</style>
</head>
<body>
<h1>Business Dashboard</h1>
<p>PHP/MySQL reporting demo using PDO and server-side aggregation.</p>
<table><thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($sales as $row): ?>
<tr><td><?= htmlspecialchars((string)$row['month']) ?></td><td><?= (int)$row['orders'] ?></td><td><?= htmlspecialchars((string)$row['revenue']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><a href="api.php">View JSON API</a></p>
</body>
</html>
