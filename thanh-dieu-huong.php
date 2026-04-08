<?php require_once 'phan-quyen.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <!-- Google Fonts -->
   <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

   <link rel="stylesheet" href="./css/thanh-dieu-huong.css">
</head>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
$fullname = $_SESSION['fullname'] ?? 'User';

function isActive($page, $current_page)
{
   return ($page === $current_page) ? 'active' : '';
}
?>

<body>
   <div class="app-container">
      <!-- Mobile Header -->
      <header class="top-header">
         <!-- Logo hiện trên cả Mobile và Desktop -->
         <div class="header-logo">
            <img src="./image/logo.png" alt="ROSA Logo" style="height: 55px; width: auto;">
         </div>

         <!-- Khu vực bên phải: Chứa cả Profile và Menu (khi trên mobile) -->
         <div class="header-actions-right">
            <div class="user-profile-header">
               <div class="profile-info-top">
                  <i class="fa-solid fa-circle-user"></i>
                  <div class="name-role">
                     <span class="user-name">
                        <?php echo htmlspecialchars($fullname); ?>
                     </span>
                     <!-- <span class="user-role">
                        <?php echo strtoupper($role); ?>
                     </span> -->
                  </div>
               </div>
               <a href="auth-logout.php" class="logout-btn-top" title="Đăng xuất">
                  <i class="fa-solid fa-right-from-bracket"></i>
               </a>
            </div>

            <!-- Nút 3 gạch kiểu mới đẹp và hiện đại hơn -->
            <button class="menu-toggle" id="mobile-toggle">
               <i class="fa-solid fa-bars"></i>
            </button>
         </div>
      </header>

      <!-- Body Container -->
      <div class="app-body">
         <!-- Sidebar -->
         <nav class="sidebar">
            <div class="sidebar-top" style="display: none;">
               <div class="sidebar-logo">
                  <img src="./image/logo.png" alt="ROSA Logo">
               </div>
            </div>

            <div class="sidebar-links">

               <!-- NÚT: Dashboard Kỹ Thuật -->
               <!-- Chỉ hiện nếu user có quyền (định nghĩa trong phan-quyen.php) -->
               <?php if (isAuthorized('dashboard-ky-thuat.php')): ?>
                  <a href="dashboard-ky-thuat.php"
                     class="nav-item <?php echo isActive('dashboard-ky-thuat.php', $current_page); ?>">
                     <i class="fa-solid fa-gauge-high"></i>
                     <p>Dashboard Kỹ Thuật</p>
                  </a>
               <?php endif; ?>

               <!-- NÚT: Dashboard Kế Toán -->
               <?php if (isAuthorized('dashboard-ke-toan.php')): ?>
                  <a href="dashboard-ke-toan.php"
                     class="nav-item <?php echo isActive('dashboard-ke-toan.php', $current_page); ?>">
                     <i class="fa-solid fa-list-check"></i>
                     <p>Dashboard Kế Toán</p>
                  </a>
               <?php endif; ?>

               <!-- NÚT: Kế toán tạo đơn -->
               <?php if (isAuthorized('ke-toan-tao-don.php')): ?>
                  <a href="ke-toan-tao-don.php"
                     class="nav-item <?php echo isActive('ke-toan-tao-don.php', $current_page); ?>">
                     <i class="fa-solid fa-plus-circle"></i>
                     <p>Kế toán tạo đơn</p>
                  </a>
               <?php endif; ?>

               <!-- NÚT: Kiểm tra đơn hàng -->
               <?php if (isAuthorized('check-quality.php')): ?>
                  <a href="check-quality.php" class="nav-item <?php echo isActive('check-quality.php', $current_page); ?>">
                     <i class="fa-solid fa-square-poll-vertical"></i>
                     <p>Kiểm tra đơn hàng</p>
                  </a>
               <?php endif; ?>

               <!-- NÚT: Xuất Excel -->
               <?php if (isAuthorized('xuat-file.php')): ?>
                  <a href="xuat-file.php" class="nav-item <?php echo isActive('xuat-file.php', $current_page); ?>">
                     <i class="fa-regular fa-file-excel"></i>
                     <p>Xuất Excel</p>
                  </a>
               <?php endif; ?>

               <!-- PHẦN DƯỚI CÙNG: Thông tin người dùng & Đăng xuất -->
               <div class="sidebar-bottom-links"
                  style="margin-top: auto; border-top: 1px solid #e2e8f0; padding-top: 10px">
                  <!-- Chỉ giữ lại liên kết cần thiết nếu có, hoặc để trống để Profile hiện trên Header -->
                  <a href="auth-logout.php" class="nav-item">
                     <i class="fa-solid fa-right-from-bracket"></i>
                     <p>Đăng xuất</p>
                  </a>
               </div>
            </div>
         </nav>
         <div class="sidebar-overlay"></div>
         <script src="./js/thanh-dieu-huong.js"></script>