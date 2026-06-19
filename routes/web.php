<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EditorialActionController;
use App\Http\Controllers\Admin\EditorialContentController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\PaypalWebhookController;
use App\Http\Controllers\MuxWebhookController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\Royal\PremiumContentController;
use App\Http\Controllers\TicketCheckInController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicContentController::class, 'home'])->name('home');
Route::get('/videos', [PublicContentController::class, 'videos']);
Route::get('/photos', [PublicContentController::class, 'photos']);
Route::get('/community', [PublicContentController::class, 'community']);
Route::get('/store', [PublicContentController::class, 'store'])->name('store');
Route::get('/api/public-content/{page}', [PublicContentController::class, 'payload'])->name('public-content.payload');
Route::get('/content/{content}', [PublicContentController::class, 'show'])->name('public.content.show');

Route::prefix(config('admin.path', 'admin'))->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');

    Route::middleware(['admin.access', 'admin.session'])->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('/site-editor', [SiteEditorController::class, 'index'])->name('site-editor.index');
        Route::get('/site-editor/{page}', [SiteEditorController::class, 'show'])->name('site-editor.show');
        Route::get('/content', [EditorialContentController::class, 'index'])->name('content.index');
        Route::get('/content/create', [EditorialContentController::class, 'create'])->name('content.create');
        Route::post('/content', [EditorialContentController::class, 'store'])->name('content.store');
        Route::get('/content/{content}/edit', [EditorialContentController::class, 'edit'])->name('content.edit');
        Route::match(['put', 'patch'], '/content/{content}', [EditorialContentController::class, 'update'])->name('content.update');
        Route::get('/content/{content}/preview', [EditorialContentController::class, 'preview'])->name('content.preview');
        Route::get('/media', [MediaLibraryController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->name('media.store');
        Route::post('/media/mux/direct-uploads', [MediaLibraryController::class, 'createMuxDirectUpload'])->name('media.mux.direct-uploads.store');
        Route::get('/editorial', [EditorialContentController::class, 'index'])->name('editorial.index');
        Route::get('/editorial/{content}/edit', [EditorialContentController::class, 'edit'])->name('editorial.edit');
        Route::get('/editorial/{content}/preview', [EditorialContentController::class, 'preview'])->name('editorial.preview');
        Route::post('/editorial/drafts', [EditorialActionController::class, 'saveDraft'])->name('editorial.drafts.store');
        Route::post('/editorial/publish', [EditorialActionController::class, 'publish'])
            ->middleware('admin.publish')
            ->name('editorial.publish');
        Route::post('/editorial/schedule', [EditorialActionController::class, 'schedule'])
            ->middleware('admin.publish')
            ->name('editorial.schedule');
        Route::post('/editorial/{content}', [EditorialActionController::class, 'updateDraft'])->name('editorial.update');
    });
});

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
    Route::get('/points', [PointsController::class, 'index'])->name('points.index');
    Route::post('/tickets/check-in', [TicketCheckInController::class, 'store'])->name('tickets.check-in');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/royal/content/{resource}', [PremiumContentController::class, 'show'])
        ->middleware('royal')
        ->name('royal.content.show');
});

Route::get('/session-expired', function () {
    return view('auth.session-expired');
})->name('session.expired');

Route::post('/checkout/paypal/orders', [CheckoutController::class, 'createOrder'])->name('checkout.paypal.orders');
Route::post('/checkout/paypal', [CheckoutController::class, 'store'])->name('checkout.paypal');
Route::post('/paypal/refund', [PaypalWebhookController::class, 'refund'])->name('paypal.refund');
Route::post('/mux/webhook', MuxWebhookController::class)->name('mux.webhook');
