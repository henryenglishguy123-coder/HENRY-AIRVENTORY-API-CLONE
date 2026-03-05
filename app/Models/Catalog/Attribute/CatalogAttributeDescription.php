<?php

namespace App\Models\Catalog\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogAttributeDescription extends Model
{
    use HasFactory;

    protected $table = 'catalog_attribute_description';

    protected $fillable = [
        'attribute_id',
        'name',
    ];

    public function attribute()
    {
        return $this->belongsTo(CatalogAttribute::class, 'attribute_id', 'attribute_id');
    }
}
