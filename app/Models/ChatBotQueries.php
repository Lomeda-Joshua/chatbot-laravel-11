<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatBotQueries extends Model
{   
    //  use SoftDeletes;

    protected $fillable = [
        'id',
        'group_id',
        'sequence',
        'query_name',
        'choices',
        'is_form',
        'image_url',
        'form_description',
        'form_details',
        'is_active',
        'is_submit',
        'navigation',
        'is_ticket'
    ];

    protected $table = 'chatbot_queries_2';

}
