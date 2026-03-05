<?php

namespace App\Models\Customer\Cart;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Branding\VendorDesignBranding;
use App\Models\Customer\Designer\VendorDesignLayerImage;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Factory\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'template_id',
        'packaging_label_id',
        'hang_tag_id',
        'fulfillment_factory_id',
        'sku',
        'product_title',
        'qty',
        'unit_price',
        'line_total',
        'tax_rate',
        'tax_amount',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'variant_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CartItemOption::class);
    }

    public function fulfillmentFactory()
    {
        return $this->belongsTo(Factory::class, 'fulfillment_factory_id');
    }

    public function designImages(): HasMany
    {
        return $this->hasMany(
            VendorDesignLayerImage::class,
            'template_id',
            'template_id'
        );
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VendorDesignTemplate::class, 'template_id');
    }

    public function packagingLabel(): BelongsTo
    {
        return $this->belongsTo(VendorDesignBranding::class, 'packaging_label_id');
    }

    public function hangTag(): BelongsTo
    {
        return $this->belongsTo(VendorDesignBranding::class, 'hang_tag_id');
    }
}
