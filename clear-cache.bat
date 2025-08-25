@echo off
echo Clearing Laravel cache...
php artisan route:clear
php artisan config:clear
php artisan cache:clear
echo Cache cleared successfully!
pause