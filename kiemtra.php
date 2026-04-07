<?php
require "config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || empty($input['id_donhang']) || empty($input['so_serial'])) {
        echo json_encode(["status" => "error", "message" => "Thiếu thông tin đơn hàng hoặc serial"]);
        exit;
    }

    $id_donhang = (int) $input['id_donhang'];
    $so_serial = trim($input['so_serial']);
    $ten_linhkien = isset($input['ten_linhkien']) ? trim($input['ten_linhkien']) : '';
    $loai_linhkien = isset($input['loai_linhkien']) ? trim($input['loai_linhkien']) : '';
    $so_may = isset($input['so_may']) ? trim($input['so_may']) : '';
    $config_name = isset($input['config_name']) ? trim($input['config_name']) : '';

    try {
        // Tinh chỉnh Database: Tự động thêm id_ct nếu thiếu
        try {
            $check = $pdo->query("SHOW COLUMNS FROM chitiet_donhang LIKE 'id_ct'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE chitiet_donhang ADD id_ct INT AUTO_INCREMENT PRIMARY KEY FIRST");
            }
        } catch (Exception $e) {
        }

        // Bước 1: Lấy linh kiện tương tự (để kiểm tra xem có dòng nào mang cùng tên/loại không)
        $stmt = $pdo->prepare(
            "SELECT * FROM chitiet_donhang 
             WHERE id_donhang = ? AND LOWER(ten_linhkien) = LOWER(?) AND LOWER(loai_linhkien) = LOWER(?) AND LOWER(so_may) = LOWER(?)"
        );
        $stmt->execute([$id_donhang, $ten_linhkien, $loai_linhkien, $so_may]);
        // $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Bước 2: Đếm xem còn bao nhiêu dòng có số Serial này mà ĐANG TRỐNG (chưa gán máy nào)
        // Hoặc đã gán cho đúng máy hiện tại. Dùng LOWER trong SQL để không lỗi function PHP.
        $stmt_check_avail = $pdo->prepare(
            "SELECT id_ct, linhkien_chon FROM chitiet_donhang 
             WHERE id_donhang = ? AND so_serial = ? AND LOWER(loai_linhkien) = LOWER(?) AND LOWER(ten_linhkien) = LOWER(?) AND LOWER(so_may)"
        );
        $stmt_check_avail->execute([$id_donhang, $so_serial, $loai_linhkien, $ten_linhkien, $so_may]);
        $all_matches = $stmt_check_avail->fetchAll(PDO::FETCH_ASSOC);

        if (empty($all_matches)) {
            echo json_encode([
                "status" => "no_match",
                "message" => "Serial [{$so_serial}] không tìm thấy trong kho thuộc loại [{$so_may}] này!"
            ]);
            exit;
        }

        if (empty($all_matches)) {
            echo json_encode([
                "status" => "no_match",
                "message" => "Serial [{$so_serial}] KhÔng được tim thấy thấy trong loại [{$ten_donhang}] nay!"
            ]);
            exit;
        }
        $available_rows = [];
        foreach ($all_matches as $m) {
            $assigned = trim((string) ($m['linhkien_chon'] ?? ''));
            // So sánh không phân biệt hoa thường bằng logic đơn giản hơn
            if ($assigned === '' || mb_strtolower($assigned, 'UTF-8') === mb_strtolower($config_name, 'UTF-8')) {
                $available_rows[] = $m;
            }
        }
        if (empty($available_rows)) {
            // Lấy đại diện 1 cái để báo lỗi xem nó đang ở đâu
            $first_busy = $all_matches[0];
            echo json_encode([
                "status" => "error",
                "message" => "Serial này đã bị chiếm bởi: [{$first_busy['linhkien_chon']}]"
            ]);
            exit;
        }

        // Thành công: Trả về dòng đầu tiên rảnh + Tổng số lượng rảnh có Serial này
        echo json_encode([
            "status" => "match",
            "message" => "Serial hợp lệ",
            "available_count" => count($available_rows),
            "id_ct" => $available_rows[0]['id_ct']
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Lỗi database: " . $e->getMessage()]);
    }
    exit;
}
