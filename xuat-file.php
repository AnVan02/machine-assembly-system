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
            td { mso-number-format:"\@"; }
        </style>
    </head>
    <body>
    <table border="1">
        <tr>
            <th style="background-color:#1e293b; color:white; width:200px;">SERIAL / MÃ MÁY</th>
            <th style="background-color:#1e293b; color:white; width:150px;">LOẠI / CẤU HÌNH</th>
            <th style="background-color:#1e293b; color:white; width:350px;">TÊN LINH KIỆN / TÊN MÁY</th>
        </tr>';

   $sql = "SELECT c.id_donhang, d.ten_khach_hang, d.ngay_tao, c.ten_cauhinh, c.ten_linhkien, c.loai_linhkien, c.linhkien_chon, c.so_serial, 
                  COALESCE(NULLIF(c.so_may, ''), 1) as so_may_fix 
           FROM chitiet_donhang c 
           JOIN donhang d ON c.id_donhang = d.id_donhang
           ORDER BY c.id_donhang DESC, so_may_fix ASC, 
                    CASE 
                       WHEN LOWER(c.loai_linhkien) LIKE '%cpu%' THEN 1
                       WHEN LOWER(c.loai_linhkien) LIKE '%main%' THEN 2
                       WHEN LOWER(c.loai_linhkien) LIKE '%ram%' THEN 3
                       WHEN LOWER(c.loai_linhkien) LIKE '%ssd%' THEN 4
                       WHEN LOWER(c.loai_linhkien) LIKE '%vga%' THEN 5
                       WHEN LOWER(c.loai_linhkien) LIKE '%psu%' THEN 6
                       WHEN LOWER(c.loai_linhkien) LIKE '%win%' THEN 7
                       ELSE 8 
                    END ASC";

   try {
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $grouped = [];
      foreach ($results as $row) {
         $oid = $row['id_donhang'];
         $may = $row['so_may_fix'];
         $cfg = $row['linhkien_chon'] ?: $row['ten_cauhinh'];
         $key = $oid . '_' . $may;

         if (!isset($grouped[$key])) {
            $date_part = date('dmy', strtotime($row['ngay_tao']));
            $cfg_part = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cfg), 0, 15));
            $machine_code = $date_part . "-" . $cfg_part . "-" . str_pad($may, 2, '0', STR_PAD_LEFT);

            $grouped[$key] = [
               'header' => ['code' => $machine_code, 'cfg' => $cfg],
               'items' => []
            ];
         }

         $type = strtoupper($row['loai_linhkien']);
         if (strpos($type, 'MAIN') !== false)
            $type = 'MB';

         $grouped[$key]['items'][] = [
            'sn' => $row['so_serial'] ?: '',
            'tp' => $type,
            'nm' => $row['ten_linhkien']
         ];
      }

      foreach ($grouped as $data) {
         $output .= '<tr style="background-color:#1e3a8a; color:white; font-weight:bold;">
                <td>' . $data['header']['code'] . '</td>
                <td>' . $data['header']['cfg'] . '</td>
                <td>' . $data['header']['cfg'] . '</td>
            </tr>';

         foreach ($data['items'] as $it) {
            $output .= '<tr>
                   <td>' . htmlspecialchars((string) $it['sn']) . '</td>
                   <td style="text-align:center;">' . $it['tp'] . '</td>
                   <td>' . $it['nm'] . '</td>
               </tr>';
         }
         $output .= '<tr style="height:30px;"><td colspan="3" style="border:none;"></td></tr>';
      }
   } catch (PDOException $e) {
      $output .= '<tr><td colspan="3">Lỗi: ' . $e->getMessage() . '</td></tr>';
   }

   $output .= '</table></body></html>';
   echo $output;
   exit;
}
?>
<?php require "thanh-dieu-huong.php"; ?>
<link rel="stylesheet" href="./css/xuat-file.css">
<link rel="stylesheet" href="./css/thanh-dieu-huong.css">

