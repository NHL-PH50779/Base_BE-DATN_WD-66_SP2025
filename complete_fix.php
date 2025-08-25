<?php
$file = 'app/Http/Controllers/API/OrderController.php';
$content = file_get_contents($file);

// Thay thế hoàn toàn tất cả transform functions
$content = preg_replace(
    '/\$item->product_info = \[\s*\'id\' => \$item->product_id,\s*\'name\' => \$item->product_name \?: \'Sản phẩm đã xóa\',\s*\'image\' => \$item->product_image \?: \'\/placeholder\.svg\',\s*\'variant_name\' => \$item->variant_name \?: \'\'\s*\];/',
    '$productName = $item->product_name ?: ($item->product ? $item->product->name : \'Sản phẩm đã xóa\');
            $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : \'http://127.0.0.1:8000/placeholder.svg\');
            $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : \'Mặc định\');
            
            $item->product_info = [
                \'id\' => $item->product_id,
                \'name\' => $productName,
                \'image\' => $productImage,
                \'variant_name\' => $variantName
            ];',
    $content
);

file_put_contents($file, $content);
echo "Complete fix applied\n";
?>