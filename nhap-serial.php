<?php
require "thanh-dieu-huong.php";
require "config.php";

// Lấy ID đơn hàng từ URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Dữ liệu mẫu mặc định (Đã cập nhật tên cột mới)
$order = [
   'ma_don_hang' => 'Mới',
   'ten_khach_hang' => 'Chưa xác định'
];

$components_db = [];

// Lấy dữ liệu thật từ Database
$config_names = [];
if ($pdo) {
   try {
      $stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $db_order = $stmt->fetch();
      if ($db_order)
         $order = $db_order;

      // Lấy chi tiết linh kiện và tên cấu hình (group name)
      $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $db_components = $stmt->fetchAll();
      if (!empty($db_components)) {
         $components_db = $db_components;
         // Lấy danh sách tên nhóm cấu hình duy nhất
         $config_names = array_unique(array_column($db_components, 'ten_donhang'));
      }
   } catch (PDOException $e) {
      // Bỏ qua lỗi nếu bảng chưa có dữ liệu
   }
}
$display_config_name = !empty($config_names) ? implode(", ", $config_names) : "Cấu hình mặc định";
$total_all_target = count($components_db);
?>

<script>
   const currentOrderId = <?php echo $order_id; ?>;
</script>

<main class="main-content-order">

   <!-- ===== PROGRESS HEADER ===== -->
   <div class="progress-header-card">
      <div class="progress-header-main">
         <div class="progress-header-left">
            <h1 class="page-title">Tiến Độ Nhập Serial <?php echo $order_id; ?></h1>
            <p class="page-subtitle">Cấu Hình: <strong style="color: #2563eb;"><?php echo htmlspecialchars($display_config_name); ?></strong> + Số lượng máy: <strong><?php echo htmlspecialchars($order['so_luong_may'] ?? '0'); ?></strong></p>
            <!-- - Đơn hàng: <strong><?php echo htmlspecialchars($order['ma_don_hang'] ?? 'Mới'); ?></strong></p> -->
            <!-- <p class="page-subtitle">Số lương máy: <strong><?php echo htmlspecialchars($order['so_luong_may'] ?? '0'); ?></strong></p> -->
            <div class="overall-percent" id="overallPercent">0%</div>
         </div>
         <div class="progress-header-right">
            <div class="serial-stat">
               <span class="stat-label">ĐÃ NHẬP</span>
               <div class="stat-numbers">
                  <span class="stat-done" id="totalDoneSerial">0</span>
                  <span class="stat-sep">/</span>
                  <span class="stat-total" id="totalAllSerial"><?php echo $total_all_target; ?></span>
                  <span class="stat-unit">Serial</span>
               </div>
            </div>
         </div>
      </div>
      <div class="overall-progress-bar">
         <div class="overall-progress-fill" id="overallProgressFill" style="width: 0%"></div>
      </div>
   </div>


   <!-- ===== COMPONENT LIST ===== -->
   <div class="component-list-header">
      <span class="component-list-title">Danh Sách Linh Kiện Cần Nhập</span>
      <!-- <span class="component-count-badge" id="componentCountBadge"><?php echo count($components_db); ?> Linh kiện</span> -->
   </div>

   <div class="component-list" id="componentList">
      <?php
      // 1. Nhóm linh kiện theo tên cấu hình (Group Name)
      $grouped_by_config = [];
      foreach ($components_db as $comp) {
         $g_name = !empty($comp['ten_donhang']) ? $comp['ten_donhang'] : "Cấu hình chung";

         // 2. Tiếp tục nhóm theo tên linh kiện + loại linh kiện bên trong mỗi nhóm cấu hình
         // Việc này giúp gộp 2 thanh RAM giống nhau thành 1 card
         $comp_key = $comp['loai_linhkien'] . "|" . $comp['ten_linhkien'];

         if (!isset($grouped_by_config[$g_name][$comp_key])) {
            $grouped_by_config[$g_name][$comp_key] = [
               'data' => $comp,
               'count' => 0,
               'serials' => []
            ];
         }
         $grouped_by_config[$g_name][$comp_key]['count']++;
         if (!empty($comp['so_serial'])) {
            $grouped_by_config[$g_name][$comp_key]['serials'][] = $comp['so_serial'];
         }
      }

      $global_idx = 0;
      foreach ($grouped_by_config as $g_name => $comps): ?>
         <!-- <div class="group-section-header" style=" padding-left: 10px; border-left: 5px solid #2563eb; background: #f8fafc; padding-top: 8px; padding-bottom: 8px; border-radius: 0 8px 8px 0;">
            <h3 style="font-size: 16px; color: #1e293b; font-weight: 700; margin: 0; display: flex; align-items: center;">
               <i class="fa-solid fa-layer-group" style="margin-right: 10px; color: #3b82f6;"></i>
               Bộ máy <?php echo htmlspecialchars($g_name); ?>
            </h3>
         </div> -->

         <?php foreach ($comps as $c_key => $group_item):
            $comp = $group_item['data'];
            $target_qty = $group_item['count'];
            $type = $comp['loai_linhkien'];
            $name = $comp['ten_linhkien'];
            $isOpen = ($global_idx === 0) ? 'open' : '';
         ?>
            <div class="component-card <?php echo $isOpen; ?>"
               data-id="<?php echo $global_idx; ?>"
               data-type="<?php echo strtoupper($type); ?>"
               data-name="<?php echo htmlspecialchars($name); ?>"
               data-config="<?php
                              $cp = array_map('trim', explode(',', $comp['ten_cauhinh'] ?? ''));
                              sort($cp);
                              echo htmlspecialchars(implode(', ', $cp));
                              ?>"
               data-choice="<?php echo htmlspecialchars($comp['linhkien_chon'] ?? ''); ?>"
               data-target="<?php echo $target_qty; ?>">
               <div class="component-card-header" onclick="toggleCard(this)">
                  <div class="comp-icon">
                     <?php
                     switch (strtolower($type)) {
                        case 'cpu':
                           echo '<i class="fa-solid fa-microchip"></i>';
                           break;
                        case 'ram':
                           echo '<i class="fa-solid fa-memory"></i>';
                           break;
                        case 'ssd':
                           echo '<i class="fa-solid fa-hard-drive"></i>';
                           break;
                        case 'vga':
                           echo '<i class="fa-solid fa-hard-drive"></i>';
                           break;
                        case 'gpu':
                           echo '<i class="fa-solid fa-display"></i>';
                           break;
                        case 'main':
                        case 'mainboard':
                           echo '<i class="fa-solid fa-square-poll-vertical"></i>';
                           break;
                        case 'psu':
                           echo '<i class="fa-solid fa-plug"></i>';
                           break;
                        default:
                           echo '<i class="fa-solid fa-box"></i>';
                           break;
                     }
                     ?>
                  </div>
                  <div class="comp-info">
                     <div class="comp-name"><?php echo htmlspecialchars($name); ?></div>
                     <div class="comp-meta">
                        <span class="comp-config-tag" style="font-size: 12px; padding: 2px 6px; border-radius: 10px">
                           <?php
                           $cp = array_map('trim', explode(',', $comp['ten_cauhinh'] ?? ''));
                           sort($cp);
                           echo htmlspecialchars(implode(', ', $cp));
                           ?>
                        </span>
                     </div>
                     <div class="comp-total-need">Tổng cấn nhập: <?php echo $target_qty; ?> serial</div>
                  </div>

                  <div class="comp-status-area">
                     <span class="comp-status status-pending">Chưa nhập (0/<?php echo $target_qty; ?>)</span>
                     <div class="header-action-wrap">
                        <button class="btn-nhap-serial" onclick="expandCard(this.closest('.component-card'));event.stopPropagation()">
                           <i class="fa-solid fa-circle-plus"></i> Nhập Serial
                        </button>
                     </div>
                  </div>
               </div>

               <div class="component-card-body">
                  <div class="serial-entry-grid">
                     <div class="serial-textarea-wrap">
                        <label class="entry-label">Dán danh sách Serial (Mỗi dòng một mã)</label>
                        <div class="textarea-hint">Ví dụ:<br>SN-<?php echo strtoupper($type); ?>-001<br>SN-<?php echo strtoupper($type); ?>-002</div>
                        <textarea class="serial-textarea" id="textarea-<?php echo $global_idx; ?>" placeholder="Dán mã serial vào đây..." rows="6"><?php echo isset($group_item['serials']) ? htmlspecialchars(implode("\n", $group_item['serials'])) : ''; ?></textarea>
                        <div class="textarea-footer">
                           <span class="auto-filter-note" style="font-size: 12px;"> Hệ thống sẽ tự động loại bỏ khoản trắng</span>
                           <span class="detected-count" style="font-size: 12px;">Đã nhận diện <strong id="detected-<?php echo $global_idx; ?>">0</strong> serial</span>
                        </div>
                        <div class="error-msg" id="error-<?php echo $global_idx; ?>" style="color: #ef4444; font-size: 12px; font-weight: 600; margin-top: 8px; display: none; background: #FEF2F2; padding: 8px 12px; border-radius: 6px; border: 1px solid #FCA5A5;">
                           <i class="fa-solid fa-triangle-exclamation"></i> Lỗi: không thể nhập thêm <span id="excess-<?php echo $global_idx; ?>">0</span> dữ liệu sql
                        </div><br>
                     </div>
                     <div class="excel-upload-wrap">
                        <!-- <label class="entry-label">Tải lên file Excel</label>
                        <label class="upload-box" for="excel-<?php echo $global_idx; ?>">
                           <i class="fa-regular fa-file-excel"></i>
                           <span>Chọn file .xlsx hoặc .csv</span>
                        </label>
                        <input type="file" id="excel-<?php echo $global_idx; ?>" accept=".xlsx,.csv" hidden> -->

                     </div>
                  </div>
               </div>
            </div>
         <?php
            $global_idx++;
         endforeach; ?>
      <?php
      endforeach; ?>
   </div>

   <div class="page-footer">
      <p class="footer-note">
         <i class="fa-solid fa-circle-info"></i>
         Sau khi xác nhận, toàn bộ thông tin serial sẽ được<br>chuyển đến bộ phận Kỹ thuật để tiến hành láp ráp.
      </p>
      <div class="footer-actions">
         <button class="btn-luu-nhap" id="btnLuuNhap">Lưu nháp</button>
         <button class="btn-xac-nhan" id="btnXacNhan">Xác nhận <i class="fa-solid fa-arrow-right"></i></button>
      </div>
   </div>
</main>

<link rel="stylesheet" href="./css/nhap-serial.css">
<script src="./js/nhap-serial.js"></script>
</body>

</html>