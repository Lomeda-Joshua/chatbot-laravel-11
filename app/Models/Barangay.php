<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    
    protected $table = 'loc_barangays';

    protected $fillable = [
            'brgy_code',
            'reg_id',
            'reg_region',
            'prov_id',
            'prov_desc',
            'mun_id',
            'mun_desc',
            'brgy_name',
            'brgy_description',
    ];

}
