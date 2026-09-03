<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesVariationProduct extends Model
{
    use HasFactory;

    protected $table = 'sales_variation_products';

    protected $fillable = [
        'sales_variation_id',
        'product_management_code',
        'quantity',
    ];

    public function variation()
    {
        return $this->belongsTo(SalesVariation::class, 'sales_variation_id', 'id');
    }

    /**
     * 商品マスタ（ProductMaster）とのリレーション
     * product_management_code をキーにして結合
     */
    public function productMaster()
    {
        return $this->belongsTo(ProductMaster::class, 'product_management_code', 'product_management_code');
    }
}