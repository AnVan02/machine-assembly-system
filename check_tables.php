<?php
require 'config.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'sanpham'");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(empty($tables)) {
        echo "Table sanpham DOES NOT exist.\n";
        
        // Show all tables
        $stmt2 = $pdo->query("SHOW TABLES");
        $all = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        echo "All tables: " . implode(', ', $all);
    } else {
        echo "Table sanpham EXISTS.\n";
        $stmt3 = $pdo->query("DESCRIBE sanpham");
        print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
