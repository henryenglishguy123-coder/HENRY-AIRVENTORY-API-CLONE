<?php

namespace App\Models\Catalog\Products;

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
}
