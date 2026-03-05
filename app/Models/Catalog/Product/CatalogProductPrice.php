<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductPrice extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'catalog_product_id',
        'factory_id',
        'regular_price',
        'sale_price',
        'specific_markup',
    ];

    protected $casts = [
        'regular_price' => 'float',
        'sale_price' => 'float',
        'specific_markup' => 'float',
    ];

    /* -----------------------------------------------------------------
     |  RELATIONS
     | -----------------------------------------------------------------
     */

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            CatalogProduct::class,
            'catalog_product_id'
        );
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Factory\Factory::class,
            'factory_id'
        );
    }
}
