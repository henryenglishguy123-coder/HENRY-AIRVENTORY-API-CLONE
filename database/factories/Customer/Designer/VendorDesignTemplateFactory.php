<?php

namespace Database\Factories\Customer\Designer;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorDesignTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VendorDesignTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'vendor_id' => Vendor::factory(),
            'catalog_design_template_id' => CatalogDesignTemplate::factory(),
        ];
    }
}
