<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\SettingsController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/settings', [SettingsController::class, 'showForm'])->name('settings.form');
Route::post('/settings', [SettingsController::class, 'saveSettings'])->name('settings.save');

Route::get('/install', [ShopifyController::class, 'install']);
Route::get('/auth/callback', [ShopifyController::class, 'callback']);
