<?php
// Script để chạy migration
echo "Running migrations...\n";
exec('php artisan migrate --force', $output, $return_var);

if ($return_var === 0) {
    echo "Migrations completed successfully!\n";
    foreach ($output as $line) {
        echo $line . "\n";
    }
} else {
    echo "Migration failed!\n";
    foreach ($output as $line) {
        echo $line . "\n";
    }
}
?>