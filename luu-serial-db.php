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

if (!$pdo) respondError('Lỗi kết nối database.');

$input = $input_raw;
$data = json_decode($input, true);

if (!$data || !isset($data['order_id']) || !isset($data['serials_data'])) {
    respondError('Dữ liệu không hợp lệ.');
}

$order_id = (int)$data['order_id'];
$serials_data = $data['serials_data'];

try {
    $pdo->beginTransaction();

    foreach ($serials_data as $group) {
        $type = $group['type'];
        $name = $group['name'];
        $choice = isset($group['linhkien_chon']) ? trim((string)$group['linhkien_chon']) : '';
        $serials = $group['serials'];

        // 1. Reset serial cũ và linhkien_chon CHỈ CHO MÁY NÀY
        $stmt_reset = $pdo->prepare("UPDATE chitiet_donhang SET so_serial = '', linhkien_chon = NULL 
                                     WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? 
                                     AND (linhkien_chon = ? OR linhkien_chon IS NULL OR linhkien_chon = '')");
        $stmt_reset->execute([$order_id, $type, $name, $choice]);

        $rowsReset = $stmt_reset->rowCount();
        file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . "Reset $rowsReset rows for $name ($type) on choice $choice" . PHP_EOL, FILE_APPEND);

        // 2. Cập nhật từng serial mới
        $stmt_update = $pdo->prepare("UPDATE chitiet_donhang SET so_serial = ? 
                                      WHERE id_donhang = ? AND loai_linhkien = ? AND ten_linhkien = ? 
                                      AND (linhkien_chon = ? OR linhkien_chon IS NULL OR linhkien_chon = '')
                                      AND (so_serial = '' OR so_serial IS NULL) 
                                      LIMIT 1");

        foreach ($serials as $sn) {
            $sn = strtoupper(trim((string)$sn));
            $stmt_update->execute([$sn, $order_id, $type, $name, $choice]);
            $updated = $stmt_update->rowCount();
            file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . "Updated serial $sn for $name, choice: $choice, affected: $updated" . PHP_EOL, FILE_APPEND);
        }
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Lưu serial thành công.']);
} catch (Exception $e) {
    if ($pdo) $pdo->rollBack();
    respondError('Lỗi database: ' . $e->getMessage());
}
