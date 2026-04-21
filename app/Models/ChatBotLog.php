<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatBotLog extends Model
{
    protected $table = 'chatbot_logs';

    protected $fillable = [
            'id',
            'group_id',
            'user_id',
            'details',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'is_active'
    ];

    protected $casts = [
        'details' => 'array',
    ];
}
