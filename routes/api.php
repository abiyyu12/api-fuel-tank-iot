<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlameSensorController;
use App\Http\Controllers\FuelPriceController;
use App\Http\Controllers\GasSensorsController;
use App\Http\Controllers\MonitoringFlowMeterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmokeSensorsController;
use App\Http\Controllers\VolumeTankController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


Route::middleware([\App\Http\Middleware\VerifyApiKey::class])->group( function () {
    Route::post('/sensors/flow-meter',[MonitoringFlowMeterController::class, 'saveDataFlowMeter']);
    Route::post('/sensors/smoke',[SmokeSensorsController::class, 'saveSmokeSensor']);
    Route::post('/sensors/volume',[VolumeTankController::class, 'updateVolumeTankIoT']);
    Route::post('/sensors/flame', [FlameSensorController::class, 'saveFlameSensor']);
});

Route::get('/docs', function () {
    $path = public_path('docs/api_specs.yml'); // File ada di folder public
    if (!file_exists($path)) {
        abort(404, "File not found");
    }
    return Response::file($path, [
        'Content-Type' => 'text/yaml', // Set tipe konten YAML
    ]);
});

Route::middleware([\App\Http\Middleware\FirebaseAuthMiddleware::class])->group(function () {
    Route::delete('/logout', [AuthController::class, 'logout']);
    Route::get('/sensors/volume',[VolumeTankController::class, 'getVolumeTank']);
    Route::get('/sensors/flow-meter',[MonitoringFlowMeterController::class, 'getAllFlowMeterData']);
    Route::get('/sensors/flow-meter/today', [MonitoringFlowMeterController::class , 'getFlowMeterDataToday']);
    Route::get('/sensors/flow-meter/filter-date', [MonitoringFlowMeterController::class , 'getFlowMeterDataFilterDate']);
    Route::delete('sensors/flow-meter/{id}', [MonitoringFlowMeterController::class, 'deleteFlowMeterById']);
    Route::get('/sensors/smoke',[SmokeSensorsController::class, 'getSensorSmoke']);
    Route::get('/sensors/flame', [FlameSensorController::class, 'getFlameSensor']);
    Route::post('/fuel/price',[FuelPriceController::class, 'saveFuelPrice']);
    Route::get('/fuel/price',[FuelPriceController::class, 'getFuelPrice']);
    Route::get('/profile', [ProfileController::class,'getProfile']);
    Route::put('/profile/{id}',[ProfileController::class,'updateProfile']);
});