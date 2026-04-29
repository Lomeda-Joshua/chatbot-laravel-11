<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ApiKey;

class ApiKeyController extends Controller
{
    public function generate(Request $request)
    {
            $request->validate([
                'name'         => 'required|string',
                'admin_secret' => 'required|string',   // ← extra protection
            ]);
            
            // verify admin secret from .env
            if ($request->admin_secret !== env('ADMIN_SECRET')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }


            // generate plain key
            $plainKey = Str::random(64);

            // store hashed version
            $apiKey = ApiKey::create([
                'name'      => $request->name,
                'key'       => hash('sha256', $plainKey),
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'API Key generated',
                'name'    => $apiKey->name,
                'key'     => $plainKey,       // ← shown ONCE, never stored plain
                'warning' => 'Copy this key now — it will never be shown again!'
            ], 201);
    }

    public function revoke(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        ApiKey::where('name', $request->name)->update(['is_active' => false]);

        return response()->json(['message' => 'API Key revoked']);
    }
}
