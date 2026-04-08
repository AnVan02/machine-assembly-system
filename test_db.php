<?php
require "config.php";
$stmt = $pdo->prepare("SELECT id_ct, so_serial, ten_cauhinh, ten_linhkien, loai_linhkien, linhkien_chon FROM chitiet_donhang LIMIT 30");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
