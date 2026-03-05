<?php

namespace App\Models\Catalog\Product;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use App\Models\Factory\Factory;
use App\Models\PrintingTechnique\PrintingTechnique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogProductPrintingPrice extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_printing_price';

    protected $fillable = [
        'catalog_product_id',
        'catalog_product_design_template_id',
        'layer_id',
        'factory_id',
        'printing_technique_id',
        'price',
    ];

    public function product()
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function designTemplateAssignment()
    {
        return $this->belongsTo(CatalogProductDesignTemplate::class, 'catalog_product_design_template_id');
    }

    public function layer()
    {
        return $this->belongsTo(CatalogDesignTemplateLayer::class, 'layer_id');
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function printingTechnique()
    {
        return $this->belongsTo(PrintingTechnique::class, 'printing_technique_id');
    }
}
