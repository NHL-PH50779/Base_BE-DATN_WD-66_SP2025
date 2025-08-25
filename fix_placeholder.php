<?php
// Script để thay thế tất cả string rỗng bằng placeholder
$file = 'app/Http/Controllers/API/OrderController.php';
$content = file_get_contents($file);

// Thay thế tất cả các trường hợp
$content = str_replace("'image' => \$item->product_image ?: '',", "'image' => \$item->product_image ?: '/placeholder.svg',", $content);
$content = str_replace("'product_image' => \$product && \$product->thumbnail ? \$product->thumbnail : '',", "'product_image' => \$product && \$product->thumbnail ? \$product->thumbnail : '/placeholder.svg',", $content);

file_put_contents($file, $content);
echo "Fixed placeholder paths\n";
?>