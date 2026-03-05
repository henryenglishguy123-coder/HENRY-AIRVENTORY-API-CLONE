<?php

namespace Database\Factories\Factory;

use App\Models\Factory\Factory as FactoryModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class FactoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FactoryModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone_number' => $this->faker->phoneNumber,
            'password' => Hash::make('password'),
            'source' => 'web',
            'account_status' => 1,
            'account_verified' => 1,
            'catalog_status' => 1,
            'email_verified_at' => now(),
        ];
    }
}
