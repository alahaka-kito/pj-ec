<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_master';
    protected $primaryKey = 'seq';

    protected $fillable = [
        'image_path',
        'management_code',
        'product_management_code',
        'supplier_product_name',
        'buying_price',
        'size',
        'cool_delivery_service',
        'time_delivery_service',
        'drive_path',
        'selling_places',
    ];

    protected $casts = [
        'selling_places'        => 'array',
        'size'                  => 'integer',
        'cool_delivery_service' => 'integer',
        'time_delivery_service' => 'integer',
    ];

    /**
     * 紐づく仕入先情報を取得
     */
    public function supplier()
    {
        return $this->belongsTo(SupplierMaster::class, 'management_code', 'management_code');
    }
}