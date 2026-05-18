<?php

use App\Http\Controllers\FlockAIController;
use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('messages', 'Messages')->name('messages');
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications');
    Route::get('flock-ai', [FlockAIController::class, 'index'])->name('flock-ai');
    Route::post('flock-ai/chat', [FlockAIController::class, 'chat'])->name('flock-ai.chat');
    Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
