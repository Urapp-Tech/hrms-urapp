<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::group(['prefix' => 'companies/{companyId}'], function () {
    Route::post('/employees/enroll', [EmployeeController::class, 'enrollEmployee']);
    Route::get('/employees/not-enrolled', [EmployeeController::class, 'getNotEnrolledEmployees']);
    Route::patch('/employees/synchronize', [EmployeeController::class, 'synchronizeEmployees']);
    Route::get('/employees/enrolled', [EmployeeController::class, 'getEnrolledEmployees']);
    Route::post('/employees/attendance/mark', [AttendanceController::class, 'markAttendances']);
});

