<?php
header('Content-Type: application/json');
require "config.php";

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Danh sách xóa trống.']);
    exit;
}

try {
    // Bắt đầu transaction để đảm bảo xóa sạch cả 2 bảng
    $pdo->beginTransaction();

    // 1. Xóa chi tiết đơn hàng trước
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    $stmt1 = $pdo->prepare("DELETE FROM chitiet_donhang WHERE id_donhang IN ($inQuery)");
    $stmt1->execute($ids);

    // 2. Xóa đơn hàng chính
    $stmt2 = $pdo->prepare("DELETE FROM donhang WHERE id_donhang IN ($inQuery)");
    $stmt2->execute($ids);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}
