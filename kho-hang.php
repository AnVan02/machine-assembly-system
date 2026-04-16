<?php
require "config.php";
require "thanh-dieu-huong.php";

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
$order = null;
$grouped_configs = [];

if ($pdo) {
   try {
      $stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_donhang = ?");
      $stmt->execute([$order_id]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($order) {
         $stmt = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
         $stmt->execute([$order_id]);
         $all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

         // BƯỚC 1: XÁC ĐỊNH MÁY CHO TỪNG CẤU HÌNH
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
            $config_qtys[$k] = 1; // Mặc định 1 máy
            $exclusive = array_filter($items, function ($item) use ($k) {
               $ps = array_map(function ($p) {
                  return mb_strtolower(trim($p), 'UTF-8');
               }, explode(',', $item['ten_cauhinh']));
               return count($ps) === 1 && $ps[0] === $k;
            });
            $type_counts = array_count_values(array_map('mb_strtolower', array_column(array_values($exclusive), 'loai_linhkien')));
            $preferred = ['cpu', 'main', 'mainboard', 'vga', 'ssd', 'psu', 'win'];
            $qty = 0;
            foreach ($preferred as $t) {
               if (!empty($type_counts[$t])) {
                  $qty = (int) $type_counts[$t];
                  break;
               }
            }
            if ($qty === 0) {
               $all_type_counts = array_count_values(array_map('mb_strtolower', array_column($items, 'loai_linhkien')));
               foreach ($preferred as $t) {
                  if (!empty($all_type_counts[$t])) {
                     $qty = (int) $all_type_counts[$t];
                     break;
                  }
               }
            }
            $config_qtys[$k] = $qty > 0 ? $qty : 1;
         }

         // BƯỚC 2: PHÂN BỔ (POOLING) - CẢI TIẾN: ƯU TIÊN LINH KIỆN ĐÃ GÁN
         $global_type_groups = [];
         foreach ($all_rows as $item) {
            $config_normalized = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $item['ten_cauhinh']));
            // sort($config_normalized);
            $type_key = mb_strtolower(trim($item['loai_linhkien']), 'UTF-8') . '|' . mb_strtolower(trim((string) $item['ten_linhkien']), 'UTF-8') . '|' . implode(',', $config_normalized);
            $global_type_groups[$type_key][] = $item;
         }

         foreach ($global_type_groups as $type_key => $sublist) {
            $pool_configs = array_map(function ($p) {
               return mb_strtolower(trim($p), 'UTF-8');
            }, explode(',', $sublist[0]['ten_cauhinh']));
            $pool_configs = array_values(array_unique(array_map('trim', $pool_configs)));
            // sort($pool_configs);

            $total_m_sharing = 0;
            foreach ($pool_configs as $pc) {
               $total_m_sharing += ($config_qtys[$pc] ?? 0);
            }

            if ($total_m_sharing > 0) {
               // Tách biệt: Linh kiện đã gán và linh kiện tự do
               $already_assigned = []; // [config][machine][] = item
               $free_pool = [];
               foreach ($sublist as $it) {
                  $c_chon = mb_strtolower(trim($it['linhkien_chon'] ?? ''), 'UTF-8');
                  $m_chon = (int) ($it['so_may'] ?? 0);
                  if (!empty($it['so_serial']) && $c_chon !== '' && $m_chon > 0) {
                     $already_assigned[$c_chon][$m_chon][] = $it;
                  } else {
                     $free_pool[] = $it;
                  }
               }

               $total_items = count($sublist);
               $base = floor($total_items / $total_m_sharing);
               $rem = $total_items % $total_m_sharing;

               $free_idx = 0;
               foreach ($pool_configs as $pc) {
                  $pc_qty = $config_qtys[$pc] ?? 0;
                  if (!isset($grouped_configs[$pc])) {
                     $grouped_configs[$pc] = ['display_name' => $pc, 'machines' => []];
                  }

                  for ($m = 1; $m <= $pc_qty; $m++) {
                     $global_m_seq = 0;
                     foreach ($pool_configs as $pc2) {
                        if ($pc2 === $pc) {
                           $global_m_seq += $m;
                           break;
                        }
                        $global_m_seq += ($config_qtys[$pc2] ?? 0);
                     }
                     $count_needed = $base + ($global_m_seq > ($total_m_sharing - $rem) ? 1 : 0);

                     // 1. Lấy những cái đã gán cho đúng ô này
                     $mine = $already_assigned[$pc][$m] ?? [];
                     foreach ($mine as $it) {
                        $grouped_configs[$pc]['machines'][$m][] = $it;
                     }

                     // 2. Lấy thêm từ pool tự do cho đủ số lượng (nếu còn thiếu)
                     $still_needed = $count_needed - count($mine);
                     if ($still_needed > 0) {
                        $added = array_slice($free_pool, $free_idx, $still_needed);
                        foreach ($added as $it) {
                           $it['so_serial'] = ''; // CHẶN TỰ ĐỘNG HIỆN
                           $grouped_configs[$pc]['machines'][$m][] = $it;
                        }
                        $free_idx += count($added);
                     }
                  }
               }
            }
         }
      }
   } catch (PDOException $e) {
   }
}
?>

