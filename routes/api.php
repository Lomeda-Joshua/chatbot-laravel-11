<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SsoController;
use App\Http\Controllers\ApiKeyController;

// ===== Public SSO Routes (No auth required) =====
Route::prefix('sso')->group(function () {
    Route::post('/login', [SsoController::class, 'login']);
    Route::post('/verify-token', [SsoController::class, 'verifySsoToken']);
    Route::post('/logout', [SsoController::class, 'ssoLogout']);
    Route::post('/force-logout', [SsoController::class, 'forceLogout']);
});



// ===== Protected API Routes =====
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [SsoController::class, 'user']);
    Route::post('/sso/generate-token', [SsoController::class, 'generateSsoToken']);
    Route::post('/logout', [SsoController::class, 'logout']);
    Route::post('/logout-all', [SsoController::class, 'logoutAll']);
});

 Route::post('/admin/apikey/generate', [ApiKeyController::class, 'generate']);

 // ===== Chat Routes =====
    Route::prefix('chat')->group(function () {
        // Get step endpotin
        Route::get('/get-step',     [ChatController::class, 'data']);
        Route::post('/get-step',    [ChatController::class, 'nextStep']);

        // Save logs enpodoint logging of history
        Route::middleware(['web','auth'])->post('/save-logs',   [ChatController::class, 'saveLog']);

        // Location and address endpoints
        Route::get('/regions', [ ChatController::class, 'getRegion'] );
        Route::get('/provinces', [ ChatController::class, 'getProvinces'] );
        Route::get('/municipalities', [ ChatController::class, 'getMunicipalities'] );
        Route::get('/barangays', [ ChatController::class, 'getBarangays'] );
        Route::get('/search-barangays', [ ChatController::class, 'searchBrgy'] );

        Route::post('/api-test', [ChatController::class, 'saveLog'])->middleware('auth:sanctum');
    });

   



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



