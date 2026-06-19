<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\EditorialActionController;
use App\Http\Controllers\Admin\EditorialContentController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\PaypalWebhookController;
use App\Http\Controllers\CommunityInteractionController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\MuxWebhookController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\Royal\PremiumContentController;
use App\Http\Controllers\StoreRsvpController;
use App\Http\Controllers\TicketCheckInController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicContentController::class, 'home'])->name('home');
Route::get('/music/albums', [MusicController::class, 'albums'])->name('music.albums');
Route::get('/music/singles', [MusicController::class, 'singles'])->name('music.singles');
Route::get('/music/play/{content}', [MusicController::class, 'play'])->name('music.play');
Route::get('/videos', [PublicContentController::class, 'videos'])->name('videos');
Route::get('/photos', [PublicContentController::class, 'photos'])->name('photos');
Route::get('/community', [PublicContentController::class, 'community']);
Route::get('/community/clubs/{club}', [CommunityInteractionController::class, 'showClub'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->name('community.clubs.show');
Route::post('/community/posts/{post}/like', [CommunityInteractionController::class, 'like'])
    ->where('post', '[A-Za-z0-9._-]+')
    ->name('community.posts.like');
Route::post('/community/posts/{post}/replies', [CommunityInteractionController::class, 'reply'])
    ->where('post', '[A-Za-z0-9._-]+')
    ->name('community.posts.replies.store');
Route::post('/community/polls/{poll}/vote', [CommunityInteractionController::class, 'vote'])
    ->where('poll', '[A-Za-z0-9._-]+')
    ->name('community.polls.vote');
Route::post('/community/clubs', [CommunityInteractionController::class, 'storeClub'])
    ->name('community.clubs.store');
Route::post('/community/clubs/{club}/join', [CommunityInteractionController::class, 'joinClub'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->name('community.clubs.join');
Route::post('/community/clubs/{club}/messages', [CommunityInteractionController::class, 'clubMessage'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->name('community.clubs.messages.store');
Route::get('/store', [PublicContentController::class, 'store'])->name('store');
Route::get('/api/public-content/{page}', [PublicContentController::class, 'payload'])->name('public-content.payload');
Route::get('/content/{content}', [PublicContentController::class, 'show'])->name('public.content.show');

Route::prefix(config('admin.path', 'admin'))->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');

    Route::middleware(['admin.access', 'admin.session'])->group(function () {
        Route::get('/', [AdminLoginController::class, 'create'])->name('dashboard');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        Route::get('/site-editor', [AdminLoginController::class, 'create'])->name('site-editor.index');
        Route::get('/site-editor/{page}/preview', [AdminLoginController::class, 'create'])->name('site-editor.preview');
        Route::get('/site-editor/{page}', [AdminLoginController::class, 'create'])->name('site-editor.show');
        Route::get('/content', [AdminLoginController::class, 'create'])->name('content.index');
        Route::get('/content/create', [AdminLoginController::class, 'create'])->name('content.create');
        Route::post('/content', [EditorialContentController::class, 'store'])->middleware('admin.cms')->name('content.store');
        Route::get('/content/{content}/edit', [AdminLoginController::class, 'create'])->name('content.edit');
        Route::match(['put', 'patch'], '/content/{content}', [EditorialContentController::class, 'update'])->middleware('admin.cms')->name('content.update');
        Route::get('/content/{content}/preview', [AdminLoginController::class, 'create'])->name('content.preview');
        Route::get('/media', [AdminLoginController::class, 'create'])->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->middleware('admin.cms')->name('media.store');
        Route::post('/media/mux/direct-uploads', [MediaLibraryController::class, 'createMuxDirectUpload'])->middleware('admin.cms')->name('media.mux.direct-uploads.store');
        Route::get('/editorial', [AdminLoginController::class, 'create'])->name('editorial.index');
        Route::get('/editorial/{content}/edit', [AdminLoginController::class, 'create'])->name('editorial.edit');
        Route::get('/editorial/{content}/preview', [AdminLoginController::class, 'create'])->name('editorial.preview');
        Route::post('/editorial/drafts', [EditorialActionController::class, 'saveDraft'])->middleware('admin.cms')->name('editorial.drafts.store');
        Route::post('/editorial/publish', [EditorialActionController::class, 'publish'])
            ->middleware(['admin.cms', 'admin.publish'])
            ->name('editorial.publish');
        Route::post('/editorial/schedule', [EditorialActionController::class, 'schedule'])
            ->middleware(['admin.cms', 'admin.publish'])
            ->name('editorial.schedule');
        Route::post('/editorial/{content}', [EditorialActionController::class, 'updateDraft'])->middleware('admin.cms')->name('editorial.update');
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
    Route::post('/store/rsvp', StoreRsvpController::class)->name('store.rsvp');
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
Route::post('/checkout/local', [CheckoutController::class, 'local'])->name('checkout.local');
Route::post('/paypal/refund', [PaypalWebhookController::class, 'refund'])->name('paypal.refund');
Route::post('/mux/webhook', MuxWebhookController::class)->name('mux.webhook');
