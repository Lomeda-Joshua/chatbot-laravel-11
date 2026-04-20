<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChatBotQueries;

class ChatbotConstructorController extends Controller
{
    /**
     * Display a listing of the resource.   
     */
    public function index()
    {
        // $db_connectionC_count = DB::connection('mysql2')->table('some_table')->get();
        // dd($db_connectionC_count);


        // dd(ChatBotQueries::all());

        // query_name
        // choices
        // is_form
        // image_url
        // form_description (form label)
        // form_details ( form inputs )
        // navigatation

        return view('pages.chatbot_constructor');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
