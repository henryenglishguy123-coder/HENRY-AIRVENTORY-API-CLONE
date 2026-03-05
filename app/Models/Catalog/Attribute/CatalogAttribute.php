<?php

namespace App\Models\Catalog\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogAttribute extends Model
{
    use HasFactory;

    protected $table = 'catalog_attributes';

    protected $primaryKey = 'attribute_id';

    protected $fillable = [
        'attribute_code',
        'field_type',
        'is_global',
        'status',
        'use_for_filter',
        'use_for_variation',
        'added_by',
        'is_required',
        'catalog_industry_id',
    ];

    protected $casts = [
        'status' => 'int',
        'is_global' => 'int',
        'use_for_filter' => 'int',
        'use_for_variation' => 'int',
        'is_required' => 'int',
    ];

    public function options()
    {
        return $this->hasMany(CatalogAttributeOption::class, 'attribute_id', 'attribute_id')->orderBy('key', 'desc');
    }

    public function description()
    {
        return $this->hasOne(CatalogAttributeDescription::class, 'attribute_id', 'attribute_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
