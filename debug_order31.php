<?php
require "config.php";
header('Content-Type: text/plain; charset=utf-8');

// Check columns first
$cols = $pdo->query("DESCRIBE chitiet_donhang")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(", ", $cols) . "\n\n";

$stmt = $pdo->query("SELECT * FROM chitiet_donhang WHERE id_donhang = 31 ORDER BY 1 ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Don hang 31 - " . count($rows) . " rows ===\n\n";

foreach ($rows as $i => $r) {
    echo "Row $i: ";
    foreach ($r as $k => $v) {
        echo "$k=" . ($v ?? 'NULL') . " | ";
    }
    echo "\n";
}
