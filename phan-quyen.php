<?php
session_start();

// Giả lập quyền hạn (Sau này bạn có thể thay bằng dữ liệu từ Database)
// Role: 'admin' (Tài khoản 2) hoặc 'ketoan' (Tài khoản 1)

// Nếu chưa đăng nhập, mặc định là ketoan để test (HOẶC bạn có thể tạo trang login)
if (!isset($_SESSION['user_role'])) {
   $_SESSION['user_role'] = 'ketoan'; // Đây là tài khoản 1
}

$role = $_SESSION['user_role'];

// Danh sách các trang mà Kế toán (Tài khoản 1) ĐƯỢC PHÉP vào
$allowed_ketoan = [
   'dashboard-ke-toan.php',
   'ke-toan-tao-don.php'
];

$current_page = basename($_SERVER['PHP_SELF']);

// Kiểm tra: Nếu là Kế toán mà vào trang KHÔNG có trong danh sách cho phép
if ($role == 'ketoan' && !in_array($current_page, $allowed_ketoan)) {
   // Nếu trang hiện tại không phải là trang được phép, đẩy về trang liệt kê đơn hàng
   header("Location: dashboard-ke-toan.php?error=no_access");
   exit();
}
