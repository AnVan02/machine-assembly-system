<?php
// --- NHẬT KÝ KIỂM TRA NGUỒN GỌI ---
$log_entry = date('[Y-m-d H:i:s] ') . "AJAX-LUU-SERIAL CALLED | Caller: " . ($_SERVER['HTTP_REFERER'] ?? 'Unknown') . " | URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
$post_data = "POST: " . json_encode($_POST) . " | RAW: " . file_get_contents('php://input') . PHP_EOL;
file_put_contents('debug_log.txt', $log_entry . $post_data, FILE_APPEND);

require "config.php";
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Không có kết nối Database.']);
    exit;
}

// Hàm trích xuất số máy từ chuỗi "Máy X"
function extract_so_may($choice)
{
    if (preg_match('/M[áàảãạ]y\s*(\d+)/ui', $choice, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

// Frontend gửi FormData:
// - order_id
// - config_name (vd: "Cấu hình 2 | Máy 1")
// - serials[i][val], serials[i][old_val], serials[i][name], serials[i][type], serials[i][id_ct]
$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$config_name = isset($_POST['config_name']) ? trim((string) $_POST['config_name']) : '';
$serials = $_POST['serials'] ?? null;

if ($order_id <= 0 || !is_array($serials) || $config_name === '') {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Tinh chỉnh Database: Tự động thêm id_ct nếu thiếu (Để không cần chạy file phụ)
    try {
        $check = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'id_ct'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE chitiet_donhang ADD id_ct INT AUTO_INCREMENT PRIMARY KEY FIRST");
        }
    } catch (Exception $e) { /* Đã có hoặc không lỗi */
    }

    // Cập nhật serial + linhkien_chon theo id_ct (chính xác nhất)
    $stmt_by_id = $pdo->prepare(
        "UPDATE chitiet_donhang
         SET so_serial = ?, linhkien_chon = ?, so_may = ?
         WHERE id_ct = ? AND id_donhang = ?
         LIMIT 1"
    );

    // Fallback: cập nhật theo tên linh kiện + loại (khi không có id_ct)
    // Cho phép cập nhật cả những dòng ĐÃ có serial này rồi (để người dùng lưu lại được)
    $stmt_by_name = $pdo->prepare(
        "UPDATE chitiet_donhang
         SET so_serial = ?, linhkien_chon = ?, so_may = ?
         WHERE id_donhang = ? AND ten_linhkien = ? AND loai_linhkien = ?
         AND (
            (so_serial IS NULL OR so_serial = '' OR so_serial = ?)
            OR (linhkien_chon IS NULL OR linhkien_chon = '' OR linhkien_chon = ?)
         )
         LIMIT 1"
    );

    // Giải phóng serial cũ khi đổi serial
    $stmt_clear_old = $pdo->prepare(
        "UPDATE chitiet_donhang
         SET so_serial = NULL, linhkien_chon = NULL
         WHERE id_donhang = ? AND ten_linhkien = ? AND loai_linhkien = ?
           AND so_serial = ? AND linhkien_chon = ?
         LIMIT 1"
    );

    $updated_rows = 0;
    foreach ($serials as $item) {
        $val = isset($item['val']) ? strtoupper(trim((string) $item['val'])) : '';
        $old_val = isset($item['old_val']) ? trim((string) $item['old_val']) : '';
        $name = isset($item['name']) ? trim((string) $item['name']) : '';
        $type = isset($item['type']) ? trim((string) $item['type']) : '';
        $id_ct = isset($item['id_ct']) ? (int) $item['id_ct'] : 0;
        $choice = isset($item['choice']) ? trim((string) $item['choice']) : $config_name;

        if ($val === '' || $name === '' || $type === '') {
            continue;
        }

        // Giải phóng serial cũ nếu có sự thay đổi
        if ($old_val !== '' && $old_val !== $val) {
            $stmt_clear_old->execute([$order_id, $name, $type, $old_val, $choice]);
        }

        // Ghi Log vào file để AI kiểm tra
        // Thêm Log để debug nguồn gọi
        $log_entry = date('[Y-m-d H:i:s] ') . "Caller: " . ($_SERVER['HTTP_REFERER'] ?? 'Unknown') . " | URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
        file_put_contents('debug_log.txt', $log_entry, FILE_APPEND);
        file_put_contents('debug_log.txt', "POST Data: " . print_r($_POST, true), FILE_APPEND);
        file_put_contents('debug_log.txt', "ID_CT: $id_ct | Serial: $val | Choice: $choice | Order: $order_id\n", FILE_APPEND);

        // Trích xuất số máy và tách tên cấu hình
        $so_may = extract_so_may($choice);

        // tách phần tên cấu hình (bỏ phần | máy x nếu có )
        $linhkien_chon = $choice;
        if (strpos($choice, '|') !== false) {
            $sparts = explode('|', $choice);
            $linhkien_chon = trim($sparts[0]);
        }


        // Ghi serial + gán linhkien_chon + so_may
        if ($id_ct > 0) {
            $stmt_by_id->execute([$val, $linhkien_chon, $so_may, $id_ct, $order_id]);
            $updated_rows += $stmt_by_id->rowCount();
        } else {
            $stmt_by_name->execute([$val, $linhkien_chon, $so_may, $order_id, $name, $type, $val, $linhkien_chon]);
            $updated_rows += $stmt_by_name->rowCount();
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Đã lưu Serial thành công! (Cập nhật $updated_rows dòng)", 'updated' => $updated_rows]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents('debug_log.txt', "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}
