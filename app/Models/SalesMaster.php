<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesMaster extends Model
{
    use HasFactory;

    protected $table = 'sales_masters';

    protected $fillable = [
        'product_name',
        'amazon_url',
        'tiktok_url',
        'drive_path',
    ];

    public function variations()
    {
        return $this->hasMany(SalesVariation::class, 'sales_master_id', 'id');
    }
}