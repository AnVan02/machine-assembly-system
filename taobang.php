<?php
require "config.php";

if (!$pdo) {
    die("Lỗi kết nối database.");
}

$queries = [
    "SET FOREIGN_KEY_CHECKS = 0",
    "DROP TABLE IF EXISTS chitiet_donhang",
    "DROP TABLE IF EXISTS donhang",
    "SET FOREIGN_KEY_CHECKS = 1",

    // Bảng 1: donhang
    "CREATE TABLE donhang (
        id_donhang INT AUTO_INCREMENT PRIMARY KEY,
        ma_don_hang VARCHAR(50) NOT NULL,
        ten_khach_hang VARCHAR(255),
        so_luong_may INT DEFAULT 1,
        user_id INT,
        ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Bảng 2: chitiet_donhang (ĐÚNG 7 CỘT THEO YÊU CẦU)
    "CREATE TABLE chitiet_donhang (
        id_ct INT AUTO_INCREMENT PRIMARY KEY,
        id_donhang INT,
        ten_donhang VARCHAR(255),
        ten_cauhinh VARCHAR(255),
        ten_linhkien VARCHAR(255),
        loai_linhkien VARCHAR(100),
        linhkien_chon VARCHAR(255),
        so_serial VARCHAR(255),
        user_id INT,

        FOREIGN KEY (id_donhang) REFERENCES donhang(id_donhang) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "<h1>Đang thiết lập Database ĐÚNG 7 CỘT...</h1>";

try {
    foreach ($queries as $query) {
        $pdo->exec($query);
        echo "<p style='color:green;'>✓ Chạy lệnh: " . htmlspecialchars(substr($query, 0, 80)) . "...</p>";
    }
    echo "<h2 style='color:blue;'>THÀNH CÔNG! Database hiện đã chuẩn 7 cột.</h2>";
    echo "<p><a href='ke-toan-tao-don.php' style='padding:10px; background:blue; color:white; text-decoration:none;'>Về trang tạo đơn</a></p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>LỖI:</h2> <pre>" . $e->getMessage() . "</pre>";
}
