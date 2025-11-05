<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\{BfmWhitelistEntry, Host};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BfmWhitelistEntry>
 */
class BfmWhitelistEntryFactory extends Factory
{
    protected $model = BfmWhitelistEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_id' => Host::factory(),
            'ip_address' => fake()->ipv4(),
            'added_at' => now(),
            'expires_at' => now()->addHours(24),
            'removed' => false,
            'removed_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the entry is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'added_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the entry has been removed.
     */
    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'removed' => true,
            'removed_at' => now(),
        ]);
    }
}

