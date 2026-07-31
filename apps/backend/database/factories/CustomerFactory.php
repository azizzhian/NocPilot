<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Odc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $statuses = ['active', 'active', 'active', 'inactive', 'suspended'];

        return [
            'customer_code' => 'PLG-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'pppoe' => strtolower(fake()->userName()).'@net',
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'odc_id' => Odc::query()->inRandomOrder()->value('id'),
            'package' => fake()->randomElement(['20 Mbps', '30 Mbps', '50 Mbps', '100 Mbps']),
            'status' => fake()->randomElement($statuses),
            'activated_at' => fake()->dateTimeBetween('-2 years'),
        ];
    }
}
