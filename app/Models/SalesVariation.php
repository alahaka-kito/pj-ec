<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesVariation extends Model
{
    use HasFactory;

    protected $table = 'sales_variations';

    protected $fillable = [
        'sales_master_id',
        'variation_name',
        'shipping_size',
        'cool_delivery_service',
        'time_delivery_service',
    ];

    public function salesMaster()
    {
        return $this->belongsTo(SalesMaster::class, 'sales_master_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(SalesVariationProduct::class, 'sales_variation_id', 'id');
    }
}