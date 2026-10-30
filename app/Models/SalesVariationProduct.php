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
        'product_master_seq',
        'quantity',
        'variation_stock',
    ];

    public function variation()
    {
        return $this->belongsTo(SalesVariation::class, 'sales_variation_id', 'id');
    }

    /**
     * 商品マスタ（ProductMaster）とのリレーション
     */
    public function productMaster()
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_seq', 'seq');
    }
}
