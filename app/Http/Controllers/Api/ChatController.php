<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\ChatBotQueries;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        
        $data = ChatBotQueries::all();
        return response()->json($data);
    }

    public function data(Request $request)
    {
        $data = ChatBotQueries::all();
        return response()->json($data);
    }
}
