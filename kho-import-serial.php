<?php

use Dom\Document;

require "config.php";
require "thanh-dieu-huong.php";

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
$config_key_req = isset($_GET['config']) ? mb_strtolower($_GET['config'], 'UTF-8') : '';
$m_idx = isset($_GET['m']) ? (int) $_GET['m'] : 1;

$order = null;
if ($pdo) {
   try {
      $stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      // Giữ đúng thứ tự insert để tránh trộn RAM.
      // Nếu DB chưa có cột id_ct thì fallback để không bị trắng trang (exception bị nuốt).
      $all_items = [];
      try {
         $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
         $stmt->execute([$order_id]);
         $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY ten_cauhinh ASC, loai_linhkien ASC, ten_linhkien ASC, so_serial ASC");
         $stmt->execute([$order_id]);
         $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }

      // 1. Phân nhóm cấu hinh linh kiên cho từng cấu hình 
      $grouped_all = [];
      foreach ($all_items as $item) {
         $c_names = explode(',', $item['ten_cauhinh']);
         foreach ($c_names as $cn) {
            $lbl = trim($cn) ?: 'Cấu hình chung';
            $key = mb_strtolower($lbl, 'UTF-8');
            $grouped_all[$key]['items'][] = $item;
         }
      }
      $config_qtys = [];
      foreach ($grouped_all as $k => $data) {
         // Tính machine count bằng exclusive items (tên đơn, trọn bộ, không có dấu phẩy gộp)
         $exclusive_items = array_filter($data['items'], function ($item) use ($k) {
            $trimmed_name = trim($item['ten_cauhinh']);
            $parts = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $trimmed_name));
            return count($parts) === 1 && trim($parts[0]) === $k;
         });
         $exclusive_type_counts = array_count_values(array_map('mb_strtolower', array_column(array_values($exclusive_items), 'loai_linhkien')));

         $preferred_types = ['cpu', 'main', 'mainboard', 'vga', 'ssd', 'psu', 'nguon', 'win'];
         $qty_guess = 0;
         foreach ($preferred_types as $t) {
            if (!empty($exclusive_type_counts[$t])) {
               $qty_guess = (int) $exclusive_type_counts[$t];
               break;
            }
         }
         // Fallback nếu không có exclusive item
         if ($qty_guess === 0) {
            $all_type_counts = array_count_values(array_map('mb_strtolower', array_column($data['items'], 'loai_linhkien')));
            foreach ($preferred_types as $t) {
               if (!empty($all_type_counts[$t])) {
                  $qty_guess = (int) $all_type_counts[$t];
                  break;
               }
            }
         }
         $config_qtys[$k] = $qty_guess > 0 ? $qty_guess : 1;
      }
      // 2. Lọc ra các linh kiện thuộc cấu hình đang yêu cầu filter và row 
      $db_components_all = [];
      $config_display_name = '';
      foreach ($all_items as $item) {
         $c_names = explode(',', $item['ten_cauhinh']);
         foreach ($c_names as $cn) {
            $lbl = trim($cn) ?: 'Cấu hình chung';
            $key = mb_strtolower($lbl, 'UTF-8');
            if ($key === $config_key_req) {
               $db_components_all[] = $item;
               $config_display_name = $lbl;
               break;
            }
         }
      }
      $grouped_by_type = [];
      foreach ($db_components_all as $comp) {
         $grouped_by_type[strtolower(trim($comp['loai_linhkien']))][] = $comp;
      }

      // 3. Phân bổ linh kiện cho máy hiện tại (giống kho-hang.php)
      $db_components = [];
      foreach ($grouped_by_type as $type => $all_list_for_type) {
         $subgroups = [];
         foreach ($all_list_for_type as $item) {
            $cfg_parts = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $item['ten_cauhinh']));
            sort($cfg_parts);
            // Tách thêm theo tên linh kiện để RAM không bị trộn model (aaaaaaaa/csoosa)
            $cfg_key_set = implode(',', $cfg_parts) . '|' . mb_strtolower(trim((string) $item['ten_linhkien']), 'UTF-8');
            $subgroups[$cfg_key_set][] = $item;
         }

         foreach ($subgroups as $cfg_key_set => $sublist) {
            // Lấy các item thực sự thuộc về Cấu hình này 
            $my_pool = [];
            foreach ($sublist as $item) {
               $pool_configs = array_map('trim', explode(',', $item['ten_cauhinh']));
               sort($pool_configs);
               $owner_idx = strlen($item['ten_cauhinh']) - strlen(rtrim($item['ten_cauhinh']));
               $owner_name = $pool_configs[$owner_idx] ?? '';

               if (mb_strtolower($owner_name, 'UTF-8') === $config_key_req) {
                  $my_pool[] = $item;
               }
            }

            if (!empty($my_pool)) {
               // Chia số linh kiện trong pool này cho số máy của riêng cấu hình này
               $qty = $config_qtys[$config_key_req] ?? 1;

               $machine_items = [];
               for ($m = 1; $m <= $qty; $m++) {
                  $machine_items[$m] = [];
               }
               $temp_unassigned = [];
               foreach ($my_pool as $it) {
                  $assigned_config = mb_strtolower(trim($it['linhkien_chon'] ?? ''), 'UTF-8');
                  $assigned_machine = (int)($it['so_may'] ?? 0);
                  
                  $matched = false;
                  for ($m = 1; $m <= $qty; $m++) {
                     $target_config_only = mb_strtolower($config_display_name, 'UTF-8');
                     $target_combined = mb_strtolower($config_display_name . ' | Máy ' . $m, 'UTF-8');
                     
                     if ($assigned_config === $target_combined || ($assigned_config === $target_config_only && $assigned_machine === $m)) {
                        $machine_items[$m][] = $it;
                        $matched = true;
                        break;
                     }
                  }
                  if (!$matched) {
                     $temp_unassigned[] = $it;
                  }
               }

               $total_c_in_pool = count($my_pool);
               $base = floor($total_c_in_pool / $qty);
               $rem = $total_c_in_pool % $qty;
               for ($m = 1; $m <= $qty; $m++) {
                  $current_count = $base + ($m <= $rem ? 1 : 0);
                  while (count($machine_items[$m]) < $current_count && !empty($temp_unassigned)) {
                     $machine_items[$m][] = array_shift($temp_unassigned);
                  }
               }

               $my_items = $machine_items[$m_idx] ?? [];

               foreach ($my_items as $my_item) {
                  $db_components[] = $my_item;
               }
            }
         }
      }
      // 
      // 4. Sắp xếp linh kiện theo thứ tự yêu cầu: CPU -> MAIN -> RAM -> SSD -> VGA -> PSU -> WIN
      usort($db_components, function ($a, $b) {
         $order = [
            'cpu' => 1,
            'main' => 2,
            'mainboard' => 2,
            'ram' => 3,
            'ssd' => 4,
            'vga' => 5,
            'psu' => 6,
            'nguon' => 6,
            'case' => 7,
            'vo may' => 7,
         ];
         $pA = $order[strtolower(trim($a['loai_linhkien']))] ?? 99;
         $pB = $order[strtolower(trim($b['loai_linhkien']))] ?? 99;
         return $pA <=> $pB;
      });

      $components = [];
      foreach ($db_components as $comp) {
         $label = '';
         $icon = 'fa-microchip';
         switch (strtolower($comp['loai_linhkien'])) {
            case 'cpu':
               $label = 'BỘ VI XỬ LÝ (CPU)';
               $icon = 'fa-microchip';
               break;
            case 'main':
            case 'mainboard':
               $label = 'BO MẠCH CHỦ (MAINBOARD)';
               $icon = 'fa-house-laptop';
               break;
            case 'ram':
               $label = 'BỘ NHỚ (RAM)';
               $icon = 'fas fa-memory';
               break;
            case 'ssd':
               $label = 'Ổ CỨNG (SSD)';
               $icon = 'fa-hard-drive';
               break;
            case 'psu':
            case 'nguon':
               $label = 'NGUỒN (PSU)';
               $icon = 'fa-bolt';
               break;
            case 'case':
            case 'vo may':
               $label = 'VỎ MÁY (CASE)';
               $icon = 'fa-computer';
               break;
            case 'vga':
               $label = 'VGA';
               $icon = 'fa-gem';
               break;
            default:
               $label = strtoupper($comp['loai_linhkien']);
               $icon = 'fa fa-windows';
               break;
         }
         // Pre-fill serial nếu liện kiện này đã được gán cho đúng cấu hình và số máy hiện tại
         $target_config_machine_name = $config_display_name . ' | Máy ' . $m_idx;
         $linhkien_chon_val = trim($comp['linhkien_chon'] ?? '');
         $so_may_val = isset($comp['so_may']) ? (int)$comp['so_may'] : 0;

         // Pre-fill nếu linhkien_chon khớp HOẶC so_may khớp (để sửa lỗi dữ liệu cũ bị thiếu Máy X trong column linhkien_chon)
         $is_match = (mb_strtolower($linhkien_chon_val, 'UTF-8') === mb_strtolower($target_config_machine_name, 'UTF-8'))
            || ($so_may_val === $m_idx && !empty($linhkien_chon_val));

         $prefilled_serial = ($is_match && !empty($comp['so_serial'])) ? $comp['so_serial'] : '';
         $components[] = [
            'id_ct' => isset($comp['id_ct']) ? (int) $comp['id_ct'] : 0,
            'loai_linh_kien' => $comp['loai_linhkien'],
            'ten_linh_kien' => $comp['ten_linhkien'],
            'so_serial' => $comp['so_serial'] ?? '',
            'linhkien_chon' => $linhkien_chon_val,
            'prefilled_serial' => $prefilled_serial,
            'label' => $label,
            'icon' => $icon
         ];
      }

      // Kiểm tra trạng thái hoàn thiện (đã nhập đủ hết serial chưa)
      $total_needed = count($components);
      $total_filled = count(array_filter($components, function ($c) {
         return !empty($c['prefilled_serial']);
      }));
      $is_completed = ($total_needed > 0 && $total_needed === $total_filled);
   } catch (PDOException $e) {
   }
}
?>

