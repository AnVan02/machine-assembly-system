<?php
require "config.php";
echo "<h1>Đang sửa lỗi Database...</h1>";
try {
    // 1. Thêm cột id_ct nếu chưa có
    $check = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'id_ct'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE chitiet_donhang ADD id_ct INT AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "<p style='color:green;'>✓ Đã thêm cột id_ct.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ Cột id_ct đã tồn tại.</p>";
    }

    // 2. Thêm cột linhkien_chon nếu chưa có
    $check2 = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'linhkien_chon'");
    if ($check2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE chitiet_donhang ADD linhkien_chon VARCHAR(255) DEFAULT NULL AFTER loai_linhkien");
        echo "<p style='color:green;'>✓ Đã thêm cột linhkien_chon.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ Cột linhkien_chon đã tồn tại.</p>";
    }

    echo "<h2 style='color:green;'>XONG! Database đã chuẩn.</h2>";
    echo "<p><a href='kho-hang.php'>Quay về Kho hàng</a></p>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>LỖI: " . $e->getMessage() . "</h2>";
}
?>