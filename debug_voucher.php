<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Voucher Values ===" . PHP_EOL;

// Lấy voucher BTC7 và BTC5
$vouchers = App\Models\Voucher::whereIn('code', ['BTC7', 'BTC5'])->get();

foreach ($vouchers as $voucher) {
    echo "--- Voucher: " . $voucher->code . " ---" . PHP_EOL;
    echo "ID: " . $voucher->id . PHP_EOL;
    echo "Name: " . $voucher->name . PHP_EOL;
    echo "Type: " . $voucher->type . PHP_EOL;
    echo "Value (raw): " . $voucher->value . " (type: " . gettype($voucher->value) . ")" . PHP_EOL;
    echo "Value (cast): " . (float)$voucher->value . PHP_EOL;
    echo "Min Order: " . $voucher->min_order_amount . PHP_EOL;
    echo "Created: " . $voucher->created_at . PHP_EOL;
    echo "Updated: " . $voucher->updated_at . PHP_EOL;
    echo PHP_EOL;
}

// Kiểm tra cấu trúc bảng
echo "=== Table Structure ===" . PHP_EOL;
$columns = DB::select("DESCRIBE vouchers");
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type . " - " . $column->Default . PHP_EOL;
}