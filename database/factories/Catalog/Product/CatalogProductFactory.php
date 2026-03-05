<?php

namespace Database\Factories\Catalog\Product;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CatalogProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type' => 'simple',
            'slug' => $this->faker->slug,
            'sku' => $this->faker->unique()->ean8,
            'status' => 1,
            'weight' => $this->faker->randomFloat(2, 0.1, 10),
        ];
    }
}