<div class="main-content">
   <div class="page-header">
      <div>
         <h1 class="page-title">Xuất Dữ Liệu Serial</h1>
         <p class="subtitle">Dữ liệu tách biệt hoàn toàn theo từng máy</p>
      </div>

      <form method="post" action="">
         <button type="submit" name="export_excel" class="btn-export">
            <i class="fas fa-file-excel"></i> Xuất File Excel
         </button>
      </form>
   </div>

   <?php
   require_once "config.php";
   $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM chitiet_donhang");
   $total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

   $preview_sql = "SELECT c.id_donhang, d.ten_khach_hang, c.ten_cauhinh, c.ten_linhkien, c.loai_linhkien, c.linhkien_chon, c.so_serial, 
                           COALESCE(NULLIF(c.so_may, ''), 1) as so_may_fix 
                  FROM chitiet_donhang c 
                  JOIN donhang d ON c.id_donhang = d.id_donhang
                  ORDER BY c.id_donhang DESC, so_may_fix ASC, 
                           CASE 
                              WHEN LOWER(c.loai_linhkien) LIKE '%cpu%' THEN 1
                              WHEN LOWER(c.loai_linhkien) LIKE '%main%' THEN 2
                              WHEN LOWER(c.loai_linhkien) LIKE '%ram%' THEN 3
                              WHEN LOWER(c.loai_linhkien) LIKE '%ssd%' THEN 4
                              WHEN LOWER(c.loai_linhkien) LIKE '%vga%' THEN 5
                              WHEN LOWER(c.loai_linhkien) LIKE '%psu%' THEN 6
                              WHEN LOWER(c.loai_linhkien) LIKE '%win%' THEN 7
                              ELSE 8 
                           END ASC
                  LIMIT 50";
   $preview_stmt = $pdo->prepare($preview_sql);
   $preview_stmt->execute();
   $preview_results = $preview_stmt->fetchAll(PDO::FETCH_ASSOC);
   ?>

   <div class="stats-container">
      <div class="stat-card">
         <div class="stat-icon"><i class="fas fa-barcode"></i></div>
         <div class="stat-info">
            <h3>Tổng số Serial</h3>
            <p><?php echo number_format($total_count); ?></p>
         </div>
      </div>
      <div class="stat-card">
         <div class="stat-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;"><i
               class="fas fa-shopping-cart"></i></div>
         <div class="stat-info">
            <h3>Đơn liên quan</h3>
            <p><?php
            $oc_stmt = $pdo->query("SELECT COUNT(DISTINCT id_donhang) as total FROM chitiet_donhang");
            echo $oc_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            ?></p>
         </div>
      </div>
   </div>

   <div class="table-card">
      <div class="card-header">
         <h3 class="card-title"><i class="fas fa-eye"></i> Xem trước dữ liệu (Tách biệt theo máy)</h3>
      </div>
      <div class="table-responsive">
         <table class="preview-table">
            <thead>
               <tr>
                  <th>SERIAL / MÃ MÁY</th>
                  <th style="text-align:center;">LOẠI</th>
                  <th>TÊN LINH KIỆN</th>
               </tr>
            </thead>
            <tbody>
               <?php
               if (count($preview_results) > 0) {
                  $prev_grouped = [];
                  foreach ($preview_results as $row) {
                     $may = $row['so_may_fix'];
                     $key = $row['id_donhang'] . '_' . $may;
                     if (!isset($prev_grouped[$key])) {
                        $prev_grouped[$key] = [
                           'title' => "MÁY " . $may . " (Đơn #" . $row['id_donhang'] . ")",
                           'cfg' => $row['linhkien_chon'] ?: $row['ten_cauhinh'],
                           'items' => []
                        ];
                     }
                     $prev_grouped[$key]['items'][] = $row;
                  }
                  foreach ($prev_grouped as $g) {
                     echo '<tr style="background:#1e3a8a; color:white; font-weight:bold;"><td colspan="3" style="padding:12px 15px;"><i class="fas fa-desktop"></i> ' . strtoupper($g['title']) . ' - ' . $g['cfg'] . '</td></tr>';
                     foreach ($g['items'] as $item) {
                        $t = strtoupper($item['loai_linhkien']);
                        if (strpos($t, 'MAIN') !== false)
                           $t = 'MB';
                        echo '<tr><td><code style="font-weight:bold; color:#059669; font-size:1.1rem;">' . ($item['so_serial'] ?: '<span style="color:#cbd5e1;">(Trống)</span>') . '</code></td><td style="text-align:center; font-weight:bold; background:#f8fafc; color:#475569;">' . $t . '</td><td>' . $item['ten_linhkien'] . '</td></tr>';
                     }
                     echo '<tr style="height:50px; background:white;"><td colspan="3" style="border:none;"></td></tr>';
                  }
               } else {
                  echo '<tr><td colspan="3" style="text-align:center; padding:40px;">Chưa có dữ liệu</td></tr>';
               }
               ?>
            </tbody>
         </table>
      </div>
   </div>
</div>
</div>
</body>

</html>