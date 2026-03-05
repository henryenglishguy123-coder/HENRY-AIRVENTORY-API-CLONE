<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductSuperAttribute extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_super_attributes';

    protected $primaryKey = 'id';

    protected $fillable = [
        'product_id',
        'attribute_id',
    ];
}
