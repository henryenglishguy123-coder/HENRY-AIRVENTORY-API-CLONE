<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_product_id',
        'image',
        'type',
        'order',
    ];

    public $timestamps = false;

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return getImageUrl($this->image);
    }
}
