<?php

namespace App\Models\Customer;

use App\Models\Customer\Payment\VendorWallet;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Vendor extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'vendors';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'mobile',
        'password',
        'last_login',
        'source',
        'account_status',
        'social_login_id',
        'gateway_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'account_status' => 'integer',
    ];

    /**
     * Auto-hash password on save
     */
    public function setPasswordAttribute($value)
    {
        if ($value && strlen($value) > 0) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    /**
     * JWT Identifier
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT Custom Claims
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => 'vendor',
            'user_type' => 'vendor',
        ];
    }

    // -------------------------
    // RELATIONSHIPS
    // -------------------------

    public function meta()
    {
        return $this->hasOne(VendorMeta::class, 'vendor_id');
    }

    public function wallet()
    {
        return $this->hasOne(VendorWallet::class, 'vendor_id');
    }

    public function cart()
    {
        return $this->hasOne(VendorUserCart::class, 'vendor_id')->where('status', 1);
    }

    public function stores()
    {
        return $this->hasMany(VendorConnectedStore::class, 'vendor_id');
    }

    public function orders()
    {
        return $this->hasMany(\App\Models\Sales\Order\SalesOrder::class, 'customer_id');
    }

    // -------------------------
    // META HANDLERS
    // -------------------------

    public function metaValue($key, $default = null)
    {
        return $this->meta()
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public function setMetaValue($key, $value)
    {
        return DB::table('vendor_metas')->updateOrInsert(
            ['vendor_id' => $this->id, 'key' => $key],
            ['value' => (string) $value]
        );
    }

    // -------------------------
    // EVENTS
    // -------------------------

    protected static function booted()
    {
        static::created(function ($vendor) {
            VendorWallet::firstOrCreate(
                ['vendor_id' => $vendor->id],
                ['balance' => 0.0000]
            );
        });
    }
}
