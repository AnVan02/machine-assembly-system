<?php
require "config.php";
if ($pdo) {
    $pdo->exec("UPDATE chitiet_donhang SET linhkien_chon = NULL WHERE linhkien_chon IS NOT NULL AND linhkien_chon != ''");
    echo "Done resetting linhkien_chon";
}
