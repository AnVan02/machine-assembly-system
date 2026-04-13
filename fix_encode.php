<?php
$content = file_get_contents('kho-import-serial.php');
if (!$content) { die("Cannot read file"); }
$fixed = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
file_put_contents('kho-import-serial-fixed.php', $fixed);
echo "Done";
?>
