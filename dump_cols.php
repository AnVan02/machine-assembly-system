<?php
require "config.php";
if ($pdo) {
    echo "COLUMNS FOR chitiet_donhang:\n";
    $stmt = $pdo->query("DESCRIBE chitiet_donhang");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
?>
