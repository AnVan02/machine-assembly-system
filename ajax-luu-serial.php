<?php
// --- [PHIÊN BẢN MỚI V7 - CHẾ ĐỘ ĐỐI CHIẾU VÀ GÁN MÁY] ---
$log_entry = date('[Y-m-d H:i:s] ') . "AJAX-LUU-SERIAL V7 (LOOKUP MODE) CALLED" . PHP_EOL;
file_put_contents('debug_log.txt', $log_entry . "POST: " . json_encode($_POST) . PHP_EOL, FILE_APPEND);

require "config.php";
header('Content-Type: application/json');
if (!$pdo) { echo json_encode(['success' => false, 'message' => 'Lỗi kết nối']); exit; }
$pdo->exec("SET NAMES utf8mb4");

function extract_so_may($choice) {
    if (preg_match('/M[áàảãạ]y\s*(\d+)/ui', $choice, $matches)) return (int)$matches[1];
    return 1; // Mặc định máy 1 nếu không bóc tách được
}

$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$config_name = isset($_POST['config_name']) ? trim((string) $_POST['config_name']) : '';
$serials = $_POST['serials'] ?? null;

if ($order_id <= 0 || !is_array($serials) || $config_name === '') {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu thiếu']); exit;
}

try {
    $pdo->beginTransaction();
    $so_may_t = isset($_POST['machine_idx']) ? (int)$_POST['machine_idx'] : extract_so_may($config_name);
    $ln_pure = $config_name;
    if (strpos($config_name, '|') !== false) {
        $parts = explode('|', $config_name);
        $ln_pure = mb_strtolower(trim($parts[0]), 'UTF-8');
    }
    
    if ($order_id <= 0 || empty($ln_pure)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ (Thiếu ID đơn hàng hoặc Tên cấu hình)']);
        exit;
    }

    // BƯỚC 1: XÁC ĐỊNH LINH KIỆN NÀO THỰC SỰ ỨNG VỚI SERIAL QUÉT ĐƯỢC
    // Câu lệnh cập nhật dựa trên Serial có sẵn trong DB
    // BƯỚC 1: GIẢI PHÓNG CÁC LINH KIỆN CŨ ĐANG GÁN CHO MÁY NÀY
    // Việc này đảm bảo không có 2 linh kiện cùng loại tranh nhau 1 vị trí máy
    $stmt_clear = $pdo->prepare("UPDATE chitiet_donhang SET linhkien_chon = NULL, so_may = 0 
                                WHERE id_donhang = ? AND linhkien_chon = ? AND so_may = ?");
    $stmt_clear->execute([$order_id, $ln_pure, $so_may_t]);

    // BƯỚC 2: GÁN CÁC LINH KIỆN MỚI
    $stmt_update_by_id = $pdo->prepare("UPDATE chitiet_donhang 
                                           SET linhkien_chon = ?, so_may = ? 
                                           WHERE id_ct = ? AND id_donhang = ?");

    $stmt_update_by_serial = $pdo->prepare("UPDATE chitiet_donhang 
                                            SET linhkien_chon = ?, so_may = ? 
                                            WHERE id_donhang = ? AND so_serial = ? AND loai_linhkien = ? AND ten_linhkien = ?
                                            LIMIT 1");

    $updated = 0;
    foreach ($serials as $item) {
        $val = isset($item['val']) ? strtoupper(trim((string) $item['val'])) : '';
        $type = isset($item['type']) ? trim((string) $item['type']) : '';
        $name = isset($item['name']) ? trim((string) $item['name']) : '';
        $id_ct = isset($item['id_ct']) ? (int)$item['id_ct'] : 0;

        if ($val === '') continue;

        if ($id_ct > 0) {
            $stmt_update_by_id->execute([$ln_pure, $so_may_t, $id_ct, $order_id]);
            $updated++;
        } else if ($type !== '' && $name !== '') {
            $stmt_update_by_serial->execute([$ln_pure, $so_may_t, $order_id, $val, $type, $name]);
            $updated++;
        }
    }

    $pdo->commit();
    
    $msg = $updated > 0 ? "Đã lưu thành công cho $updated linh kiện!" : "Dữ liệu đã được đồng bộ!";
    echo json_encode(['success' => true, 'message' => $msg, 'updated' => $updated]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
