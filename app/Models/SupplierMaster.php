<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierMaster extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'supplier_master';
    protected $primaryKey = 'seq';

    protected $fillable = [
        'management_code',
        'hatsu_ninushi_code',
        'company_name',
        'manager',
        'manager_telephone_number',
        'selling_places',
        'post_code',
        'main_address',
        'building_name',
        'bank_name',
        'bank_code',
        'branch_name',
        'branch_code',
        'account_type',
        'account_number',
        'account_holder_name',
        'pickup_location_post_code',
        'pickup_location_main_address',
        'pickup_location_building_name',
        'business_days',
        'payment_date',
        'payment_closing_date',
    ];

    // DBのJSONデータが自動的にPHPの「配列」に変換されます
    protected $casts = [
        'selling_places' => 'array',
    ];
}