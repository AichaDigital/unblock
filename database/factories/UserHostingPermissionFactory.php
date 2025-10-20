<?php

namespace Database\Factories;

use App\Models\{User, Hosting, UserHostingPermission};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserHostingPermission>
 */
class UserHostingPermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hosting_id' => Hosting::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Create inactive permission.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create permission for specific user and hosting.
     */
    public function forUserAndHosting(int $userId, int $hostingId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'hosting_id' => $hostingId,
        ]);
    }
}
