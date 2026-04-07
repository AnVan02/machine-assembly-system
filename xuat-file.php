<?php
if (isset($_POST['export_excel'])) {
   require "config.php";

   header("Content-Type: application/vnd.ms-excel; charset=utf-8");
   header("Content-Disposition: attachment; filename=Danh_Sach_So_Serial.xls");
   header("Pragma: no-cache");
   header("Expires: 0");

   $output = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            td { mso-number-format:"\@"; } /* Forces text format so long text/numbers aren\'t converted to scientific notation */
        </style>
    </head>
    <body>
    <table border="1">
        <tr>
            <th style="background-color:#f2f2f2;">ID Đơn Hàng</th>
            <th style="background-color:#f2f2f2;">Tên Khách Hàng</th>
            <th style="background-color:#f2f2f2;">Tên Cấu Hình</th>
            <th style="background-color:#f2f2f2;">Tên Linh Kiện</th>
            <th style="background-color:#f2f2f2;">Loại Linh Kiện</th>
            <th style="background-color:#f2f2f2;">Số Serial</th>
            <th style="background-color:#f2f2f2;">Linh kiện được chọn</th>
            <th style="background-color:#f2f2f2;">User id</th>
        </tr>';

   $sql = "SELECT c.id_donhang, d.ten_khach_hang, c.ten_cauhinh, c.ten_linhkien, c.loai_linhkien, c.linhkien_chon, c.so_serial, d.user_id 
            FROM chitiet_donhang c 
            JOIN donhang d ON c.id_donhang = d.id_donhang
            ORDER BY c.id_donhang DESC, c.loai_linhkien ASC";

   try {
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (count($results) > 0) {
         foreach ($results as $row) {
            $output .= '<tr>
                   <td>' . $row['id_donhang'] . '</td>
                   <td>' . $row['ten_khach_hang'] . '</td>
                   <td>' . $row['ten_cauhinh'] . '</td>
                   <td>' . $row['ten_linhkien'] . '</td>
                   <td>' . $row['loai_linhkien'] . '</td>
                   <td>' . htmlspecialchars((string)$row['so_serial']) . '</td>
                   <td>' . ($row['linhkien_chon'] ?? '') . '</td>
                   <td>' . $row['user_id'] . '</td>
               </tr>';
         }
      } else {
         $output .= '<tr><td colspan="8">Không có dữ liệu</td></tr>';
      }
   } catch (PDOException $e) {
      $output .= '<tr><td colspan="8">Lỗi truy vấn: ' . $e->getMessage() . '</td></tr>';
   }

   $output .= '</table></body></html>';
   echo $output;
   exit;
}
?>
<?php require "thanh-dieu-huong.php"; ?>
<link rel="stylesheet" href="./css/xuat-file.css">
<link rel="stylesheet" href="./css/thanh-dieu-huong.css">

<!-- Main Content Area -->
<div class="main-content">
   <div class="page-header">
      <div>
         <h1 class="page-title">Xuất Dữ Liệu Serial</h1>
         <p class="subtitle">Quản lý và trích xuất danh sách số serial của các đơn hàng</p>
      </div>

      <form method="post" action="">
         <button type="submit" name="export_excel" class="btn-export">
            <i class="fas fa-file-excel"></i> Xuất File Excel
         </button>
      </form>
   </div>

   <?php
   require_once "config.php";

   // Lấy tổng số lượng serial
   $total_sql = "SELECT COUNT(*) as total FROM chitiet_donhang";
   $total_stmt = $pdo->query($total_sql);
   $total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

   // Lấy dữ liệu preview (giới hạn 5 dòng mới nhất để demo)
   $preview_sql = "SELECT c.id_donhang, d.ten_khach_hang, c.ten_cauhinh, c.ten_linhkien, c.loai_linhkien, c.linhkien_chon, c.so_serial 
                  FROM chitiet_donhang c 
                  JOIN donhang d ON c.id_donhang = d.id_donhang
                  ORDER BY c.id_donhang DESC, c.loai_linhkien ASC
                  LIMIT 5";
   $preview_stmt = $pdo->prepare($preview_sql);
   $preview_stmt->execute();
   $preview_results = $preview_stmt->fetchAll(PDO::FETCH_ASSOC);
   ?>

   <!-- Stats Section -->
   <div class="stats-container">
      <div class="stat-card">
         <div class="stat-icon">
            <i class="fas fa-barcode"></i>
         </div>
         <div class="stat-info">
            <h3>Tổng số Serial</h3>
            <p><?php echo number_format($total_count); ?></p>
         </div>
      </div>

      <div class="stat-card">
         <div class="stat-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
            <i class="fas fa-shopping-cart"></i>
         </div>
         <div class="stat-info">
            <h3>Đơn hàng liên quan</h3>
            <p>
               <?php
               $order_count_sql = "SELECT COUNT(DISTINCT id_donhang) as total FROM chitiet_donhang";
               $order_count_stmt = $pdo->query($order_count_sql);
               echo $order_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
               ?>
            </p>
         </div>
      </div>
   </div>

   <!-- Preview Table Section -->
   <div class="table-card">
      <div class="card-header">
         <h3 class="card-title"><i class="fas fa-eye"></i> Xem trước dữ liệu</h3>
         <span class="badge-serial">Mới cập nhật</span>
      </div>
      <div class="table-responsive">
         <table class="preview-table">
            <thead>
               <tr>
                  <th>ID Đơn Hàng</th>
                  <th>Khách Hàng</th>
                  <th>Cấu Hình</th>
                  <th>Linh Kiện</th>
                  <th>Số Serial</th>
                  <th>Linh kiện chọn</th>
               </tr>
            </thead>
            <tbody>
               <?php if (count($preview_results) > 0): ?>
                  <?php foreach ($preview_results as $row): ?>
                     <tr>
                        <td><span class="badge-order"><?php echo $row['id_donhang']; ?></span></td>
                        <td><?php echo $row['ten_khach_hang']; ?></td>
                        <td><?php echo $row['ten_cauhinh']; ?></td>
                        <td>
                           <strong><?php echo $row['ten_linhkien']; ?></strong><br>
                           <small style="color: #64748b;"><?php echo $row['loai_linhkien']; ?></small>
                        </td>
                        <td><code><?php echo htmlspecialchars((string)$row['so_serial']); ?></code></td>
                        <td><?php echo $row['linhkien_chon'] ?? ''; ?></td>
                     </tr>
                  <?php
                  endforeach; ?>
               <?php
               else: ?>
                  <tr>
                     <td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-info-circle"></i> Chưa có dữ liệu số serial nào.

                     </td>
                  </tr>
               <?php
               endif; ?>
            </tbody>
         </table>
      </div>
   </div>
</div>

</div>
</body>

</html>