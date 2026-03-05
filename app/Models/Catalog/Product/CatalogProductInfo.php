<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_product_id',
        'name',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
    ];

    public function product()
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
