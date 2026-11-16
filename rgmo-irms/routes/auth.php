<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    });
/*
Dear Aly,

 Halo Aly iam writing this code for our capstone project throughout my development you are always
 by my side and i just wanna thank you for everything from late night chikas to study calls with you 
 my year has been so much better because of you and i just wanna say thank you my beshy, my mamshie, mah love
 hoping for your dreams to come true and for you to be happy and healthy always, 
 i love you so muchie muchie may favourite shade of blue, loves a lot <3

 -Clip


*/
Route::middleware('auth')->group(function () {
    // Keep old verification URLs harmless for existing browser sessions and links.
    Route::get('verify-email', fn () => redirect()->route('dashboard'));
    Route::get('verify-email/{id}/{hash}', fn () => redirect()->route('dashboard'))
        ->whereNumber('id');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});


