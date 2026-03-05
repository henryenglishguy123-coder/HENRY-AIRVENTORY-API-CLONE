<?php

namespace Database\Factories\Catalog\DesignTemplate;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogDesignTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CatalogDesignTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'status' => true,
        ];
    }
}
