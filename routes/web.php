<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\CommunityMemberController as AdminCommunityMemberController;
use App\Http\Controllers\Admin\CommunityPostController as AdminCommunityPostController;
use App\Http\Controllers\Admin\CommunityRsvpController as AdminCommunityRsvpController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EditorialActionController;
use App\Http\Controllers\Admin\EditorialContentController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\PhotoLibraryController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\AnalyticsEventController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\PaypalWebhookController;
use App\Http\Controllers\CommunityInteractionController;
use App\Http\Controllers\FreeEventRsvpController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\MuxWebhookController;
use App\Http\Controllers\PhotoAssetController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\Royal\PremiumContentController;
use App\Http\Controllers\StoreRsvpController;
use App\Http\Controllers\TicketCheckInController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicContentController::class, 'home'])->name('home');
Route::get('/music', [MusicController::class, 'index'])->name('music');
Route::get('/music/albums', [MusicController::class, 'albums'])->name('music.albums');
Route::get('/music/singles', [MusicController::class, 'singles'])->name('music.singles');
Route::get('/music/playlists', [MusicController::class, 'playlists'])->name('music.playlists');
Route::get('/music/play/{content}', [MusicController::class, 'play'])->name('music.play');
Route::get('/album/{album}', [MusicController::class, 'album'])->name('music.albums.show');
Route::get('/videos', [PublicContentController::class, 'videos'])->name('videos');
Route::get('/photos', [PublicContentController::class, 'photos'])->name('photos');
Route::get('/royals', [PublicContentController::class, 'community'])->name('royals');
Route::get('/community', [PublicContentController::class, 'community'])->name('community.legacy');
Route::get('/community/live-chat/messages', [CommunityInteractionController::class, 'liveChatMessages'])
    ->middleware('throttle:120,1')
    ->name('community.live-chat.messages.index');
Route::post('/community/live-chat/messages', [CommunityInteractionController::class, 'storeLiveChatMessage'])
    ->middleware('throttle:community-chat')
    ->name('community.live-chat.messages.store');
Route::post('/community/live-chat/users/{user}/block', [CommunityInteractionController::class, 'blockLiveChatUser'])
    ->whereNumber('user')
    ->middleware(['auth', 'throttle:community-writes'])
    ->name('community.live-chat.users.block');
Route::delete('/community/live-chat/messages/{message}', [CommunityInteractionController::class, 'moderateLiveChatMessage'])
    ->whereNumber('message')
    ->middleware(['auth', 'throttle:community-writes'])
    ->name('community.live-chat.messages.moderate');
