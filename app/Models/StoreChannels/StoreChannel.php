<?php

namespace App\Models\StoreChannels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreChannel extends Model
{
    use HasFactory;

    protected $table = 'store_channels';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'code',
        'name',
        'logo',
        'description',
        'auth_type',
        'required_credentials',
        'is_active',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'is_active' => 'boolean',
        'required_credentials' => 'array',
    ];

    /**
     * Scope: only active channels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Helper: check auth type
     */
    public function isOAuth(): bool
    {
        return $this->auth_type === 'oauth';
    }

    public function isApiKey(): bool
    {
        return $this->auth_type === 'api_key';
    }

    /**
     * Helper: frontend logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $clean = trim(str_replace('`', '', $this->logo));

        if (filter_var($clean, FILTER_VALIDATE_URL)) {
            return $clean;
        }

        return getImageUrl($clean);
    }

    /**
     * Helper: required credentials list
     */
    public function getCredentialFields(): array
    {
        return $this->required_credentials ?? [];
    }

    public function toApiArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'logo' => $this->logo_url,
            'description' => $this->description,
        ];
    }
}
