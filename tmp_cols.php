<?php
require "config.php";
$stmt = $pdo->query("DESCRIBE chitiet_donhang");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
