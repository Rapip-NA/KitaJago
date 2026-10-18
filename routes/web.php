<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\LeadAdminController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ParticipantOnly;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/login', 'login')->name('login');
Route::post('/otp/request', [OtpController::class, 'request'])->middleware('throttle:30,1');
Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:60,1');
Route::get('/dashboard', [SettingsController::class, 'dashboard'])->middleware(ParticipantOnly::class);
Route::post('/daftar', [RegistrationController::class, 'store'])->middleware('throttle:10,1');
Route::view('/terima-kasih', 'thanks');
Route::view('/admin/login', 'admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/logout', [AdminAuthController::class, 'logout']);
Route::middleware(AdminOnly::class)->prefix('admin')->group(function () {
    Route::get('/settings', [SettingsController::class, 'edit']);
    Route::post('/settings', [SettingsController::class, 'update']);
    Route::post('/leads/{lead}/access', [LeadAdminController::class, 'access']);
    Route::get('/leads', [LeadAdminController::class, 'index']);
    Route::get('/leads/{lead}', [LeadAdminController::class, 'show']);
    Route::post('/leads/{lead}/payment', [LeadAdminController::class, 'payment']);
    Route::post('/leads/{lead}/provision', [LeadAdminController::class, 'provision']);
});
