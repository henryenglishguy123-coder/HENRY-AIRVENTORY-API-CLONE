<?php

namespace Database\Factories\Customer;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'mobile' => $this->faker->phoneNumber,
            'password' => 'password', // setPasswordAttribute handles bcrypt
            'account_status' => 1,
            'source' => 'signup',
        ];
    }
}
