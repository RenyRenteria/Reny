<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\PaypalWebhookController;
use App\Http\Controllers\Royal\PremiumContentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/videos', function () {
    return view('videos');
});

Route::get('/photos', function () {
    return view('photos');
});

Route::get('/community', function () {
    return view('community');
});

Route::get('/store', function () {
    return view('store');
})->name('store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/royal/content/{resource}', [PremiumContentController::class, 'show'])
        ->middleware('royal')
        ->name('royal.content.show');
});

Route::get('/session-expired', function () {
    return view('auth.session-expired');
})->name('session.expired');

Route::post('/checkout/paypal', [CheckoutController::class, 'store'])->name('checkout.paypal');
Route::post('/paypal/refund', [PaypalWebhookController::class, 'refund'])->name('paypal.refund');
