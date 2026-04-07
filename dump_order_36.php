<?php
require "config.php";
if ($pdo) {
    // TỰ ĐỘNG THÊM CỘT ID_CT NẾU THIẾU
    try {
        $pdo->exec("ALTER TABLE chitiet_donhang ADD id_ct INT AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "✓ Đã thêm cột id_ct thành công!\n";
    } catch (Exception $e) {
        echo "ℹ Cột id_ct đã có hoặc chưa cần cập nhật.\n";
    }

    $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ?");
    $stmt->execute([36]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "DỮ LIÊU ĐƠN HÀNG 36:\n";
    print_r($rows);
}
