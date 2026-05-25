<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlockAIController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Error page preview — development only
if (app()->isLocal()) {
    foreach ([400, 401, 402, 403, 404, 405, 419, 422, 429, 500, 502, 503] as $code) {
        Route::get("/$code", function () use ($code) {
            return Inertia::render('Error', ['status' => $code])->toResponse(request())->setStatusCode($code);
        });
    }
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{post}/like', [PostController::class, 'like'])->name('posts.like');
    Route::post('posts/{post}/repost', [PostController::class, 'repost'])->name('posts.repost');
    Route::post('posts/{post}/reply', [PostController::class, 'reply'])->name('posts.reply');
    Route::get('messages', [MessagesController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessagesController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessagesController::class, 'store'])->name('messages.store');
    Route::post('messages/{conversation}/typing', [MessagesController::class, 'typing'])->name('messages.typing');
    Route::post('conversations/with/{user}', [MessagesController::class, 'findOrCreate'])->name('conversations.find-or-create');
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications');
    Route::get('flock-ai', [FlockAIController::class, 'index'])->name('flock-ai');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('posts/{post}/replies.json', [PostController::class, 'repliesJson'])->name('posts.replies.json');
    Route::post('flock-ai/chat', [FlockAIController::class, 'chat'])->name('flock-ai.chat')->middleware('throttle:20,1');
    Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::delete('notifications', [NotificationsController::class, 'clearAll'])->name('notifications.clear');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/posts.json', [UserController::class, 'postsJson'])->name('users.posts.json');
    Route::post('users/{user}/follow', [FollowController::class, 'toggle'])->name('users.follow');
    Route::get('dashboard/posts.json', [DashboardController::class, 'postsJson'])->name('dashboard.posts.json');
    Route::get('search', [SearchController::class, 'index'])->name('search');
});

require __DIR__.'/settings.php';
