<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatBotLog extends Model
{
    
    protected $table = 'chatbot_logs';

    protected $fillable = [
            'group_id',
            'user_id',
            'details',
            'created_by',
            'updated_by',
            'is_active'
    ];

    
    // protected $casts = [
    //     'details' => 'string',
    // ];
}
