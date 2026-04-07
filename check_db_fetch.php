<?php
require 'config.php';
try {
    $stmt = $pdo->prepare("SELECT SOSERIAL, LENGTH(SOSERIAL) as len FROM `nvpbgqcv_vietsontdc0110`.sanpham LIMIT 10");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
