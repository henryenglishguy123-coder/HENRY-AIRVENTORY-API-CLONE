<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductInventory extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_inventory';

    protected $primaryKey = 'id';

    protected $fillable = [
        'product_id',
        'factory_id',
        'manage_inventory',
        'quantity',
        'min_quantity',
        'quantity',
        'stock_status',
    ];

    protected $casts = [
        'stock_status' => 'integer',
        'manage_inventory' => 'integer',
    ];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Factory\Factory::class,
            'factory_id'
        );
    }
}