Route::get('/community/clubs/{club}', [CommunityInteractionController::class, 'showClub'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->name('community.clubs.show');
Route::post('/community/posts/{post}/like', [CommunityInteractionController::class, 'like'])
    ->where('post', '[A-Za-z0-9._-]+')
    ->middleware('throttle:community-writes')
    ->name('community.posts.like');
Route::post('/community/posts/{post}/replies', [CommunityInteractionController::class, 'reply'])
    ->where('post', '[A-Za-z0-9._-]+')
    ->middleware('throttle:community-writes')
    ->name('community.posts.replies.store');
Route::post('/community/polls/{poll}/vote', [CommunityInteractionController::class, 'vote'])
    ->where('poll', '[A-Za-z0-9._-]+')
    ->middleware('throttle:community-writes')
    ->name('community.polls.vote');
Route::post('/community/clubs', [CommunityInteractionController::class, 'storeClub'])
    ->middleware('throttle:community-writes')
    ->name('community.clubs.store');
Route::post('/community/clubs/{club}/join', [CommunityInteractionController::class, 'joinClub'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->middleware('throttle:community-writes')
    ->name('community.clubs.join');
Route::post('/community/clubs/{club}/messages', [CommunityInteractionController::class, 'clubMessage'])
    ->where('club', '[A-Za-z0-9._-]+')
    ->middleware('throttle:community-chat')
    ->name('community.clubs.messages.store');
Route::get('/shows', [PublicContentController::class, 'shows'])->name('shows');
Route::get('/store', [PublicContentController::class, 'store'])->name('store');
Route::get('/store/checkout/{product}', [PublicContentController::class, 'checkout'])
    ->where('product', '[A-Za-z0-9._-]+')
    ->name('store.checkout');
Route::post('/analytics/events', [AnalyticsEventController::class, 'store'])
    ->middleware('throttle:analytics-events')
    ->name('analytics.events.store');
Route::post('/api/community/register-free-event', FreeEventRsvpController::class)
    ->middleware('throttle:20,1')
    ->name('community.free-event-rsvp.store');
Route::get('/api/public-content/{page}', [PublicContentController::class, 'payload'])->name('public-content.payload');
Route::get('/content/{content}', [PublicContentController::class, 'show'])->name('public.content.show');
Route::get('/photos/{photo}/image', [PhotoAssetController::class, 'show'])
    ->middleware(['auth', 'royal'])
    ->name('photos.image.show');

Route::post('/api/cms/photos/upload', [PhotoLibraryController::class, 'upload'])
    ->middleware(['admin.access', 'admin.session', 'admin.cms'])
    ->name('cms.photos.upload');

Route::prefix(config('admin.path', 'admin'))->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:admin-auth-login')
        ->name('login.store');

    Route::middleware(['admin.access', 'admin.session'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        Route::get('/site-editor', [SiteEditorController::class, 'index'])->middleware('admin.cms')->name('site-editor.index');
        Route::get('/site-editor/{page}/preview', [SiteEditorController::class, 'preview'])->middleware('admin.cms')->name('site-editor.preview');
        Route::post('/site-editor/{page}/settings', [SiteEditorController::class, 'updatePageSettings'])->middleware('admin.cms')->name('site-editor.page-settings.update');
        Route::get('/site-editor/community/members.csv', AdminCommunityMemberController::class)->middleware('admin.cms')->name('site-editor.community-members.export');
        Route::get('/site-editor/community/rsvps.csv', AdminCommunityRsvpController::class)->middleware('admin.cms')->name('site-editor.community-rsvps.export');
        Route::middleware(['admin.cms', 'admin.community-posts'])->group(function () {
            Route::post('/site-editor/community/posts', [AdminCommunityPostController::class, 'store'])->name('site-editor.community-posts.store');
            Route::patch('/site-editor/community/posts/{post}', [AdminCommunityPostController::class, 'update'])->whereNumber('post')->name('site-editor.community-posts.update');
            Route::delete('/site-editor/community/posts/{post}', [AdminCommunityPostController::class, 'destroy'])->whereNumber('post')->name('site-editor.community-posts.destroy');
            Route::patch('/site-editor/community/comments/{reply}', [AdminCommunityPostController::class, 'moderateReply'])->whereNumber('reply')->name('site-editor.community-comments.moderate');
        });
        Route::get('/site-editor/{page}', [SiteEditorController::class, 'show'])->middleware('admin.cms')->name('site-editor.show');
        Route::post('/site-editor/music/banner', [SiteEditorController::class, 'updateMusicBanner'])->middleware('admin.cms')->name('site-editor.music-banner.update');
        Route::post('/site-editor/store/storefront', [SiteEditorController::class, 'updateStorefront'])->middleware('admin.cms')->name('site-editor.storefront.update');
        Route::get('/photos', [PhotoLibraryController::class, 'index'])->middleware('admin.cms')->name('photos.index');
        Route::post('/photos/albums', [PhotoLibraryController::class, 'storeAlbum'])->middleware('admin.cms')->name('photos.albums.store');
        Route::patch('/photos/albums/{album}', [PhotoLibraryController::class, 'updateAlbum'])->middleware('admin.cms')->name('photos.albums.update');
        Route::delete('/photos/albums/{album}', [PhotoLibraryController::class, 'destroyAlbum'])->middleware('admin.cms')->name('photos.albums.destroy');
        Route::patch('/photos/{photo}', [PhotoLibraryController::class, 'update'])->middleware('admin.cms')->name('photos.update');
        Route::delete('/photos/{photo}', [PhotoLibraryController::class, 'destroy'])->middleware('admin.cms')->name('photos.destroy');
        Route::post('/photos/batch', [PhotoLibraryController::class, 'batch'])->middleware('admin.cms')->name('photos.batch');
        Route::post('/photos/reorder', [PhotoLibraryController::class, 'reorder'])->middleware('admin.cms')->name('photos.reorder');

        Route::get('/content', [EditorialContentController::class, 'index'])->middleware('admin.cms')->name('content.index');
        Route::get('/content/create', [EditorialContentController::class, 'create'])->middleware('admin.cms')->name('content.create');
        Route::post('/content', [EditorialContentController::class, 'store'])->middleware('admin.cms')->name('content.store');
        Route::post('/content/album-track-audio', [EditorialContentController::class, 'storeAlbumTrackAudio'])->middleware('admin.cms')->name('content.album-track-audio.store');
        Route::get('/content/{content}/edit', [EditorialContentController::class, 'edit'])->middleware('admin.cms')->name('content.edit');
        Route::match(['put', 'patch'], '/content/{content}', [EditorialContentController::class, 'update'])->middleware('admin.cms')->name('content.update');
        Route::delete('/content/{content}', [EditorialContentController::class, 'destroy'])->middleware('admin.cms')->name('content.destroy');
        Route::post('/content/{content}/archive', [EditorialContentController::class, 'archive'])->middleware(['admin.cms', 'admin.publish'])->name('content.archive');
        Route::get('/content/{content}/preview', [EditorialContentController::class, 'preview'])->middleware('admin.cms')->name('content.preview');
        Route::get('/media', [MediaLibraryController::class, 'index'])->middleware('admin.cms')->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->middleware('admin.cms')->name('media.store');
        Route::patch('/media/{asset}', [MediaLibraryController::class, 'update'])->middleware('admin.cms')->name('media.update');
        Route::post('/media/{asset}/replace', [MediaLibraryController::class, 'replace'])->middleware('admin.cms')->name('media.replace');
        Route::delete('/media/{asset}', [MediaLibraryController::class, 'destroy'])->middleware('admin.cms')->name('media.destroy');
        Route::post('/media/mux/direct-uploads', [MediaLibraryController::class, 'createMuxDirectUpload'])->middleware('admin.cms')->name('media.mux.direct-uploads.store');
        Route::get('/editorial', [EditorialActionController::class, 'index'])->middleware('admin.cms')->name('editorial.index');
        Route::get('/editorial/{content}/edit', [EditorialActionController::class, 'edit'])->middleware('admin.cms')->name('editorial.edit');
        Route::get('/editorial/{content}/preview', [EditorialActionController::class, 'preview'])->middleware('admin.cms')->name('editorial.preview');
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
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth-login')
        ->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth-password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::post('/account/avatar', [AccountController::class, 'updateAvatar'])->name('account.avatar.update');
    Route::patch('/account/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences.update');
    Route::post('/account/subscription/pause', [AccountController::class, 'pauseSubscription'])->name('account.subscription.pause');
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

Route::post('/checkout/paypal/orders', [CheckoutController::class, 'createOrder'])
    ->middleware('throttle:checkout')
    ->name('checkout.paypal.orders');
Route::post('/checkout/paypal/orders/cancel', [CheckoutController::class, 'cancelOrder'])
    ->middleware('throttle:checkout')
    ->name('checkout.paypal.orders.cancel');
Route::post('/checkout/paypal', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('checkout.paypal');
Route::post('/checkout/local', [CheckoutController::class, 'local'])
    ->middleware('throttle:checkout')
    ->name('checkout.local');
Route::post('/paypal/refund', [PaypalWebhookController::class, 'refund'])->name('paypal.refund');
Route::post('/mux/webhook', MuxWebhookController::class)->name('mux.webhook');
