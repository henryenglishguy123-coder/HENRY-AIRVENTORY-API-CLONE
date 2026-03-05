<?php

namespace App\Models\Catalog\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogAttributeOption extends Model
{
    use HasFactory;

    protected $table = 'catalog_attribute_options';

    protected $primaryKey = 'option_id';

    protected $fillable = [
        'attribute_id',
        'option_value',
        'key',
        'type',
    ];

    protected $casts = [
        'attribute_id' => 'int',
        'store_id' => 'int',
    ];

    public function parentOption()
    {
        return $this->belongsTo(self::class, 'attribute_value', 'option_id');
    }

    public function attribute()
    {
        return $this->belongsTo(CatalogAttribute::class, 'attribute_id', 'attribute_id');
    }
}
