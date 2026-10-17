<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Réception des prospects depuis le siteweb (protégé par jeton, sans CSRF)
Route::post('/leads', [LeadController::class, 'store'])
    ->name('leads.store')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, // Laravel 11+
        \App\Http\Middleware\VerifyCsrfToken::class,                      // Laravel 10
    ]);

// Désinscription : lien signé reçu par email (sans connexion ; sans CSRF car les clients mail font un POST "one-click")
Route::match(['get', 'post'], '/unsubscribe/{id}', UnsubscribeController::class)
    ->whereNumber('id')
    ->middleware('signed')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('unsubscribe');

// Connexion (sans Breeze)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/password', [AccountController::class, 'edit'])->name('password.edit');
    Route::put('/password', [AccountController::class, 'update'])->name('password.update');

    Route::get('/contacts/import', [ContactController::class, 'importForm'])->name('contacts.import.form');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::resource('contacts', ContactController::class)->except('show');

    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    Route::post('/campaigns/{campaign}/relaunch', [CampaignController::class, 'relaunch'])->name('campaigns.relaunch');
    Route::post('/campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->name('campaigns.schedule');
    Route::post('/campaigns/{campaign}/unschedule', [CampaignController::class, 'unschedule'])->name('campaigns.unschedule');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('/campaigns/{campaign}/test', [CampaignController::class, 'test'])->name('campaigns.test');
});