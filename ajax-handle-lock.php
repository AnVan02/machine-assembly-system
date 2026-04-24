<?php
session_start();
require "config.php";
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$action = $_POST['action'] ?? '';
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$machine_idx = isset($_POST['machine_idx']) ? (int)$_POST['machine_idx'] : 0;
$config_name = isset($_POST['config_name']) ? mb_strtolower(trim($_POST['config_name']), 'UTF-8') : '';

if ($action === 'check') {
    // 1. Kiểm tra xem người dùng này có đang làm máy nào khác không
    $stmt = $pdo->prepare("SELECT id_donhang, so_may, config_name FROM trang_thai_lap_may 
                           WHERE user_id = ? AND (id_donhang != ? OR so_may != ? OR config_name != ?)
                           LIMIT 1");
    $stmt->execute([$user_id, $order_id, $machine_idx, $config_name]);
    $other_machine = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($other_machine) {
        echo json_encode([
            'success' => true,
            'status' => 'busy',
            'message' => "Bạn đang làm dở máy số " . $other_machine['so_may'] . " của đơn hàng ID " . $other_machine['id_donhang'] . " (" . $other_machine['config_name'] . "). Bạn có muốn chuyển qua máy này không?",
            'other' => $other_machine
        ]);
    } else {
        // Kiểm tra xem có ai (người khác) ĐANG làm máy này không
        $stmt = $pdo->prepare("SELECT t.user_id, u.fullname FROM trang_thai_lap_may t 
                               LEFT JOIN users u ON t.user_id = u.id
                               WHERE t.id_donhang = ? AND t.so_may = ? AND t.config_name = ? AND t.user_id IS NOT NULL AND t.user_id != ?
                               LIMIT 1");
        $stmt->execute([$order_id, $machine_idx, $config_name, $user_id]);
        $someone_else = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($someone_else) {
            $locker_name = $someone_else['fullname'] ?: "ID " . $someone_else['user_id'];
            echo json_encode([
                'success' => true,
                'status' => 'locked',
                'message' => "Máy này đang được [{$locker_name}] xử lý. Vui lòng chọn máy khác!"
            ]);
        } else {
            // Kiểm tra xem chính mình có đang khóa máy này không
            $stmt_self = $pdo->prepare("SELECT id FROM trang_thai_lap_may 
                                        WHERE id_donhang = ? AND so_may = ? AND config_name = ? AND user_id = ?
                                        LIMIT 1");
            $stmt_self->execute([$order_id, $machine_idx, $config_name, $user_id]);
            if ($stmt_self->fetch()) {
                echo json_encode(['success' => true, 'status' => 'my_lock']);
            } else {
                echo json_encode(['success' => true, 'status' => 'available']);
            }
        }
    }
} elseif ($action === 'lock') {
    $force = isset($_POST['force']) && $_POST['force'] == '1';

    if ($force) {
        // Xoá user_id của user này ở TẤT CẢ các máy khác (Cả bảng trạng thái và bảng chi tiết)
        $stmt_detail = $pdo->prepare("UPDATE chitiet_donhang SET user_id = NULL WHERE user_id = ?");
        $stmt_detail->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM trang_thai_lap_may WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    // Kiểm tra chéo một lần nữa trước khi thực hiện khóa (Phòng trường hợp 2 người cùng nhấn)
    $stmt_occupied = $pdo->prepare("SELECT user_id FROM trang_thai_lap_may 
                                    WHERE id_donhang = ? AND so_may = ? AND config_name = ? AND user_id IS NOT NULL AND user_id != ?
                                    LIMIT 1");
    $stmt_occupied->execute([$order_id, $machine_idx, $config_name, $user_id]);
    $occupied = $stmt_occupied->fetch();


    if ($occupied && !$force) {
        echo json_encode([
            'success' => false,
            'message' => 'Rất tiếc, máy đã được sử đụng bởi id người dùng khác '
        ]);
        exit;
    }

    // Cấp Token cho phép vào máy 
    $_SESSION['ENTRY_TOKEN'] = "{$order_id}_{$machine_idx}_{$config_name}";

    // Kiểm tra xem đã có khóa của chính user này chưa
    $stmt_check = $pdo->prepare("SELECT id FROM trang_thai_lap_may WHERE id_donhang = ? AND so_may = ? AND config_name = ? AND user_id = ?");
    $stmt_check->execute([$order_id, $machine_idx, $config_name, $user_id]);
    if (!$stmt_check->fetch()) {
        $m_key = "{$order_id}_{$machine_idx}_{$config_name}";
        $_SESSION['FRESH_LOCK_' . $m_key] = true;
    }

    // Chỉ cập nhật hoặc chèn nếu KHÔNG ai chiếm (hoặc chính mình chiếm)
    $stmt = $pdo->prepare("INSERT INTO trang_thai_lap_may (id_donhang, so_may, config_name, user_id) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE user_id = ?");
    $stmt->execute([$order_id, $machine_idx, $config_name, $user_id, $user_id]);

    // Cấp Token cho phép vào máy (Phòng chống copy URL)
    $_SESSION['ENTRY_TOKEN'] = "{$order_id}_{$machine_idx}_{$config_name}";

    echo json_encode(['success' => true, 'message' => 'Đã khóa máy']);
} elseif ($action === 'unlock') {
    $stmt = $pdo->prepare("DELETE FROM trang_thai_lap_may WHERE id_donhang = ? AND so_may = ? AND config_name = ? AND user_id = ?");
    $stmt->execute([$order_id, $machine_idx, $config_name, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Đã mở khóa máy']);
}
