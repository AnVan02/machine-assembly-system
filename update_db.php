<?php
require "config.php";

echo "<h1>Đang cập nhật cơ sở dữ liệu...</h1>";

try {
    // 1. Kiểm tra và thêm cột id_ct (Primary Key) nếu chưa có
    $checkIdCt = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'id_ct'");
    if ($checkIdCt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE chitiet_donhang ADD id_ct INT AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "<p style='color:green;'>✓ Đã thêm cột 'id_ct' (Primary Key) vào bảng 'chitiet_donhang'.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ Cột 'id_ct' đã tồn tại.</p>";
    }

    // 2. Kiểm tra và xử lý cột linhkien_chon
    $checkColumn = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'linhkien_chon'");
    $column = $checkColumn->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        $pdo->exec("ALTER TABLE chitiet_donhang ADD linhkien_chon VARCHAR(255) DEFAULT NULL AFTER loai_linhkien");
        echo "<p style='color:green;'>✓ Đã thêm cột 'linhkien_chon' vào bảng 'chitiet_donhang' (mặc định NULL).</p>";
    } else {
        // Đảm bảo cột linhkien_chon cho phép NULL (Khắc phục lỗi NO NULL)
        $pdo->exec("ALTER TABLE chitiet_donhang MODIFY linhkien_chon VARCHAR(255) DEFAULT NULL");
        echo "<p style='color:blue;'>ℹ Cột 'linhkien_chon' đã được cập nhật để cho phép NULL.</p>";
    }

    // 3. Cập nhật các hàng cũ: Nếu linhkien_chon đang bằng ten_linhkien hoặc rỗng, cho nó về NULL
    $updateExisting = $pdo->exec("UPDATE chitiet_donhang SET linhkien_chon = NULL WHERE linhkien_chon = ten_linhkien OR linhkien_chon = ''");
    echo "<p style='color:green;'>✓ Đã cập nhật $updateExisting hàng cũ để linhkien_chon là NULL.</p>";

    // 4. Thêm cột so_may để gắn linh kiện theo từng máy
    $checkSoMay = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'so_may'");
    if ($checkSoMay->rowCount() == 0) {
        $checkOldMaySo = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'may_so'");
        if ($checkOldMaySo->rowCount() > 0) {
            $pdo->exec("ALTER TABLE chitiet_donhang CHANGE may_so so_may INT NULL DEFAULT NULL");
            echo "<p style='color:green;'>✓ Đã đổi tên cột 'may_so' thành 'so_may' và cho phép NULL.</p>";
        } else {
            $pdo->exec("ALTER TABLE chitiet_donhang ADD so_may INT NULL DEFAULT NULL AFTER user_id");
            echo "<p style='color:green;'>✓ Đã thêm cột 'so_may' vào bảng 'chitiet_donhang' (mặc định NULL).</p>";
        }
    } else {
        // Khắc phục triệt để lỗi "Field 'so_may' doesn't have a default value"
        $pdo->exec("ALTER TABLE chitiet_donhang MODIFY so_may INT NULL DEFAULT NULL");
        echo "<p style='color:blue;'>ℹ Cột 'so_may' đã được cấu hình lại để cho phép NULL.</p>";
    }

    echo "<h2 style='color:blue;'>THÀNH CÔNG! Cơ sở dữ liệu đã sẵn sàng.</h2>";
    echo "<p><a href='ke-toan-tao-don.php' style='padding:10px; background:blue; color:white; text-decoration:none;'>Quay lại trang tạo đơn</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>LỖI:</h2> <pre>" . $e->getMessage() . "</pre>";
}
?>