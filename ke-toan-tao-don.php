<?php require "thanh-dieu-huong.php"; ?>

<link rel="stylesheet" href="./css/ke-toan-tao-don.css?v=1.2">
<script src="./js/ke-toan-tao-don.js?v=1.2" defer></script>
<?php
require "config.php";
?>

<main class="main-content-order">
   <!-- Breadcrumbs -->
   <nav class="breadcrumb">
      <a href="#">Đơn hàng</a>
      <span>›</span>
      <a href="#" class="active">Tạo mới</a>
   </nav>

   <!-- Page Title -->
   <h1 class="page-title">Tạo đơn hàng lắp ráp mới</h1>

   <div class="order-container">
      <!-- Customer Information Section -->
      <section class="order-section">
         <h2 class="section-title">
            <i class="fa-regular fa-user"></i> Thông tin khách hàng
         </h2>
         <div class="customer-info-grid">
            <div class="input-group">
               <label>Tên khách hàng</label>
               <div class="input-with-icon">
                  <i class="fa-regular fa-user"></i>
                  <input id="customer_name" type="text" placeholder="Nhập tên khách hàng">
               </div>
            </div>
            <!-- <div class="input-group">
               <label>Số điện thoại</label>
               <div class="input-with-icon">
                  <i class="fa-solid fa-phone"></i>
                  <input id="customer_phone" type="text" placeholder="(+84)">
               </div>
            </div>
            <div class="input-group">
               <label>Mã đơn hàng</label>
               <div class="input-with-icon">
                  <i class="fa-regular fa-folder-open"></i>
                  <input id="order_code" type="text" placeholder="ROSA-A865222">
               </div>
            </div> -->
         </div>
      </section>

      <!-- Configuration Details Section -->
      <section class="order-section">
         <div class="section-header-flex">
            <h2 class="section-title">Cấu hình chi tiết</h2>
            <button class="btn-add-group">+ Thêm nhóm cấu hình</button>
         </div>
         <!-- Configuration Group 1 -->
         <div class="config-group active">
            <div class="config-group-header">
               <div class="header-left">
                  <span class="group-badge">Cấu hình 1</span>
                  <input type="text" class="group-name-input" value="">
                  <span class="header-sep">|</span>
                  <div class="quantity-control-header">
                     <span>Số lượng:</span>
                     <input type="number" class="qty-bubble" value="1">
                  </div>
               </div>
               <div class="header-right">
                  <div class="btn-toggle-accordion" title="Mở rộng/Thu gọn">
                     <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <button class="btn-delete" title="Xóa cấu hình này">
                     <i class="fa-regular fa-trash-can"></i>
                  </button>
               </div>
            </div>

            <div class="config-grid">
               <!-- CPU -->
               <div class="config-row">
                  <label>CPU</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="cpu-list" placeholder="Nhập tên CPU">
                     </div>
                     <button class="btn-link">+ Thêm loại CPU khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>

               <!-- MAINBOARD -->
               <div class="config-row">
                  <label>MAINBOARD</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="mainboard-list" placeholder="Nhập tên MAINBOARD">
                     </div>
                     <button class="btn-link">+ Thêm loại MAINBOARD khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>


               <!-- RAM -->
               <div class="config-row">
                  <label>RAM</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="ram-list" placeholder="Nhập tên RAM">
                     </div>
                     <button class="btn-link">+ Thêm loại RAM khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>

               <!-- SSD -->
               <div class="config-row">
                  <label>SSD</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="ssd-list" placeholder="Nhập tên SSD">
                     </div>
                     <button class="btn-link">+ Thêm loại SSD khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>

               <!-- VGA -->
               <div class="config-row">
                  <label>VGA</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="gpu-list" placeholder="Nhập tên VGA">
                     </div>
                     <button class="btn-link">+ Thêm loại VGA khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>

               <!-- PSU -->
               <div class="config-row">
                  <label>PSU</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="pdu-list" placeholder="Nhập tên PSU">
                     </div>
                     <button class="btn-link">+ Thêm loại PSU khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>

               <!-- WIN -->
               <div class="config-row">
                  <label>WIN</label>
                  <div class="config-field-main">
                     <div class="input-wrapper">
                        <input type="text" list="win-list" placeholder="Nhập tên WIN">
                     </div>
                     <button class="btn-link">+ Thêm loại WIN khác</button>
                  </div>
                  <div class="config-field-qty">
                     <span class="qty-label">SỐ LƯỢNG</span>
                     <input type="number" value="1" class="item-qty">
                  </div>
               </div>
            </div> <!-- End .config-grid -->
         </div>
      </section>
   </div>

   <!-- Sticky Footer -->
   <footer class="order-footer">
      <div class="footer-stats">
         <div class="stat-item">
            <span class="stat-label">TỔNG SỐ</span>
            <div class="stat-value"><strong>01</strong> máy tính</div>
         </div>
         <div class="stat-item border-left">
            <span class="stat-label">SỐ NHÓM CẤU HÌNH</span>
            <div class="stat-value"><strong>01</strong></div>
         </div>
      </div>
      <div class="footer-actions">
         <button class="btn-primary">Tạo đơn hàng</button>
      </div>
   </footer>

</main>

<!-- Các datlist để gợi ý khi nhập tên linh kiện (Trống vì linh_kien_kho đã bị xóa) -->

<datalist id="cpu-list"></datalist>
<datalist id="gpu-list"></datalist>
<datalist id="ram-list"></datalist>
<datalist id="ssd-list"></datalist>
<datalist id="mainboard-list"></datalist>
<datalist id="pdu-list"></datalist>
<datalist id="win-list"></datalist>

<!-- We need to close the app-body and app-container opened in thanh-dieu-huong.php -->
</div> <!-- .app-body -->
</div> <!-- .app-container -->
</body>

</html>