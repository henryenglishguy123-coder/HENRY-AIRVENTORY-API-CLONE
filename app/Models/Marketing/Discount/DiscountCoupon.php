<?php

namespace App\Models\Marketing\Discount;

use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Vendor;
use App\Models\Factory\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCoupon extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'discount_coupons';

    protected $fillable = [
        'title',
        'code',
        'amount_type',
        'amount_value',
        'discount_type',
        'max_uses',
        'uses_count',
        'max_uses_per_customer',
        'eligibility',
        'min_qty',
        'min_price',
        'start_date',
        'end_date',
        'status',
        'used',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(
            CatalogProduct::class,
            'discount_coupon_products',
            'coupon_id',
            'product_id'
        );
    }

    public function categories()
    {
        return $this->belongsToMany(
            CatalogCategory::class,
            'discount_coupon_categories',
            'coupon_id',
            'category_id'
        );
    }

    public function suppliers()
    {
        return $this->belongsToMany(
            Factory::class,
            'discount_coupon_suppliers',
            'coupon_id',
            'supplier_id'
        );
    }

    public function customers()
    {
        return $this->belongsToMany(
            Vendor::class,
            'discount_coupon_customers',
            'coupon_id',
            'customer_id'
        );
    }

    public static function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
            $exists = self::withTrashed()->where('code', $code)->exists();
        } while ($exists);

        return $code;
    }
}
