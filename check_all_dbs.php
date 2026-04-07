<?php
$host = "localhost";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    $found = [];
    foreach ($dbs as $db) {
        $tables = $pdo->query("SHOW TABLES FROM `$db`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('sanpham', $tables)) {
            $found[] = $db;
        }
    }
    
    if (empty($found)) {
        echo "Table 'sanpham' DOES NOT exist in ANY database.\n";
    } else {
        echo "Table 'sanpham' found in databases: " . implode(', ', $found) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