<link rel="stylesheet" href="./css/kho-hang.css">
<main class="main-content-order">
   <nav class="breadcrumb-nav">
      <a href="dashboard-ky-thuat.php">Trang chủ</a>
      <span class="bc-sep">›</span>
      <span class="bc-active">NHẬP SERIAL</span>
   </nav>

   <?php if ($order): ?>
      <div class="order-banner">
         <div class="banner-content">
            <h1 class="banner-title">Đơn hàng: <span><?php echo htmlspecialchars($order['ma_don_hang']); ?></span></h1>
         </div>
      </div>

      <div class="config-section">
         <?php foreach ($grouped_configs as $l_key => $data):
            $qty = $config_qtys[$l_key] ?? 1;
            $dName = $data['display_name'] ?? $l_key;
            ?>
            <div class="config-card">
               <div class="config-card-header">
                  <div class="config-header-left">
                     <span class="config-label"><?php echo htmlspecialchars($dName); ?></span>
                     <span class="config-qty">Số lượng <?php echo $qty; ?></span>
                  </div>
                  <?php
                  $machines_finished = 0;
                  for ($m_check = 1; $m_check <= $qty; $m_check++) {
                     $m_items_check = $data['machines'][$m_check] ?? [];
                     if (empty($m_items_check))
                        continue;

                     $m_is_finished = true;
                     foreach ($m_items_check as $mi) {
                        $has_s = !empty(trim((string) ($mi['so_serial'] ?? '')));
                        $assigned_cfg = mb_strtolower(trim($mi['linhkien_chon'] ?? ''), 'UTF-8');
                        if (!$has_s || (string) $assigned_cfg !== (string) $l_key || (int) $mi['so_may'] !== (int) $m_check) {
                           $m_is_finished = false;
                           break;
                        }
                     }
                     if ($m_is_finished)
                        $machines_finished++;
                  }
                  ?>
                  <div class="config-header-right">
                     <?php if ($machines_finished === $qty && $qty > 0): ?>
                        <span class="config-qty"
                           style="background:#10B981; color:#fff; box-shadow: 0 4px 12px rgba(16,185,129,0.4);">
                           <i class="fa-solid fa-circle-check"></i> Đã nhập <?php echo $machines_finished; ?>/<?php echo $qty; ?>
                           máy
                        </span>
                     <?php else: ?>
                        <span class="config-qty"
                           style="background:#F59E0B; color:#fff; box-shadow:0 4px 12px rgba(245,158,11,0.4);">
                           <i class="fa-solid fa-spinner fa-spin-pulse"></i> Đã nhập
                           <?php echo $machines_finished; ?>/<?php echo $qty; ?> máy
                        </span>
                     <?php endif; ?>
                  </div>
               </div>

               <table class="config-table">
                  <thead>
                     <tr>
                        <th>Máy</th>
                        <th>Linh kiện</th>
                        <th class="th-action">Thao tác</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php for ($i = 1; $i <= $qty; $i++):
                        $m_items = $data['machines'][$i] ?? [];
                        usort($m_items, function ($a, $b) {
                           $o = ['cpu' => 1, 'main' => 2, 'ram' => 3, 'ssd' => 4, 'vga' => 5, 'psu' => 6];
                           $pA = $o[strtolower(trim($a['loai_linhkien']))] ?? 99;
                           $pB = $o[strtolower(trim($b['loai_linhkien']))] ?? 99;
                           return ($pA !== $pB) ? ($pA <=> $pB) : ($a['id_ct'] <=> $b['id_ct']);
                        });
                        $filled = 0;
                        $total = count($m_items);
                        foreach ($m_items as $mi) {
                           $assigned_cfg = mb_strtolower(trim($mi['linhkien_chon'] ?? ''), 'UTF-8');
                           if (!empty($mi['so_serial']) && (string) $assigned_cfg == (string) $l_key && (int) $mi['so_may'] == (int) $i)
                              $filled++;
                        }
                        ?>
                        <tr>
                           <td>Máy số <?php echo $i; ?></td>
                           <td class="td-linhkien">
                              <div class="component-list">
                                 <?php foreach ($m_items as $mi):
                                    $has_s = !empty(trim((string) $mi['so_serial']));
                                    $assigned_cfg = mb_strtolower(trim($mi['linhkien_chon'] ?? ''), 'UTF-8');
                                    $is_assigned = ($has_s && (string) $assigned_cfg == (string) $l_key && (int) $mi['so_may'] == (int) $i);
                                    ?>
                                    <div class="component-item">
                                       <strong><?php echo strtoupper($mi['loai_linhkien']); ?>:</strong>
                                       <?php echo htmlspecialchars($mi['ten_linhkien']); ?>
                                       <?php if ($is_assigned): ?> <i class="fa-solid fa-circle-check" style="color:#30d81d;"></i>
                                       <?php endif; ?>
                                    </div>
                                 <?php endforeach; ?>
                              </div>
                           </td>
                           <td class="td-action">
                              <a href="kho-import-serial.php?id=<?php echo $order_id; ?>&config=<?php echo urlencode($dName); ?>&m=<?php echo $i; ?>"
                                 class="btn-nhap-serial-link <?php echo ($total > 0 && $filled === $total) ? 'finished' : ''; ?>">
                                 <span>NHẬP SERIAL <b><?php echo $filled . '/' . $total; ?></b></span>
                              </a>
                           </td>
                        </tr>
                     <?php endfor; ?>
                  </tbody>
               </table>
            </div>
         <?php endforeach; ?>
      </div>
   <?php endif; ?>
</main>