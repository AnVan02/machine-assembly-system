<?php
require "config.php";
if ($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBING chitiet_donhang");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "CẤU TRÚC BẢNG chitiet_donhang:\n";
        foreach ($cols as $c) {
            echo "- " . $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    } catch (Exception $e) {
        // Nếu DESCRIBING có vấn đề, thử DESCRIBE
        $stmt = $pdo->query("DESCRIBE chitiet_donhang");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "CẤU TRÚC BẢNG chitiet_donhang:\n";
        foreach ($cols as $c) {
            echo "- " . $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    }
} else {
    echo "Không kết nối được PDO";
}
?>