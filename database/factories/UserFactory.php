<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'parent_user_id' => null,
            'whmcs_client_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }



    /**
     * Create a user with a specific WHMCS client ID (main user)
     */
    public function withWhmcsClientId(int $clientId): static
    {
        return $this->state(fn (array $attributes) => [
            'whmcs_client_id' => $clientId,
        ]);
    }

    /**
     * Create an authorized user (delegated user)
     */
    public function authorizedUser(int $parentUserId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_user_id' => $parentUserId,
            'whmcs_client_id' => null,
        ]);
    }
}
