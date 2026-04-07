<?php
require "config.php";
echo "<h1>Hệ thống Debug Đơn Hàng</h1>";
if ($pdo) {
    echo "<h3>5 Đơn hàng mới nhất:</h3>";
    $stmt = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM chitiet_donhang WHERE id_donhang = d.id_donhang) as components_count FROM donhang d ORDER BY id_donhang DESC LIMIT 5");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Mã đơn</th><th>Khách hàng</th><th>Số máy (donhang)</th><th>Số dòng (chitiet)</th><th>Ngày tạo</th></tr>";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id_donhang']}</td>";
        echo "<td>{$row['ma_don_hang']}</td>";
        echo "<td>{$row['ten_khach_hang']}</td>";
        echo "<td>{$row['so_luong_may']}</td>";
        echo "<td>" . ($row['components_count'] > 0 ? "<strong>{$row['components_count']}</strong>" : "<span style='color:red;'>0 (LỖI)</span>") . "</td>";
        echo "<td>{$row['ngay_tao']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Kiểm tra cấu trúc bảng chitiet_donhang:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE chitiet_donhang");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}
?>
