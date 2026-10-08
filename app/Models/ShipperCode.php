<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipperCode extends Model
{
    protected $table = 'hatsu_ninushi_data';
    protected $primaryKey = 'seq';

    protected $fillable = [
        'hatsu_ninushi_code',
        'product_name',
    ];
}
