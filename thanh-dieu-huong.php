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
function isActive($page, $current_page)
{
   return ($page === $current_page) ? 'active' : '';
}
?>

<?php
if (isset($_GET['action'])) {
   $action = $_GET['action'];
} else {
   $action = "-1";
}
if (isset($_GET['query'])) {
   $quest = $_GET['query'];
} else {
   $quest = "-1 ";
}

?>

<body>
   <div class="app-container">
      <!-- Mobile Header -->
      <header class="top-header">
         <div class="header-logo">
            <img src="./image/logo.png" alt="ROSA Logo" style="height: 40px; width: auto;">
         </div>
         <button class="menu-toggle" id="mobile-toggle">
            <i class="fa-solid fa-bars"></i>
         </button>
      </header>

      <!-- Body Container -->
      <div class="app-body">
         <!-- Sidebar -->
         <aside class="sidebar">
            <div class="sidebar-top">
               <div class="sidebar-logo">
                  <img src="./image/logo.png" alt="ROSA Logo">
               </div>
            </div>
            <!-- Links section follows... -->

            <div class="sidebar-links">
               <a href="dashboard-ky-thuat.php"
                  class="nav-item <?php echo isActive('dashboard-ky-thuat.php', $current_page); ?>">
                  <i class="fa-solid fa-gauge-high"></i>
                  <p>Dashboard Kỹ Thuật</p>
               </a>

               <a href="dashboard-ke-toan.php"
                  class="nav-item <?php echo isActive('dashboard-ke-toan.php', $current_page); ?>">
                  <i class="fa-solid fa-list-check"></i>
                  <p>Dashboard Kế Toán</p>
               </a>

               <!-- <a href="check-quality.php" class="nav-item <?php echo isActive('check-quality.php', $current_page); ?>">
                  <i class="fa-solid fa-chart-line"></i>
                  <p>Dashboard Kỹ thuật</p>
               </a> -->

               <a href="ke-toan-tao-don.php"
                  class="nav-item <?php echo isActive('ke-toan-tao-don.php', $current_page); ?>">
                  <i class="fa-solid fa-plus-circle"></i>
                  <p>Kế toán tạo đơn</p>
               </a>

               <!-- <a href="kho-import-serial.php<?php echo isset($_GET['id']) ? '?id=' . $_GET['id'] : ''; ?>" class="nav-item <?php echo isActive('kho-import-serial.php', $current_page); ?>">
                  <i class="fa fa-list-ol"></i>
                  <p>Import Serial</p>
               </a> -->

               <a href="check-quality.php" class="nav-item <?php echo isActive('check-quality.php', $current_page); ?>">
                  <i class="fa-solid fa-square-poll-vertical"></i>
                  <p>Kiểm tra đơn hàng</p>
               </a>

               <a href="xuat-file.php" class="nav-item <?php echo isActive('xuat-file.php', $current_page); ?>">
                  <i class="fa-regular fa-file-excel"></i>
                  <p>Xuất Excel</p>
               </a>

            </div>
         </aside>
         <div class="sidebar-overlay"></div>
         <script src="./js/thanh-dieu-huong.js"></script>