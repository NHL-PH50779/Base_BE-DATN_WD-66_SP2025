 <?php
use Illuminate\Support\Facades\Route;
 use App\Http\Controllers\Api\BannerController;

Route::apiResource('banners', BannerController::class);
