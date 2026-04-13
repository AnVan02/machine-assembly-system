<?php
// --- NHẬT KÝ KIỂM TRA NGUỒN GỌI ---
$log_entry = date('[Y-m-d H:i:s] ') . "LUU-SERIAL-DB CALLED | Caller: " . ($_SERVER['HTTP_REFERER'] ?? 'Unknown') . PHP_EOL;
$input_raw = file_get_contents('php://input');
file_put_contents('debug_log.txt', $log_entry . "INPUT: " . $input_raw . PHP_EOL, FILE_APPEND);

require "config.php";
header('Content-Type: application/json');

function respondError($message)
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Hàm trích xuất số máy từ chuỗi "Máy X"
function extract_so_may($choice)
{
    if (preg_match('/M[áàảãạ]y\s*(\d+)/ui', $choice, $matches)) {
        return (int) $matches[1];
    }
    return 0;
}

if (!$pdo)
    respondError('Lỗi kết nối database.');

$input = $input_raw;
$data = json_decode($input, true);

if (!$data || !isset($data['order_id']) || !isset($data['serials_data'])) {
    respondError('Dữ liệu không hợp lệ.');
}

$order_id = (int) $data['order_id'];
$serials_data = $data['serials_data'];

try {
    $pdo->beginTransaction();

    foreach ($serials_data as $group) {
        $type = $group['type'];
        $name = $group['name'];
        $choice = isset($group['linhkien_chon']) ? trim((string) $group['linhkien_chon']) : '';
        $serials = array_map(function ($s) {
            return strtoupper(trim((string) $s));
        }, $group['serials']);
        $serials = array_filter($serials); // Bỏ chuỗi rỗng

        // Phân tích choice để lấy so_may và linhkien_chon thực tế
        $so_may = extract_so_may($choice);
        $lk_chon_db = $choice;
        if (strpos($choice, '|') !== false) {
            $parts = explode('|', $choice);
            $lk_chon_db = trim($parts[0]);
        }

        // 1. Tìm các serial hiện đang gán cho "Máy" này
        // Hỗ trợ gộp: Nếu $lk_chon_db chứa nhiều cấu hình (cách nhau dấu phẩy), ta tìm tất cả.
        $cfg_list = array_map('trim', explode(',', $lk_chon_db));
        // MỚI: Thêm cả chuỗi gốc (chứa dấu phẩy) vào danh sách tìm kiếm để khớp với TRIM(ten_cauhinh) cho linh kiện gộp
        if (count($cfg_list) > 1) {
            $cfg_list[] = $lk_chon_db;
        }
        $cfg_list = array_values(array_unique($cfg_list));
        $placeholders = implode(',', array_fill(0, count($cfg_list), '?'));

        $sql_query = "SELECT id_ct, so_serial FROM chitiet_donhang 
                      WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? 
                      AND (linhkien_chon IN ($placeholders) OR TRIM(ten_cauhinh) IN ($placeholders) OR (so_may = ? AND so_may > 0))";

        $params = array_merge([$order_id, $type, $name], $cfg_list, $cfg_list, [$so_may]);
        $stmt_current = $pdo->prepare($sql_query);
        $stmt_current->execute($params);
        $current_rows = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

        // 2. Phân loại các dòng hiện tại của máy này: dòng có serial và dòng trống
        $existing_assigned = [];
        $empty_slots = [];
        foreach ($current_rows as $row) {
            if (!empty($row['so_serial'])) {
                $existing_assigned[] = $row;
            } else {
                $empty_slots[] = $row;
            }
        }

        // (A) Giải phóng: Nếu serial cũ của máy này không nằm trong danh sách mới
        // Lưu ý: Nếu input có ['A'], mà DB có ['A', 'A'], thì dòng thứ 2 của 'A' cũng sẽ bị giải phóng
        // Để chính xác, ta dùng một bản sao của input để đánh dấu
        $temp_serials_input = $serials;
        foreach ($existing_assigned as $row) {
            $sn = $row['so_serial'];
            $found_idx = array_search($sn, $temp_serials_input);
            if ($found_idx !== false) {
                // Serial này còn giữ lại, xoá khỏi danh sách tạm để không gán trùng vào slot trống sau này
                unset($temp_serials_input[$found_idx]);
            } else {
                // Giải phóng row này
                $pdo->prepare("UPDATE chitiet_donhang SET so_serial = '', linhkien_chon = NULL, so_may = 0 WHERE id_ct = ?")
                    ->execute([$row['id_ct']]);
                // Sau khi giải phóng, dòng này trở thành slot trống mới
                $empty_slots[] = $row;
            }
        }
        // (B) Thêm mới: Những serial còn dư trong $temp_serials_input (chưa được gán) sẽ gán vào slot trống
        foreach ($temp_serials_input as $sn) {
            if (!empty($empty_slots)) {
                $slot = array_shift($empty_slots);
                $pdo->prepare("UPDATE chitiet_donhang SET so_serial = ? WHERE id_ct = ?")
                    ->execute([$sn, $slot['id_ct']]);
            }
        }


        // nếu cập nhập 3 4 thì dôi thành 4 3 va được serial 4 3
        // foreach ($all_slots as $index => $slot) {
        //     if (isset($serials[$index])) {
        //         $sn = $serials[$index];
        //         // Giải phóng serial này ở bất kỳ đâu khác trong đơn hàng (tránh trùng)
        //         $pdo->prepare("UPDATE chitiet_donhang SET so_may = 0, linhkien_chon = NULL 
        //                           WHERE id_donhang = ? AND so_serial = ? AND id_ct != ?")
        //             ->execute([$order_id, $sn, $slot['id_ct']]);

        //         // Cập nhật cho slot hiện tại: Gán serial và gán máy
        //         $final_lk = ($so_may > 0) ? $lk_chon_db : null;
        //         $pdo->prepare("UPDATE chitiet_donhang SET so_serial = ?, so_may = ?, linhkien_chon = ? WHERE id_ct = ?")
        //             ->execute([$sn, $so_may, $final_lk, $slot['id_ct']]);
        //     } else {
        //         // Slot dư hoặc bị người dùng xóa nội dung: giải phóng
        //         $pdo->prepare("UPDATE chitiet_donhang SET so_serial = NULL, so_may = 0, linhkien_chon = NULL WHERE id_ct = ?")
        //             ->execute([$slot['id_ct']]);
        //     }
        // }

    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Lưu serial thành công.']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction())
        $pdo->rollBack();
    respondError('Lỗi database: ' . $e->getMessage());
}
