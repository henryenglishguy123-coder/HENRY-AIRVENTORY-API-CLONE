<?php

namespace App\Models\Customer\Designer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDesignTemplateInformation extends Model
{
    protected $table = 'vendor_design_template_information';

    protected $fillable = [
        'vendor_design_template_id',
        'name',
        'description',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(VendorDesignTemplate::class, 'vendor_design_template_id');
    }
}
