<?php
session_start();
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
    file_put_contents('debug_log.txt', "[INFO] Transaction started" . PHP_EOL, FILE_APPEND);

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

        // CHỈNH SỬA: Xác định cách lấy linh kiện (theo máy cụ thể hoặc lấy tất cả để nhập tổng quát)
        if ($so_may > 0) {
            // Nếu có số máy (từ kho-import-serial.php), ưu tiên tìm đúng hàng của máy đó
            $sql_query = "SELECT id_ct, so_serial FROM chitiet_donhang 
                          WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? AND so_may = ?
                          ORDER BY id_ct ASC";
            $params = [$order_id, $type, $name, $so_may];
            $stmt_current = $pdo->prepare($sql_query);
            $stmt_current->execute($params);
            $current_rows = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

            // Nếu máy này chưa được gán bất kỳ linh kiện nào, ta lấy các hàng chưa gán (so_may = 0) làm dự phòng
            if (empty($current_rows)) {
                $sql_query = "SELECT id_ct, so_serial FROM chitiet_donhang 
                              WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? AND (so_may = 0 OR so_may IS NULL)
                              ORDER BY id_ct ASC";
                $stmt_current = $pdo->prepare($sql_query);
                $stmt_current->execute([$order_id, $type, $name]);
                $current_rows = $stmt_current->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Nếu không có số máy (từ nhap-serial.php), lấy tất cả linh kiện loại này trong đơn hàng để cập nhật hàng loạt
            $sql_query = "SELECT id_ct, so_serial FROM chitiet_donhang 
                          WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? 
                          ORDER BY id_ct ASC";
            $stmt_current = $pdo->prepare($sql_query);
            $stmt_current->execute([$order_id, $type, $name]);
            $current_rows = $stmt_current->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Cập nhật vị trí (Positional Update)
        $all_slots = $current_rows;

        // Lấy ID người dùng từ Session
        $user_id = $_SESSION['user_id'] ?? NULL;

        foreach ($all_slots as $index => $slot) {
            if (isset($serials[$index])) {
                $sn = $serials[$index];
                if ($sn !== $slot['so_serial']) {
                    // Cập nhật serial cho chính dòng này + ghi nhận User thực hiện
                    $stmt = $pdo->prepare("UPDATE chitiet_donhang SET so_serial = ?, so_may = NULL, linhkien_chon = NULL, user_id = ? WHERE id_ct = ?");
                    $stmt->execute([$sn, $user_id, $slot['id_ct']]);
                }
            } else {
                if (!empty($slot['so_serial'])) {
                    // Xóa serial nhưng vẫn ghi nhận User đã thao tác xóa
                    $pdo->prepare("UPDATE chitiet_donhang SET so_serial = '', so_may = NULL, linhkien_chon = NULL, user_id = ? WHERE id_ct = ?")
                        ->execute([$user_id, $slot['id_ct']]);
                }
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
    file_put_contents('debug_log.txt', "[INFO] Transaction committed" . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => true, 'message' => 'Lưu serial thành công.']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
        file_put_contents('debug_log.txt', "[ERROR] Transaction rolled back: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
    respondError('Lỗi database: ' . $e->getMessage());
}
