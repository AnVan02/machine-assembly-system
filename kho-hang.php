<?php
require "config.php";
require "thanh-dieu-huong.php";

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
$order = null;
$grouped_configs = []; // Mãng

if ($pdo) {
   try {
      // Nếu không có id lấy đơn hàng mới nhất 
      $stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($order) {
         // Lấy thôg tin đơn hàng
         // Giữ đúng thứ tự insert để tránh trộn RAM.
         // Nếu DB chưa có cột id_ct thì fallback để không bị trắng trang (exception bị nuốt).
         $rows = [];
         try {
            $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
            $stmt->execute([$order_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
         } catch (PDOException $e) {
            $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY ten_donhang ASC");
            $stmt->execute([$order_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
         }

         foreach ($rows as $row) {
            // Tách cấu hình nếu có dấu phẩy 
            $c_names = explode(',', $row['ten_cauhinh']);
            foreach ($c_names as $cn) {
               $lbl = trim($cn) ?: 'Cấu hình chung';

               // Gom nhóm KHÔNG phân biệt chữ hoa chữ thường để tránh bị tách bảng lẻ
               $key = mb_strtolower($lbl, 'UTF-8');
               if (!isset($grouped_configs[$key])) {
                  $grouped_configs[$key] = ['name' => $lbl, 'items' => []];
               }
               $grouped_configs[$key]['items'][] = $row;
            }
         }
      }
   } catch (PDOException $e) {
   }
}
?>

<link rel="stylesheet" href="./css/kho-hang.css">
<main class="main-content-order">
   <!-- thanh điều hướng -->
   <nav class="breadcrumb-nav">
      <a href="dashboard-ky-thuat.php">Trang chủ</a>
      <span class="bc-sep">›</span>
      <a href="dashboard-ke-toan.php">Kho hàng</a>
      <span class="bc-sep">›</span>
      <span class="bc-active">NHẬP SERIAL</span>
   </nav>

   <?php if ($order): ?>
      <div class="order-banner">
         <div class="banner-overlay"></div>
         <div class="banner-content">
            <div class="banner-info">
               <h1 class="banner-title">Đơn hàng: <span><?php echo htmlspecialchars($order['ma_don_hang'] ?? ''); ?></span>
               </h1>
               <div class="banner-meta">
                  <div class="banner-meta-item">
                     <strong><?php echo htmlspecialchars($order['ten_khach_hang'] ?? ''); ?></strong>
                  </div>
                  <div class="banner-meta-item">
                     <strong><?php echo htmlspecialchars($order['so_luong_may'] ?? '0'); ?> máy</strong>
                  </div>
                  <div class="banner-meta-item">
                     <strong><?php echo date('d/m/Y', strtotime($order['ngay_tao'])); ?></strong>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="config-section">
         <div class="config-section-title">
            <i class="fa-solid fa-shop" style="color:#1152D4;"></i>
            <span>Nhóm Cấu Hình </span>
         </div>
         <?php
         // Tính số lượng máy cho từng cấu hình:
         // Chỉ đếm linh kiện RIÊNG của cấu hình đó (ten_cauhinh không chứa dấu phẩy = không gộp).
         // CPU/MAIN không bao giờ bị gộp giữa các cấu hình → đây là nguồn tin cậy nhất.

         $config_qtys = [];
         foreach ($grouped_configs as $k => $data) {
            // Lọc chỉ lấy item thuộc riêng cấu hình này (ten_cauhinh không có dấu phẩy)
            $exclusive_items = array_filter($data['items'], function ($item) use ($k) {
               $parts = array_map(function ($p) {
                  return mb_strtolower(trim($p), 'UTF-8');
               }, explode(',', $item['ten_cauhinh']));
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

            // Nếu không tìm thấy từ exclusive → fallback dùng tất cả item
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

         foreach ($grouped_configs as $key => $data):
            $name = $data['name'];
            $qty = $config_qtys[$key];
            $components = $data['items'];
            $grouped_by_type = [];
            foreach ($components as $c) {
               if (!empty($c['ten_linhkien'])) {
                  $grouped_by_type[mb_strtolower(trim($c['loai_linhkien']), 'UTF-8')][] = $c;
               }
            }
         ?>
            <div class="config-card">
               <div class="config-card-header">
                  <div class="config-header-left">
                     <span class="config-label"> <?php echo htmlspecialchars($name); ?></span>
                     <span class="config-qty">Số lượng <?php echo $qty; ?></span>
                     <span class="config-header-lk">
                        <?php
                        $result = [];
                        foreach ($grouped_by_type as $type => $items) {
                           $names = [];
                           foreach ($items as $it) {
                              $parts = array_map('trim', explode(',', $it['ten_cauhinh']));
                           }
                        }
                        ?>
                     </span>
                  </div>

               </div>
               <table class="config-table">
                  <thead>
                     <tr>
                        <th>Máy số</th>
                        <th>Linh kiện lắp ráp</th>
                        <th class="th-action">Thao tác</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php for ($i = 1; $i <= $qty; $i++): ?>
                        <?php
                        $is_machine_finished = true;
                        $machine_component_count = 0;
                        $machine_filled_count = 0;
                        ?>
                        <tr>
                           <td class="td-may">Máy số <?php echo $i; ?></td>
                           <td class="td-linhkien">
                              <div class="component-list">
                                 <?php
                                 foreach ($grouped_by_type as $type => $all_list_for_type):
                                    // Phân nhóm linh kiện theo đúng tập hợp cấu hình mà nó thuộc về
                                    $subgroups = [];
                                    foreach ($all_list_for_type as $item) {
                                       $cfg_parts = array_map(function ($p) {
                                          return mb_strtolower(trim($p), 'UTF-8');
                                       }, explode(',', $item['ten_cauhinh']));
                                       sort($cfg_parts);
                                       // Tách thêm theo tên linh kiện để RAM không bị trộn model (cauhinh1/cauhinh2)
                                       $cfg_key_set = implode(',', $cfg_parts) . '|' . mb_strtolower(trim((string) $item['ten_linhkien']), 'UTF-8');
                                       $subgroups[$cfg_key_set][] = $item;
                                    }
                                    foreach ($subgroups as $cfg_key_set => $sublist):
                                       // Lấy các item thực sự thuộc về Cấu hình này 
                                       // Sử dụng mẹo: tìm item mang tên cấu hình hiện tại trong chuỗi ten_cauhinh
                                       $my_pool = [];
                                       foreach ($sublist as $item) {
                                          // Lấy danh sách cấu hình của item này và SẮP XẾP giống như khi lưu
                                          $pool_configs = array_map('trim', explode(',', $item['ten_cauhinh']));
                                          sort($pool_configs);

                                          // Mẹo trailing space: đếm số khoảng trắng ở cuối chuỗi để tìm index của owner
                                          $owner_idx = strlen($item['ten_cauhinh']) - strlen(rtrim($item['ten_cauhinh']));
                                          $owner_name = $pool_configs[$owner_idx] ?? '';

                                          if (mb_strtolower($owner_name, 'UTF-8') === $key) {
                                             $my_pool[] = $item;
                                          }
                                       }

                                       if (!empty($my_pool)) {
                                          $machine_items = [];
                                          for ($m = 1; $m <= $qty; $m++) {
                                             $machine_items[$m] = [];
                                          }
                                          $temp_unassigned = [];
                                          foreach ($my_pool as $it) {
                                             $assigned_to = mb_strtolower(trim($it['linhkien_chon'] ?? ''), 'UTF-8');
                                             $matched = false;
                                             for ($m = 1; $m <= $qty; $m++) {
                                                $target_m = mb_strtolower($name . ' | Máy ' . $m, 'UTF-8');
                                                if ($assigned_to === $target_m) {
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

                                          $my_items = $machine_items[$i] ?? [];

                                          foreach ($my_items as $my_item):
                                             $machine_component_count++;
                                             // Chi coi là linh kiện Hoàn Thành nếu đã nhập Serial VÀ đã được gán đích danh cho máy này (linhkien_chon không NULL)
                                             $has_serial = !empty(trim((string) ($my_item['so_serial'] ?? '')));
                                             $is_assigned = !empty(trim((string) ($my_item['linhkien_chon'] ?? '')));


                                             if ($has_serial && $is_assigned) {
                                                $machine_filled_count++;
                                             } else {
                                                $is_machine_finished = false;
                                             }
                                 ?>
                                             <div class="component-item">
                                                <strong><?php echo strtoupper($my_item['loai_linhkien']); ?>:</strong>
                                                <?php echo htmlspecialchars($my_item['ten_linhkien']); ?>
                                                <?php if ($has_serial): ?>
                                                   <span class="serial-status <?php echo $is_assigned ? 'assigned' : 'unassigned'; ?>">
                                                      <?php echo $is_assigned ? '<i class="fa-solid fa-check" style="color:green;"></i>' : ''; ?>
                                                   </span>
                                                <?php else: ?>
                                                   <span class="serial-status missing">Chưa có Serial</span>
                                                <?php endif; ?>
                                             </div>
                                 <?php
                                          endforeach;
                                       }
                                    endforeach;
                                 endforeach; ?>
                              </div>
                              <?php
                              // Nếu máy không có linh kiện nào (do dữ liệu/chia nhóm), không được coi là "Hoàn Thành"
                              if ($machine_component_count === 0)
                                 $is_machine_finished = false;
                              $is_machine_in_progress = ($machine_component_count > 0 && $machine_filled_count > 0 && !$is_machine_finished);
                              ?>
                           </td>
                           <td class="td-action">
                              <a href="kho-import-serial.php?id=<?php echo $order_id; ?>&config=<?php echo urlencode($name); ?>&m=<?php echo $i; ?>"
                                 class="btn-nhap-serial-link <?php echo $is_machine_finished ? 'finished' : ($is_machine_in_progress ? 'checking' : ''); ?>">
                                 <span>
                                    <?php if ($is_machine_finished): ?>
                                       <span style="display:block;">Hoàn Tất</span><br>
                                       <span style="display:block;"><?= $machine_filled_count . '/' . $machine_component_count ?></span>
                                    <?php else: ?>
                                       <?= $is_machine_in_progress ? 'Kiểm tra' : 'Nhập Serial' ?>
                                    <?php endif; ?>
                                 </span>
                              </a>
                           </td>
                        </tr>
                     <?php endfor; ?>
                  </tbody>
               </table>
            </div>
         <?php endforeach; ?>
      </div>
   <?php else: ?>
      <div style="padding:16px; margin-top:12px; background:#fff3cd; border:1px solid #ffe69c; border-radius:10px;">
         Không tìm thấy đơn hàng hoặc dữ liệu chi tiết. Vui lòng kiểm tra tham số `id` trên URL.
      </div>
   <?php endif; ?>
</main>