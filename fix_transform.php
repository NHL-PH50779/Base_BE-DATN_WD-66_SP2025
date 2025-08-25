<?php
$file = 'app/Http/Controllers/API/OrderController.php';
$content = file_get_contents($file);

// Thay thế tất cả transform functions
$oldPattern = '$item->product_info = [
                \'id\' => $item->product_id,
                \'name\' => $item->product_name ?: \'Sản phẩm đã xóa\',
                \'image\' => $item->product_image ?: \'/placeholder.svg\',
                \'variant_name\' => $item->variant_name ?: \'\'
            ];';

$newPattern = '$productName = $item->product_name ?: ($item->product ? $item->product->name : \'Sản phẩm đã xóa\');
            $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : \'/placeholder.svg\');
            $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : \'Mặc định\');
            
            $item->product_info = [
                \'id\' => $item->product_id,
                \'name\' => $productName,
                \'image\' => $productImage,
                \'variant_name\' => $variantName
            ];';

$content = str_replace($oldPattern, $newPattern, $content);
file_put_contents($file, $content);
echo "Fixed transform functions\n";
?>