<link rel="stylesheet" href="./css/kho-import-serial.css">

<main class="main-content-scan">
   <!-- Breadcrumbs -->
   <nav class="breadcrumb">
      <a href="don-hang.php">Đơn hàng</a>
      <span><i class="fa-solid fa-chevron-right"></i></span>
      <a href="kho-hang.php?id=<?php echo $order_id; ?>">Kho hàng</a>
      <span><i class="fa-solid fa-chevron-right"></i></span>
      <span class="active">Quét mã Serial</span>
   </nav>

   <?php if ($order): ?>
      <div class="page-header">
         <h1 class="page-title">Nhập Serial Linh Kiện</h1>
         <div class="header-info-wrap">
            <div class="order-badge"><?php echo htmlspecialchars($config_display_name); ?></div>
            <p class="page-subtitle">Vui lòng quét hoặc nhập mã serial cho từng linh kiện dưới đây để tiến hành lắp ráp
            </p>
         </div>
      </div>

      <!-- Scanner API Status Bar -->
      <div id="scanner-status-bar" class="scanner-status-bar unauthenticated">
         <div class="status-info">
            <i class="fa-solid fa-server status-icon"></i>
            <span class="status-text">Đang kiểm tra kết nối máy chủ quét...</span>
         </div>
         <div class="status-actions">
            <!-- Nút Kết nối dịch vụ quét -->
            <button id="btn-scanner-login" class="btn-scanner-action" style="display:none;">
               <i class="fa-solid fa-plug"></i> Kết nối
            </button>
            <button id="btn-scanner-register" class="btn-scanner-action" style="display:none; color: #10b981;">
               <i class="fa-solid fa-user-plus"></i> Đăng ký
            </button>
            <!-- Nút Đăng xuất dịch vụ quét -->
            <button id="btn-scanner-logout" class="btn-scanner-action logout" style="display:none;">
               <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
            </button>
         </div>
      </div>


      <!-- Scanner Login Modal (Sync with login/login.html) -->
      <div id="scanner-login-modal" class="scanner-modal" style="display:none;">
         <div class="modal-content">
            <div class="logo"
               style="font-family: 'Space Mono', monospace; font-size: 20px; font-weight: 700; text-align: center; margin-bottom: 8px; color: #7c3aed;">
               QR · BARCODE SCANNER</div>
            <div class="subtitle" style="text-align: center; color: #6b6b80; font-size: 13px; margin-bottom: 24px;">Đăng
               nhập để sử dụng</div>

            <div class="field" style="margin-bottom: 16px;">
               <label
                  style="display: block; font-size: 12px; font-weight: 600; color: #6b6b80; margin-bottom: 6px; text-transform: uppercase;">Username</label>
               <input type="text" id="l-username" placeholder="Nhập username"
                  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div class="field" style="margin-bottom: 16px;">
               <label
                  style="display: block; font-size: 12px; font-weight: 600; color: #6b6b80; margin-bottom: 6px; text-transform: uppercase;">Mật
                  khẩu</label>
               <input type="password" id="l-password" placeholder="Nhập mật khẩu"
                  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div id="loginMsg" class="msg"
               style="text-align: center; font-size: 13px; padding: 10px; border-radius: 8px; display: none; margin-bottom: 16px;">
            </div>

            <div class="modal-footer" style="display: flex; gap: 10px;">
               <button id="btn-close-scanner-modal" class="btn-cancel"
                  style="flex: 1; padding: 12px; border-radius: 10px; background: #f1f5f9; border: none; cursor: pointer;">Hủy</button>
               <button id="btn-do-scanner-login" class="btn-primary"
                  style="flex: 2; padding: 12px; border-radius: 10px; background: #7c3aed; color: #fff; border: none; cursor: pointer; font-weight: 700;">Đăng
                  nhập</button>
            </div>
         </div>
      </div>

      <!-- Scanner Register Modal (Sync with login/login.html) -->
      <div id="scanner-register-modal" class="scanner-modal" style="display:none;">
         <div class="modal-content">
            <div class="logo"
               style="font-family: 'Space Mono', monospace; font-size: 20px; font-weight: 700; text-align: center; margin-bottom: 8px; color: #10b981;">
               TẠO TÀI KHOẢN QUÉT</div>
            <div class="subtitle" style="text-align: center; color: #6b6b80; font-size: 13px; margin-bottom: 24px;">Đăng ký
               để sử dụng dịch vụ</div>

            <div class="field" style="margin-bottom: 16px;">
               <label
                  style="display: block; font-size: 12px; font-weight: 600; color: #6b6b80; margin-bottom: 6px; text-transform: uppercase;">Username</label>
               <input type="text" id="r-username" placeholder="Chọn username"
                  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div class="field" style="margin-bottom: 16px;">
               <label
                  style="display: block; font-size: 12px; font-weight: 600; color: #6b6b80; margin-bottom: 6px; text-transform: uppercase;">Email</label>
               <input type="email" id="r-email" placeholder="rosa@example.com"
                  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div class="field" style="margin-bottom: 16px;">
               <label
                  style="display: block; font-size: 12px; font-weight: 600; color: #6b6b80; margin-bottom: 6px; text-transform: uppercase;">Mật
                  khẩu</label>
               <input type="password" id="r-password" placeholder="Tối thiểu 6 ký tự"
                  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div id="registerMsg" class="msg"
               style="text-align: center; font-size: 13px; padding: 10px; border-radius: 8px; display: none; margin-bottom: 16px;">
            </div>

            <div class="modal-footer" style="display: flex; gap: 10px;">
               <button id="btn-close-register-modal" class="btn-cancel"
                  style="flex: 1; padding: 12px; border-radius: 10px; background: #f1f5f9; border: none; cursor: pointer;">Hủy</button>
               <button id="btn-do-scanner-register" class="btn-primary"
                  style="flex: 2; padding: 12px; border-radius: 10px; background: #10b981; color: #fff; border: none; cursor: pointer; font-weight: 700;">Tạo
                  tài khoản</button>
            </div>
         </div>
      </div>


      <!-- Top Info Cards -->
      <div class="info-cards-grid">
         <div class="info-card">
            <div class="card-icon">
               <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div class="card-meta">
               <span class="card-label">MÃ ĐƠN HÀNG</span>
               <span class="card-value"><?php echo htmlspecialchars($order_id); ?></span>
            </div>
         </div>
         <div class="info-card">
            <div class="card-icon purple">
               <i class="fa-solid fa-desktop"></i>
            </div>
            <div class="card-meta">
               <span class="card-label">CẤU HÌNH HIỆN TẠI</span>
               <span class="card-value"><?php echo htmlspecialchars($config_display_name); ?></span>
            </div>
         </div>
      </div>

      <div class="component-list-card">
         <div class="list-header">
            <div class="list-title">Danh sách linh kiện cần nhập</div>
            <div class="scan-note">* Sử dụng máy quét để nhập nhanh</div>
         </div>
         <!-- <div class="clear-input-btn" title="Xoá để nhập lại">
            <i class="fa-solid fa-circle-xmark"></i>
         </div> -->

         <?php foreach ($components as $comp): ?>
            <div class="component-item">
               <div class="comp-info-side">
                  <div class="comp-icon-box">
                     <i class="fa-solid <?php echo $comp['icon']; ?>"></i>
                  </div>

                  <div class="comp-text">
                     <span class="comp-category"><?php echo $comp['label']; ?></span>
                     <span class="comp-name"><?php echo htmlspecialchars($comp['ten_linh_kien']); ?></span>
                  </div>
               </div>

               <div class="comp-input-side">
                  <input type="text" class="scan-input" data-id-ct="<?php echo (int) ($comp['id_ct'] ?? 0); ?>"
                     data-name="<?php echo htmlspecialchars($comp['ten_linh_kien']); ?>"
                     data-loai="<?php echo htmlspecialchars($comp['loai_linh_kien']); ?>"
                     data-choice="<?php echo htmlspecialchars($target_config_machine_name); ?>"
                     data-old-serial="<?php echo htmlspecialchars($comp['prefilled_serial']); ?>"
                     placeholder="Serial của <?php echo $comp['label']; ?>..."
                     value="<?php echo htmlspecialchars($comp['prefilled_serial']); ?>" autocomplete="off">
                  <div class="scan-icon-inside">
                     <i class="fa-solid fa-barcode" style="color:#1152D4; vertical-align: 10px;"></i>
                  </div>
                  <!-- <div class="clear-input-btn" title="Xoá để nhập lại">
                     <i class="fa-solid fa-circle-xmark"></i>
                  </div> -->
                  <div class="scan-status-icon"></div>
                  <div class="scan-error-msg"></div>
               </div>
            </div>
         <?php
         endforeach; ?>
      </div>
      <div class="footer-actions">
         <button type="button" class="btn-back" onclick="window.location.href='kho-hang.php?id=<?php echo $order_id; ?>'">
            <i class="fa-solid fa-arrow-left"></i> Quay lại
         </button>
         <div class="footer-confirm-wrap">
            <?php if ($is_completed): ?>
               <span class="confirm-note success"><i class="fa-solid fa-circle-check"></i> Đã nhập đầy đủ linh kiện</span>
               <button type="button" class="btn-confirm" id="btnConfirm">Lưu thay đổi <i
                     class="fa-solid fa-paper-plane"></i></button>
            <?php else: ?>
               <span class="confirm-note">Kiểm tra kỹ trước khi xác nhận</span>
               <button type="button" class="btn-confirm" id="btnConfirm">Xác nhận Lưu <i
                     class="fa-solid fa-paper-plane"></i></button>
            <?php endif; ?>
         </div>
      </div>
   <?php
   endif; ?>
   <!-- Global Hidden File Input for Scanning -->
   <input type="file" id="scan-file-input" accept="image/*" capture="environment" style="display: none;">

   <!-- Scanner UI Modal (Refined design like login/index.html) -->
   <div id="scanner-ui-modal" class="scanner-modal" style="display:none;">
      <div class="scanner-ui-container">
         <div class="scanner-ui-header">
            <div class="title-wrap">
               <i class="fa-solid fa-qrcode"></i>
               <h3>QUÉT MÃ SERIAL</h3>
            </div>
            <button type="button" class="btn-close-scanner"><i class="fa-solid fa-xmark"></i></button>
         </div>

         <div class="scanner-ui-body">
            <div class="scanner-preview-area" id="modalPreviewArea">
               <div class="scanner-placeholder" id="modalPlaceholder">
                  <div class="icon-circle">
                     <i class="fa-solid fa-camera"></i>
                  </div>
                  <span>Nhấn để chụp hoặc chọn ảnh</span>
               </div>
               <img id="modal-preview-img" alt="Preview" style="display:none;">
               <div class="scanner-corners" id="modalCorners">
                  <div class="scanner-corner tl"></div>
                  <div class="scanner-corner tr"></div>
                  <div class="scanner-corner bl"></div>
                  <div class="scanner-corner br"></div>
               </div>
            </div>

            <div class="scanner-status-text" id="modalStatus">Chưa chọn ảnh nào</div>

            <div class="scanner-loading-overlay" id="modalLoading" style="display:none;">
               <div class="spinner"></div>
               <span>Đang phân tích dữ liệu...</span>
            </div>

            <div class="modal-btn-grid">
               <button type="button" class="btn-mod-camera" id="btnModalCapture">
                  <i class="fa-solid fa-images"></i>Thử lại
               </button>
               <button type="button" class="btn-mod-scan" id="btnModalScan" disabled>
                  <i class="fa-solid fa-user-gear"></i>Xử lý
               </button>
            </div>
            <button type="button" class="btn-mod-back btn-close-scanner"
               style="width: 100%; margin-top: 10px; padding: 12px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #94a3b8; font-weight: 700; cursor: pointer; transition: all 0.2s;">
               <i class="fa-solid fa-arrow-left"></i> QUAY LẠI
            </button>

            <div class="scanner-result-container" id="modalResultArea" style="display:none;">
               <!-- Scanned items will be listed here -->
            </div>
         </div>
      </div>
   </div>
</main>


<script>
   const currentOrderId = <?php echo json_encode($order_id); ?>;
   const currentConfigName = <?php echo json_encode($config_display_name . ' | Máy ' . $m_idx); ?>;
   const currentConfigKey = <?php echo json_encode($config_key_req); ?>;
</script>
<!-- Đặt script ở cuối body -->
<script src="./js/quet-ma.js"></script>