<?php
require "config.php";
if ($pdo) {
    $stmt = $pdo->query("DESCRIBE chitiet_donhang");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = "";
    foreach ($cols as $c) {
        $out .= $c['Field'] . "\n";
    }
    file_put_contents('columns_list.txt', $out);
    echo "Đã ghi danh sách cột vào columns_list.txt";
}
?>
