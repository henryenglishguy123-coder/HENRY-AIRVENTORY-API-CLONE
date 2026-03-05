<?php

namespace App\Models\Catalog\Product;

use App\Models\Catalog\Category\CatalogCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_product_id',
        'catalog_category_id',
    ];

    public $timestamps = false;

    public function category()
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    public function product()
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
