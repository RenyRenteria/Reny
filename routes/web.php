<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin.auth')->group(function (): void {
        Route::get('/', [SiteContentController::class, 'dashboard'])->name('dashboard');
        Route::put('/hero', [SiteContentController::class, 'updateHero'])->name('hero.update');
        Route::post('/albums', [SiteContentController::class, 'storeAlbum'])->name('albums.store');
        Route::put('/albums/{album}', [SiteContentController::class, 'updateAlbum'])->name('albums.update');
        Route::delete('/albums/{album}', [SiteContentController::class, 'destroyAlbum'])->name('albums.destroy');
        Route::post('/singles', [SiteContentController::class, 'storeSingle'])->name('singles.store');
        Route::put('/singles/{single}', [SiteContentController::class, 'updateSingle'])->name('singles.update');
        Route::delete('/singles/{single}', [SiteContentController::class, 'destroySingle'])->name('singles.destroy');
    });
});
