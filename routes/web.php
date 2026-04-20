<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatbotConstructorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Http\Request;

use App\Models\User;


Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

Route::get('/dashboard', function () {

    // $logged_user = Auth::user();    
    // return view('dashboard', compact($logged_user));
    return view('dashboard');

})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Chatbot constructor
Route::get('/chatbot-constuctor',[ChatbotConstructorController::class, 'index'])->middleware('auth')->name('chatbot-constructor');


Route::get('/', function () {
    return response()->json([
        'timestamp' => now()->format('Y-m-d H:i:s O'),
        'status'    => 401,
        'error'     => 'Unauthorized',
        'path'      => '/'
    ], 401);
});





require __DIR__.'/auth.php';
