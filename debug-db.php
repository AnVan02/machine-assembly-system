<?php
require "config.php";

// Lấy tất cả đơn hàng
$orders = $pdo->query("SELECT id_donhang, ma_don_hang FROM donhang ORDER BY id_donhang DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Danh sách đơn hàng</h2><pre>";
foreach ($orders as $o) {
    echo "id={$o['id_donhang']} | {$o['ma_don_hang']}\n";
}
echo "</pre>";

// Kiểm tra order cụ thể (mặc định = 1, thay đổi để test)
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : ($orders[0]['id_donhang'] ?? 1);
echo "<h2>Debug Order ID: $order_id</h2>";

$rows = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
$rows->execute([$order_id]);
$rows = $rows->fetchAll(PDO::FETCH_ASSOC);

// Simulate grouped_configs
$grouped_configs = [];
foreach ($rows as $row) {
    $c_names = explode(',', $row['ten_cauhinh']);
    foreach ($c_names as $cn) {
        $lbl = trim($cn) ?: 'Cấu hình chung';
        $key = mb_strtolower($lbl, 'UTF-8');
        if (!isset($grouped_configs[$key])) {
            $grouped_configs[$key] = ['name' => $lbl, 'items' => []];
        }
        $grouped_configs[$key]['items'][] = $row;
    }
}

echo "<h3>Grouped configs:</h3><pre>";
foreach ($grouped_configs as $k => $data) {
    echo "--- Config key: '$k' | name: '{$data['name']}' | items: " . count($data['items']) . "\n";
    foreach ($data['items'] as $item) {
        $trailing = strlen($item['ten_cauhinh']) - strlen(rtrim($item['ten_cauhinh']));
        $pool_configs = array_map('trim', explode(',', $item['ten_cauhinh']));
        sort($pool_configs);
        $owner_name = $pool_configs[$trailing] ?? '???';
        
        echo "  id_ct={$item['id_ct']} | type={$item['loai_linhkien']} | ten={$item['ten_linhkien']}\n";
        echo "    ten_cauhinh=[{$item['ten_cauhinh']}] (trailing_spaces={$trailing})\n";
        echo "    pool_configs_sorted=" . json_encode($pool_configs, JSON_UNESCAPED_UNICODE) . "\n";
        echo "    owner_idx={$trailing} → owner='{$owner_name}'\n";
        echo "    linhkien_chon=[{$item['linhkien_chon']}] | so_may={$item['so_may']}\n";
        echo "    → belongs_to_config_'$k': " . (mb_strtolower($owner_name,'UTF-8') === $k ? 'YES ✓' : 'NO ✗') . "\n\n";
    }
}
echo "</pre>";
?>
