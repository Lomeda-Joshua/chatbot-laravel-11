<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatbotConstructorController;
use App\Http\Controllers\SSOLoginController;
use Illuminate\Support\Facades\Route;

// ===== SSO Routes =====
Route::get('sso/authenticate', [SSOLoginController::class, 'authenticate'])
    ->name('sso.authenticate');

Route::get('/autologin', [SSOLoginController::class, 'loginWithToken'])
    ->name('sso.autologin');

Route::post('sso/logout', [SSOLoginController::class, 'logout'])
    ->name('sso.logout');

// Redirect /login to Unified SSO
Route::get('/login', function () {
    return redirect(rtrim(config('sso.project1.url'), '/') . '/login');
})->name('login');

Route::get('/dashboard', function () {

    // $logged_user = Auth::user();    
    // return view('dashboard', compact($logged_user));
    return view('dashboard');

})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Chatbot constructor
Route::get('/chatbot-constuctor',[ChatbotConstructorController::class, 'index'])->middleware(['auth'])->name('chatbot-constructor');


Route::get('/', function () {
    return redirect('/dashboard');
})->middleware(['auth']);





require __DIR__.'/auth.php';
