<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/', [ChatController::class, 'data']);


// Route::post('/token', function (Request $request) {
//     $request->validate([
//         'email'    => 'required|email',
//         'password' => 'required',
//     ]);

//     $user = User::where('email', $request->email)->first();

//     if (!$user || !Hash::check($request->password, $user->password)) {
//         return response()->json(['error' => 'Invalid credentials'], 401);
//     }

//     // Delete old tokens — so only ONE active token per user at a time
//     $user->tokens()->delete();

//     return response()->json([
//         'token' => $user->createToken('api-token')->plainTextToken
//     ]);
// });



Route::get('/get-data/{id}', [ChatController::class, 'data']);