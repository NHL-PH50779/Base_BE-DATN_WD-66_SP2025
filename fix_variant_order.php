<?php
require_once 'vendor/autoload.php';

use App\Models\ProductVariant;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== SỬA THỨ TỰ BIẾN THỂ: SSD - RAM - CPU ===\n";
    
    // Tìm các biến thể có format sai (Intel/AMD ở đầu)
    $wrongVariants = ProductVariant::where('Name', 'like', 'Intel -%')
        ->orWhere('Name', 'like', 'AMD -%')
        ->get();
    
    echo "Tìm thấy " . $wrongVariants->count() . " biến thể cần sửa thứ tự\n\n";
    
    foreach($wrongVariants as $variant) {
        echo "--- Biến thể ID: {$variant->id} ---\n";
        echo "Tên cũ: {$variant->Name}\n";
        
        $oldName = $variant->Name;
        $newName = '';
        
        // Phân tích và sắp xếp lại
        if (preg_match('/^(Intel|AMD)\s*-\s*(.+)$/', $oldName, $matches)) {
            $cpu = $matches[1]; // Intel hoặc AMD
            $remaining = $matches[2]; // Phần còn lại
            
            // Tách SSD và RAM từ phần còn lại
            $parts = explode(' - ', $remaining);
            
            if (count($parts) >= 2) {
                $ssd = $parts[0];
                $ram = $parts[1];
                
                // Format mới: SSD - RAM - CPU
                $newName = "{$ssd} - {$ram} - {$cpu}";
            } else {
                // Nếu không tách được, giữ nguyên
                $newName = $oldName;
            }
        }
        
        if ($newName && $newName !== $oldName) {
            $variant->update(['Name' => $newName]);
            echo "Tên mới: {$newName}\n";
            echo "✅ Đã cập nhật\n\n";
        } else {
            echo "⚠️ Không thể phân tích, giữ nguyên\n\n";
        }
    }
    
    // Thêm giá trị AMD vào thuộc tính CPU nếu chưa có
    echo "=== KIỂM TRA THUỘC TÍNH CPU ===\n";
    
    $cpuAttribute = \App\Models\Attribute::where('name', 'CPU')->first();
    if ($cpuAttribute) {
        echo "Thuộc tính CPU đã tồn tại (ID: {$cpuAttribute->id})\n";
        
        // Kiểm tra giá trị AMD
        $amdValue = $cpuAttribute->values()->where('value', 'AMD')->first();
        if (!$amdValue) {
            $cpuAttribute->values()->create(['value' => 'AMD']);
            echo "✅ Đã thêm giá trị AMD\n";
        } else {
            echo "✅ Giá trị AMD đã tồn tại\n";
        }
        
        // Kiểm tra giá trị Intel
        $intelValue = $cpuAttribute->values()->where('value', 'Intel')->first();
        if (!$intelValue) {
            $cpuAttribute->values()->create(['value' => 'Intel']);
            echo "✅ Đã thêm giá trị Intel\n";
        } else {
            echo "✅ Giá trị Intel đã tồn tại\n";
        }
    }
    
    echo "\n=== THỐNG KÊ SAU CẬP NHẬT ===\n";
    
    // Hiển thị một số biến thể mẫu
    $sampleVariants = ProductVariant::take(10)->get();
    foreach($sampleVariants as $variant) {
        echo "- {$variant->Name}\n";
    }
    
    echo "\n✅ Hoàn thành sửa thứ tự biến thể!\n";
    echo "Format mới: SSD - RAM - CPU\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}