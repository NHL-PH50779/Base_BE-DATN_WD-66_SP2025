<?php
$file = 'app/Http/Controllers/API/OrderController.php';
$content = file_get_contents($file);

// Thay thế tất cả placeholder paths
$content = str_replace("'/placeholder.svg'", "'http://127.0.0.1:8000/placeholder.svg'", $content);

file_put_contents($file, $content);
echo "Final fix applied - all placeholder paths updated\n";
?>