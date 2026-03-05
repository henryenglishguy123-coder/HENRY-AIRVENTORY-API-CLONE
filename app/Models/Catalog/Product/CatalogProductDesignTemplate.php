<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductDesignTemplate extends Model
{
    protected $table = 'catalog_product_design_templates';

    protected $fillable = [
        'catalog_product_id',
        'catalog_design_template_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function catalogDesignTemplate(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Catalog\DesignTemplate\CatalogDesignTemplate::class,
            'catalog_design_template_id'
        );
    }
}
