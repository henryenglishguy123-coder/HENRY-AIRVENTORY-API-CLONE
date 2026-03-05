<?php

namespace App\Models\Factory;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Factory extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'factory_users';

    protected $fillable = [
        'email',
        'phone_number',
        'first_name',
        'last_name',
        'password',
        'source',
        'account_status',
        'account_verified',
        'remember_token',

        'google_id',
        'stripe_account_id',
        'email_verification_code',
        'email_verification_code_expires_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'account_verified' => AccountVerificationStatus::class,
        'account_status' => AccountStatus::class,
        'email_verified_at' => 'datetime',
        'email_verification_code_expires_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    /**
     * Get the combined name of the factory user.
     */
    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Auto-hash password on save
     */
    public function setPasswordAttribute($value)
    {
        if ($value && strlen($value) > 0) {
            $this->attributes['password'] = \Illuminate\Support\Facades\Hash::make($value);
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
            'role' => 'factory',
            'user_type' => 'factory',
        ];
    }

    public function scopeVerified($query)
    {
        return $query->where('account_verified', AccountVerificationStatus::VERIFIED)
            ->where('account_status', AccountStatus::ENABLED)
            ->whereNotNull('email_verified_at');
    }

    /**
     * Check if the factory profile is complete.
     */
    public function isComplete(): bool
    {
        return empty($this->getMissingFields());
    }

    /**
     * Get missing fields for factory profile completion.
     */
    public function getMissingFields(): array
    {
        $missing = [];

        // Basic Info
        if (!$this->first_name) $missing[] = 'basic_info.first_name';
        if (!$this->last_name) $missing[] = 'basic_info.last_name';
        if (!$this->email) $missing[] = 'basic_info.email';
        if (!$this->phone_number) $missing[] = 'basic_info.phone_number';
        if (!$this->email_verified_at) $missing[] = 'basic_info.email_verified';

        // Business Info
        if (!$this->business) {
            $missing[] = 'business_info.not_provided';
        } else {
            $b = $this->business;
            if (!$b->company_name) $missing[] = 'business_info.company_name';
            if (!$b->registration_number) $missing[] = 'business_info.registration_number';
            if (!$b->tax_vat_number) $missing[] = 'business_info.tax_vat_number';
            if (!$b->registered_address) $missing[] = 'business_info.registered_address';
            if (!$b->country_id) $missing[] = 'business_info.country_id';
            if (!$b->state_id) $missing[] = 'business_info.state_id';
            if (!$b->city) $missing[] = 'business_info.city';
            if (!$b->postal_code) $missing[] = 'business_info.postal_code';
        }

        // Industries
        if ($this->relationLoaded('industries')) {
            if ($this->industries->isEmpty()) {
                $missing[] = 'industries.not_assigned';
            }
        } else {
            if (!$this->industries()->exists()) {
                $missing[] = 'industries.not_assigned';
            }
        }

        // Location - at least one facility or distribution address
        if ($this->relationLoaded('addresses')) {
            if ($this->addresses->whereIn('type', ['facility', 'dist'])->isEmpty()) {
                $missing[] = 'location.not_provided';
            }
        } else {
            if (!$this->addresses()->whereIn('type', ['facility', 'dist'])->exists()) {
                $missing[] = 'location.not_provided';
            }
        }

        return $missing;
    }

    /**
     * Check if the factory can be verified.
     */
    public function canBeVerified(): bool
    {
        return $this->isComplete();
    }

    /**
     * Get metadata for the current account status.
     */
    public function getAccountStatusMetadataAttribute(): ?array
    {
        return $this->account_status?->getMetadata();
    }

    /**
     * Get metadata for the current verification status.
     */
    public function getVerificationStatusMetadataAttribute(): ?array
    {
        return $this->account_verified?->getMetadata();
    }

    public function business()
    {
        return $this->hasOne(FactoryBusiness::class);
    }

    public function addresses()
    {
        return $this->hasMany(FactoryAddress::class, 'factory_id');
    }

    /**
     * Get the industries associated with the factory.
     */
    public function industries()
    {
        return $this->belongsToMany(
            \App\Models\Catalog\Industry\CatalogIndustry::class,
            'factory_industries',
            'factory_id',
            'catalog_industry_id'
        );
    }

    /**
     * Get the factory industry relationships.
     */
    public function factoryIndustries()
    {
        return $this->hasMany(FactoryIndustry::class, 'factory_id');
    }

    public function packagingLabel()
    {
        return $this->hasOne(PackagingLabel::class, 'factory_id');
    }

    public function hangTag()
    {
        return $this->hasOne(HangTag::class, 'factory_id');
    }

    public function metas()
    {
        return $this->hasMany(FactoryMetas::class);
    }

    public function shippingPartners()
    {
        return $this->belongsToMany(
            \App\Models\Shipping\ShippingPartner::class,
            'factory_shipping_partners',
            'factory_id',
            'shipping_partner_id'
        )->withTimestamps()->withPivot('is_active');
    }

    public function getMetasArrayAttribute()
    {
        return $this->metas()->pluck('value', 'key')->toArray();
    }

    /**
     * Get a meta value for this factory
     */
    public function metaValue(string $key, $default = null)
    {
        $meta = $this->metas()->where('key', $key)->first();

        return $meta ? $meta->value : $default;
    }

    /**
     * Set a meta value for this factory
     */
    public function setMetaValue(string $key, $value): void
    {
        $this->metas()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    protected static function booted()
    {
        static::creating(function ($factory) {
            //
        });
        static::created(function ($factory) {
            //
        });
    }
}
