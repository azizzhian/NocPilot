<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => Ticket::generateNumber(),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'customer_name' => fake()->name(),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'status' => fake()->randomElement(['open', 'assigned', 'progress', 'solved', 'closed']),
            'area' => fake()->city(),
            'sla_deadline' => now()->addHours(rand(1, 24)),
        ];
    }
}
