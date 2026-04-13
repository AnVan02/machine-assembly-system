<?php
require "config.php";
$stmt = $pdo->query("SELECT id_donhang, ma_don_hang FROM donhang ORDER BY id_donhang DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
