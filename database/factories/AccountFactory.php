<?php

namespace Database\Factories;

use App\Models\Host;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Creates an active account with random data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_id' => Host::factory(),
            'user_id' => null, // Nullable by default, use withUser() state to set
            'username' => fake()->userName(),
            'domain' => fake()->unique()->domainName(),
            'owner' => fake()->name(),
            'suspended_at' => null,
            'deleted_at' => null,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Indicate that the account is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }

    /**
     * Indicate that the account is deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }

    /**
     * Indicate that the account has a user (WHMCS integration).
     */
    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the account was recently synced.
     */
    public function recentlySynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the account has not been synced recently.
     */
    public function staleSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }
}
