<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductParent extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_product_id',
        'parent_id',
    ];

    public $timestamps = false;

    public function parent()
    {
        return $this->belongsTo(CatalogProduct::class, 'parent_id');
    }

    public function child()
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
