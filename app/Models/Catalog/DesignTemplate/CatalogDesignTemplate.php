<?php

namespace App\Models\Catalog\DesignTemplate;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class CatalogDesignTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'catalog_design_template';

    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(CatalogDesignTemplateLayer::class, 'catalog_design_template_id', 'id');
    }

    protected static function booted()
    {
        $flush = fn () => Cache::forget('catalog_active_templates');

        static::created($flush);
        static::updated($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}
