<?php

namespace Database\Factories;

use App\Initiative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Initiative>
 */
class InitiativeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'subtitle' => fake()->sentence(12),
            'url' => null,
            'featured_on_home' => false,
            'order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['featured_on_home' => true]);
    }

    public function withUrl(string $url = 'https://example.com'): static
    {
        return $this->state(fn (): array => ['url' => $url]);
    }
}
