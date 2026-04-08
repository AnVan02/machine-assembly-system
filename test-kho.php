<?php
$grouped_configs = [
    'cấu hình 1' => ['name' => 'Cấu hình 1', 'items' => []],
    'cấu hình 2' => ['name' => 'Cấu hình 2', 'items' => []]
];

// MOCK data: 
// Cấu hình 1: 1 máy => 1 RAM
// Cấu hình 2: 2 máy => 4 RAM (2 per machine)
// total RAM = 5 rows
$mock_rows = [];
for ($i = 0; $i < 5; $i++) {
    $mock_rows[] = [
        'ten_cauhinh' => 'cấu hình 1, Cấu hình 2',
        'ten_linhkien' => 'RAM 8GB',
        'loai_linhkien' => 'RAM'
    ];
}
// 1 CPU for Config 1
$mock_rows[] = ['ten_cauhinh' => 'Cấu hình 1', 'ten_linhkien' => 'CPU 1', 'loai_linhkien' => 'CPU'];
// 2 CPU for Config 2
$mock_rows[] = ['ten_cauhinh' => 'Cấu hình 2', 'ten_linhkien' => 'CPU 2', 'loai_linhkien' => 'CPU'];
$mock_rows[] = ['ten_cauhinh' => 'Cấu hình 2', 'ten_linhkien' => 'CPU 2', 'loai_linhkien' => 'CPU'];

foreach ($mock_rows as $row) {
    $c_names = array_map('trim', explode(',', $row['ten_cauhinh']));
    foreach ($c_names as $cn) {
        $k = mb_strtolower($cn, 'UTF-8');
        $grouped_configs[$k]['items'][] = $row;
    }
}

// simulate kho-hang.php
$config_qtys = [];
foreach ($grouped_configs as $k => $data) {
    $type_counts = array_count_values(array_map('strtolower', array_column($data['items'], 'loai_linhkien')));
    $config_qtys[$k] = !empty($type_counts) ? min($type_counts) : 1;
}

echo "Config quantities:\n";
print_r($config_qtys);

foreach ($grouped_configs as $key => $data) {
    $qty = $config_qtys[$key];
    $components = $data['items'];
    $grouped_by_type = [];
    foreach ($components as $c) {
        $grouped_by_type[strtolower(trim($c['loai_linhkien']))][] = $c;
    }

    echo "--- $key ($qty máy) ---\n";
    for ($i = 1; $i <= $qty; $i++) {
        echo "Máy số $i:\n";
        $display_components = [];

        foreach ($grouped_by_type as $type => $all_list_for_type) {
            $subgroups = [];
            foreach ($all_list_for_type as $item) {
                $cfg_parts = array_map(function ($p) {
                    return mb_strtolower(trim($p), 'UTF-8');
                }, explode(',', $item['ten_cauhinh']));
                sort($cfg_parts);
                $cfg_key_set = implode(',', $cfg_parts);
                $subgroups[$cfg_key_set][] = $item;
            }

            foreach ($subgroups as $cfg_key_set => $sublist) {
                // BUG IS HERE ??? Let's trace it exactly!
                $involved_configs = explode(',', $cfg_key_set);
                $total_machines_in_set = 0;
                $rank = 0;
                foreach ($involved_configs as $ic) {
                    // WHAT IF $ic does not exactly match $key because of accent marks or something?
                    // We mapped to mb_strtolower(trim($p), 'UTF-8') so it SHOULD match.
                    $iqty = $config_qtys[$ic] ?? 1;
                    if ($ic === $key) {
                        $rank = $total_machines_in_set + $i;
                    }
                    $total_machines_in_set += $iqty;
                }

                $total_c = count($sublist);
                $base = floor($total_c / $total_machines_in_set);
                $rem = $total_c % $total_machines_in_set;

                $current_count = $base + (($total_machines_in_set - $rank + 1) <= $rem ? 1 : 0);
                $start_idx = 0;
                for ($r = 1; $r < $rank; $r++) {
                    $start_idx += $base + (($total_machines_in_set - $r + 1) <= $rem ? 1 : 0);
                }
                for ($j = 0; $j < $current_count; $j++) {
                    $idx = $start_idx + $j;
                    if (isset($sublist[$idx])) {
                        $display_components[] = htmlspecialchars($sublist[$idx]['ten_linhkien']);
                    }
                }
            }
        }
        print_r($display_components);
    }
}
