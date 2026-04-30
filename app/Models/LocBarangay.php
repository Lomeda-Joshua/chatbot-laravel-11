<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocBarangay extends Model
{
    protected $table = 'loc_barangays';

    protected $fillable = [
            'brgy_code',
            'reg_id',
            'reg_region',
            'prov_id',
            'prov_desc',
            'mun_id'
    ];
}
