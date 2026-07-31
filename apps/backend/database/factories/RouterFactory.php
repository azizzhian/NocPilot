<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    protected $model = Router::class;

    public function definition(): array
    {
        return [
            'name' => 'RT-'.fake()->bothify('POP-##'),
            'ip' => fake()->localIpv4(),
            'pop' => 'POP '.fake()->city(),
            'area' => fake()->city(),
            'status' => 'online',
            'cpu' => rand(20, 80),
            'memory' => rand(30, 85),
            'temperature' => rand(34, 50),
            'clients' => rand(50, 500),
            'pppoe_sessions' => rand(30, 300),
            'last_synced_at' => now(),
        ];
    }
}
