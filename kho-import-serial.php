<?php
ob_start(); // Chống lỗi "headers already sent"
require "config.php";
require_once 'phan-quyen.php';

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
$l_cfg_req = isset($_GET['config']) ? mb_strtolower(trim($_GET['config']), 'UTF-8') : '';
$m_idx_req = isset($_GET['m']) ? (int) $_GET['m'] : 1;
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
   header("Location: dang-nhap.php");
   echo '<script>window.location.href="dang-nhap.php";</script>';
   exit;
}

if ($pdo) {
   // --- LOGIC PHÒNG CHỐNG COPY URL TRỰC TIẾP ---
   $m_key = "{$order_id}_{$m_idx_req}_{$l_cfg_req}";
   $allow_entry = false;

   if (isset($_SESSION['ENTRY_TOKEN']) && $_SESSION['ENTRY_TOKEN'] === $m_key) {
      $allow_entry = true;
      $_SESSION['ENTRY_TOKEN_VAL_FOUND'] = true; // Cắm cờ để JS nhận diện
      unset($_SESSION['ENTRY_TOKEN']);
      $_SESSION['LAST_MACHINE_ENTERED'] = $m_key;
   } elseif (isset($_SESSION['LAST_MACHINE_ENTERED']) && $_SESSION['LAST_MACHINE_ENTERED'] === $m_key) {
      $allow_entry = true;
      unset($_SESSION['ENTRY_TOKEN_VAL_FOUND']); // Refresh thì không còn là vào bằng token nữa
   }

   if (!$allow_entry) {
      header("Location: kho-hang.php?id=" . $order_id);
      echo '<script>window.location.href="kho-hang.php?id=' . $order_id . '";</script>';
      exit;
   }
   // ---------------------------------------------

   // 1. Kiểm tra xem người dùng này có đang làm dở máy nào KHÁC không
   $stmt_busy = $pdo->prepare("SELECT id_donhang, so_may, config_name FROM trang_thai_lap_may 
                                 WHERE user_id = ? AND (id_donhang != ? OR so_may != ? OR config_name != ?)
                                 LIMIT 1");
   $stmt_busy->execute([$user_id, $order_id, $m_idx_req, $l_cfg_req]);
   $busy_machine = $stmt_busy->fetch(PDO::FETCH_ASSOC);

   if ($busy_machine) {
      header("Location: kho-hang.php?id=" . $busy_machine['id_donhang']);
      exit;
   }

   // 2. Kiểm tra xem máy này có đang bị người khác khóa không (Active lock)
   $stmt_check = $pdo->prepare("SELECT user_id FROM trang_thai_lap_may WHERE id_donhang = ? AND so_may = ? AND config_name = ? LIMIT 1");
   $stmt_check->execute([$order_id, $m_idx_req, $l_cfg_req]);
   $current_lock = $stmt_check->fetch(PDO::FETCH_ASSOC);

   if ($current_lock && $current_lock['user_id'] != $user_id) {
      // Nếu bị người khác khóa, không cho vào
      echo "<script>alert('Máy này đang được người khác xử lý!'); window.location.href='kho-hang.php?id=$order_id';</script>";
      exit;
   }

   // 2b. Kiểm tra xem máy này đã được gán cố định cho người khác trong chitiet_donhang chưa
   $stmt_assigned = $pdo->prepare("SELECT user_id FROM chitiet_donhang WHERE id_donhang = ? AND linhkien_chon = ? AND so_may = ? AND user_id IS NOT NULL AND user_id != ? LIMIT 1");
   $stmt_assigned->execute([$order_id, $l_cfg_req, $m_idx_req, $user_id]);
   if ($stmt_assigned->fetch()) {
      echo "<script>alert('Máy này đã được gán cho người khác!'); window.location.href='kho-hang.php?id=$order_id';</script>";
      exit;
   }
   // 3. Xử lý logic Fresh Entry (Xóa dữ liệu cũ nếu là phiên mới)
   $is_fresh = false;
   $m_key = "{$order_id}_{$m_idx_req}_{$l_cfg_req}";
   if (isset($_SESSION['FRESH_LOCK_' . $m_key])) {
      $is_fresh = true;
      unset($_SESSION['FRESH_LOCK_' . $m_key]);
   }
   // 4. Ghi nhận/Cập nhật khóa máy cho user hiện tại (Trong bảng trạng thái)
   $stmt_lock = $pdo->prepare("INSERT INTO trang_thai_lap_may (id_donhang, so_may, config_name, user_id) 
                                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = ?, last_active = CURRENT_TIMESTAMP");
   $stmt_lock->execute([$order_id, $m_idx_req, $l_cfg_req, $user_id, $user_id]);
}

require "thanh-dieu-huong.php";

$order = null;
$comps = [];
$dNameMaster = $_GET['config'] ?? '';

if ($pdo) {
   try {
      $stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($order) {
         $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
         $stmt->execute([$order_id]);
         $all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

         // BƯỚC 1: XÁC ĐỊNH MÁY
         $config_qtys = [];
         $temp_all_configs = [];
         foreach ($all_rows as $row) {
            $parts = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $row['ten_cauhinh']));
            foreach ($parts as $p) {
               if ($p !== '')
                  $temp_all_configs[$p][] = $row;
            }
         }
         foreach ($temp_all_configs as $k => $items) {
            $qty = 0;
            $exclusive = array_filter($items, function ($item) use ($k) {
               $ps = array_map(function ($p) {
                  return mb_strtolower(trim($p), 'UTF-8');
               }, explode(',', $item['ten_cauhinh']));
               return count($ps) === 1 && $ps[0] === $k;
            });
            $t_counts = array_count_values(array_map('mb_strtolower', array_column(array_values($exclusive), 'loai_linhkien')));
            $pref = ['cpu', 'main', 'mainboard', 'vga', 'ssd', 'psu', 'fan'];
            foreach ($pref as $t) {
               if (!empty($t_counts[$t])) {
                  $qty = (int) $t_counts[$t];
                  break;
               }
            }
            if ($qty === 0) {
               $all_t_counts = array_count_values(array_map('mb_strtolower', array_column($items, 'loai_linhkien')));
               foreach ($pref as $t) {
                  if (!empty($all_t_counts[$t])) {
                     $qty = (int) $all_t_counts[$t];
                     break;
                  }
               }
            }
            $config_qtys[$k] = $qty > 0 ? $qty : 1;
         }

         // BƯỚC 2: PHÂN BỔ - ƯU TIÊN LINH KIỆN ĐÃ GÁN
         $my_db_rows = [];
         $global_type_groups = [];
         foreach ($all_rows as $item) {
            $config_normalized = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $item['ten_cauhinh']));
            // sort($config_normalized); // Tắt sắp xếp Alphabet để khớp với dữ liệu tạo đơn
            $type_key = mb_strtolower(trim($item['loai_linhkien']), 'UTF-8') . '|' . mb_strtolower(trim((string) $item['ten_linhkien']), 'UTF-8') . '|' . implode(',', $config_normalized);
            $global_type_groups[$type_key][] = $item;
         }

         foreach ($global_type_groups as $type_key => $sublist) {
            $pool_configs = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $sublist[0]['ten_cauhinh']));
            $pool_configs = array_values(array_unique(array_map('trim', $pool_configs)));
            // sort($pool_configs); // Tắt sắp xếp Alphabet để khớp với dữ liệu tạo đơn

            $total_m = 0;
            foreach ($pool_configs as $pc) {
               $total_m += ($config_qtys[$pc] ?? 0);
            }

            if ($total_m > 0) {
               // Phân loại
               $already_assigned = [];
               $free_pool = [];

               // NEW: Đếm số linh kiện thực tế thuộc về từng cấu hình trong pool này (Space Hack)
               $items_by_config = [];
               foreach ($sublist as $it) {
                  $t_owner = rtrim($it['ten_cauhinh'], ' ');
                  $s_owner = strlen($it['ten_cauhinh']) - strlen($t_owner);
                  $p_owner = array_map('trim', explode(',', $t_owner));
                  $owner_name = mb_strtolower($p_owner[$s_owner] ?? $p_owner[0], 'UTF-8');
                  $items_by_config[$owner_name] = ($items_by_config[$owner_name] ?? 0) + 1;

                  $c_chon = mb_strtolower(trim($it['linhkien_chon'] ?? ''), 'UTF-8');
                  $m_chon = (int) ($it['so_may'] ?? 0);
                  if (!empty($it['so_serial']) && $c_chon !== '' && $m_chon > 0) {
                     $already_assigned[$c_chon][$m_chon][] = $it;
                  } else {
                     $free_pool[] = $it;
                  }
               }

               $free_idx = 0;
               foreach ($pool_configs as $pc) {
                  $pc_qty = $config_qtys[$pc] ?? 0;
                  if ($pc_qty <= 0)
                     continue;

                  $pc_total_items = $items_by_config[$pc] ?? 0;
                  $pc_base = floor($pc_total_items / $pc_qty);
                  $pc_rem = $pc_total_items % $pc_qty;

                  for ($m = 1; $m <= $pc_qty; $m++) {
                     // Phân phối dư (remainder) cho các máy đầu tiên của cấu hình đó
                     $count_needed = $pc_base + ($m <= $pc_rem ? 1 : 0);

                     // Lấy linh kiện cho Slot này
                     $slot_items = [];
                     // 1. Ưu tiên đã gán
                     $mine = $already_assigned[$pc][$m] ?? [];
                     foreach ($mine as $it) {
                        $slot_items[] = $it;
                     }
                     // 2. Lấy thêm từ pool tự do
                     $still_needed = $count_needed - count($mine);
                     if ($still_needed > 0) {
                        $added = array_slice($free_pool, $free_idx, $still_needed);
                        foreach ($added as $it) {
                           $it['so_serial'] = ''; // CHẶN TỰ ĐỘNG HIỆN 
                           $slot_items[] = $it;
                        }
                        $free_idx += count($added);
                     }

                     // Nếu trúng cấu hình và máy đang yêu cầu thì nạp vào results
                     if ((string) $pc == (string) $l_cfg_req && (int) $m == (int) $m_idx_req) {
                        foreach ($slot_items as $it) {
                           $my_db_rows[] = $it;
                        }
                     }
                  }
               }
            }
         }


         usort($my_db_rows, function ($a, $b) {
            $o = ['cpu' => 1, 'main' => 2, 'ram' => 3, 'ssd' => 4, 'vga' => 5, 'psu' => 6, 'fan' => 7];
            $pA = $o[strtolower(trim($a['loai_linhkien']))] ?? 99;
            $pB = $o[strtolower(trim($b['loai_linhkien']))] ?? 99;
            return ($pA !== $pB) ? ($pA <=> $pB) : ($a['id_ct'] <=> $b['id_ct']);
         });


         // --------------------------------------------------------------------------

         foreach ($my_db_rows as $mi) {
            $lbl = strtoupper($mi['loai_linhkien']);
            $has_s = !empty($mi['so_serial']);
            $is_verified = !empty($mi['user_id_save']); // Chỉ những gì đã được lưu bởi kỹ thuật viên mới coi là "đã nhập"
            $is_m = ($has_s && $is_verified && (string) mb_strtolower($mi['linhkien_chon'] ?? '', 'UTF-8') == (string) $l_cfg_req && (int) $mi['so_may'] == (int) $m_idx_req);
            $comps[] = [
               'id_ct' => (int) $mi['id_ct'],
               'loai' => $mi['loai_linhkien'],
               'ten' => $mi['ten_linhkien'],
               'label' => $lbl,
               'icon' => 'fa-microchip',
               'prefilled' => ($is_m ? $mi['so_serial'] : '')
            ];
         }
      }
   } catch (PDOException $e) {
      echo "<div style='color:red; padding:20px; background:#fff;'>Lỗi hệ thống: " . $e->getMessage() . "</div>";
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
   <div class="page-header">
      <h1 class="page-title">Đơn hàng: <span><?php echo htmlspecialchars($dNameMaster); ?></span> | Máy
         <?php echo $m_idx_req; ?>
      </h1>
   </div>
   <div class="component-list-card">
      <div class="list-header">
         <h2 class="list-title">Danh sách linh kiện cần nhập</h2>
         <span class="scan-note">* Sử dụng máy quét để nhập nhanh</span>
      </div>
      <?php foreach ($comps as $c): ?>
         <div class="component-item">
            <?php
            $comp_fullname = $c['label'];
            $icon_html = '<i class="fa-solid fa-microchip"></i>';
            switch (strtoupper($c['loai'])) {
               case 'CPU':
                  $comp_fullname = 'BỘ VI XỬ LÝ (CPU)';
                  $icon_html = '<i class="fa-solid fa-microchip" style="color:#e74c3c;"></i>'; // đỏ
                  break;

               case 'MAIN':
                  $comp_fullname = 'BO MẠCH CHỦ (MAIN)';
                  $icon_html = '<i class="fa-solid fa-microchip" style="color:#8e44ad;"></i>'; // tím
                  break;

               case 'RAM':
                  $comp_fullname = 'BỘ NHỚ (RAM)';
                  $icon_html = '<i class="fa-solid fa-memory" style="color:#27ae60;"></i>'; // xanh lá
                  break;

               case 'SSD':
               case 'HDD':
                  $comp_fullname = 'Ổ CỨNG (' . strtoupper($c['loai']) . ')';
                  $icon_html = '<i class="fa-solid fa-hard-drive" style="color:#2980b9;"></i>'; // xanh dương
                  break;

               case 'VGA':
                  $comp_fullname = 'CARD ĐỒ HỌA (GPU)';
                  $icon_html = '<i class="fa-solid fa-gamepad" style="color:#f39c12;"></i>'; // cam
                  break;

               case 'FAN':
                  $comp_fullname = 'QUẠT TẢN NHIỆT (FAN)';
                  $icon_html = '<i class="fa-solid fa-fan" style="color:#7f8c8d;"></i>'; // xám
                  break;

               case 'PSU':
                  $comp_fullname = 'NGUỒN (PSU)';
                  $icon_html = '<i class="fa-solid fa-plug" style="color:#2c3e50;"></i>'; // xám đậm
                  break;

               case 'WIN':
                  $comp_fullname = 'PHẦN MỀM';
                  $icon_html = '<i class="fa-brands fa-windows" style="color:#00a8ff;"></i>'; // xanh Windows
                  break;
            }
            ?>
            <div class="comp-info-side">
               <div class="comp-icon-box">
                  <?php echo $icon_html; ?>
               </div>
               <div class="comp-text">
                  <span class="comp-category"><?php echo htmlspecialchars($comp_fullname); ?></span>
                  <span class="comp-name"><?php echo htmlspecialchars($c['ten']); ?></span>
               </div>
            </div>
            <div class="comp-input-side">
               <?php
               $placeholder_txt = 'Nhập số Serial ' . strtoupper($c['loai']) . '...';
               if (strtoupper($c['loai']) == 'MAIN')
                  $placeholder_txt = 'Nhập số Serial Bo mạch chủ...';
               if (strtoupper($c['loai']) == 'WIN')
                  $placeholder_txt = 'Nhập mã bản quyền phần mềm...';
               ?>
               <div class="input-wrapper">
                  <input type="text" class="scan-input <?php echo !empty($c['prefilled']) ? 'is-valid' : ''; ?>"
                     data-id-ct="<?php echo $c['id_ct']; ?>" data-name="<?php echo htmlspecialchars($c['ten']); ?>"
                     data-loai="<?php echo htmlspecialchars($c['loai']); ?>"
                     data-choice="<?php echo htmlspecialchars($dNameMaster); ?>"
                     placeholder="<?php echo $placeholder_txt; ?>"
                     value="<?php echo htmlspecialchars($c['prefilled']); ?>">
                  <div class="input-actions-group">
                     <div class="status-indicator <?php echo !empty($c['prefilled']) ? 'success' : ''; ?>">
                        <?php if (!empty($c['prefilled'])): ?>
                           <i class="fa-solid fa-circle-check"></i>
                        <?php endif; ?>
                     </div>
                     <div class="barcode-action-btn">
                        <i class="fa-solid fa-barcode scan-btn-icon" title="Nhấn để quét mã"></i>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      <?php endforeach; ?>
   </div>
   <div class="footer-actions">
      <button type="button" class="btn-back"
         onclick="window.location.href='kho-hang.php?id=<?php echo $order_id; ?>'">Quay lại</button>
      <button type="button" class="btn-confirm" id="btnConfirm" data-next-url="">
         Xác nhận Lưu
      </button>
   </div>
</main>
<div id="scan-toast" class="scan-toast"></div>

<!-- SCANNER UI MỚI -->
<input type="file" id="scan-file-input" accept="image/*" capture="environment" style="display: none;">

<!-- Scanner UI Modal (Refined design) -->
<div id="scanner-ui-modal" class="scanner-modal" style="display:none;">
   <div class="scanner-ui-container">
      <div class="scanner-ui-header">
         <div class="title-wrap">
            <i class="fa-solid fa-qrcode"></i>
            <h3>QUÉT MÃ SERIAL</h3>
         </div>
         <button type="button" class="btn-close-scanner-icon btn-close-scanner"><i
               class="fa-solid fa-xmark"></i></button>
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

            <div class="scanner-loading-overlay" id="modalLoading" style="display:none;">
               <div class="spinner"></div>
               <span id="loadingTextModal">Đang nạp ảnh...</span>
            </div>
         </div>

         <div class="scanner-status-text" id="modalStatus">Chưa chọn ảnh nào</div>

         <div class="modal-btn-grid">
            <button type="button" class="btn-mod-camera" id="btnModalCapture">
               <i class="fa-solid fa-images"></i>Chọn ảnh
            </button>
            <button type="button" class="btn-mod-scan" id="btnModalScan" disabled>
               <i class="fa-solid fa-microchip"></i>Xử lý
            </button>
         </div>
         <button type="button" class="btn-close-scanner"
            style="width: 100%; margin-top: 5px; padding: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s">
            <i class="fa-solid fa-arrow-left"></i>Quay lại
         </button>

         <div class="scanner-result-container" id="modalResultArea" style="display:none;">
         </div>
      </div>
   </div>
</div>

<script>
   const currentOrderId = <?php echo json_encode($order_id); ?>;
   const currentConfigPure = <?php echo json_encode($l_cfg_req); ?>;
   const currentMachineIdx = <?php echo (int) $m_idx_req; ?>;
   const isFreshEntry = <?php echo json_encode($is_fresh); ?>;
   const isAuthorizedByToken = <?php echo json_encode(isset($_SESSION['ENTRY_TOKEN_VAL_FOUND'])); ?>;

   // --- KIỂM TRA TAB (Chống Copy-Paste sang tab mới) ---
   (function () {
      const machineUID = `${currentOrderId}_${currentMachineIdx}_${currentConfigPure}`;
      const tabKey = "ACTIVE_TAB_FOR_" + machineUID;

      if (isAuthorizedByToken) {
         // Nếu vào bằng token (từ kho-hang.php), đánh dấu tab này là tab "chính chủ"
         sessionStorage.setItem(tabKey, "true");
      } else {
         // Nếu không có token (vào trực tiếp hoặc copy link)
         if (!sessionStorage.getItem(tabKey)) {
            // Nếu session của riêng tab này cũng không có -> Đẩy về trang chủ
            window.location.href = "kho-hang.php?id=" + currentOrderId;
         }
      }
   })();
</script>

<script src="./js/quet-ma.js?v=<?php echo time(); ?>"></script>