<?php

namespace App\Models\Customer\Designer;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use App\Models\PrintingTechnique\PrintingTechnique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorDesignLayer extends Model
{
    use HasFactory;

    protected $table = 'vendor_design_layers';

    protected $fillable = [
        'vendor_design_template_id',
        'catalog_design_template_layer_id',
        'technique_id',
        'type',
        'image_path',
        'scale_x',
        'scale_y',
        'width',
        'height',
        'rotation_angle',
        'position_top',
        'position_left',
    ];

    protected $casts = [
        // 🔥 keep precision – do NOT cast to float
        'scale_x' => 'string',
        'scale_y' => 'string',
        'width' => 'string',
        'height' => 'string',
        'rotation_angle' => 'string',
        'position_top' => 'string',
        'position_left' => 'string',
        'canvas_json' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function vendorDesignTemplate()
    {
        return $this->belongsTo(
            VendorDesignTemplate::class,
            'vendor_design_template_id'
        );
    }

    public function technique()
    {
        return $this->belongsTo(
            PrintingTechnique::class,
            'technique_id'
        );
    }

    public function catalogTemplateLayer()
    {
        return $this->belongsTo(
            CatalogDesignTemplateLayer::class,
            'catalog_design_template_layer_id'
        );
    }
}
