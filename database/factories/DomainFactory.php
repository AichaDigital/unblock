<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Domain>
 */
class DomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Creates a primary domain by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'domain_name' => fake()->unique()->domainName(),
            'type' => 'primary',
        ];
    }

    /**
     * Indicate that the domain is an addon domain.
     */
    public function addon(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'addon',
        ]);
    }

    /**
     * Indicate that the domain is a subdomain.
     */
    public function subdomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'subdomain',
            'domain_name' => 'sub.' . fake()->unique()->domainName(),
        ]);
    }

    /**
     * Indicate that the domain is an alias (parked domain).
     */
    public function alias(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'alias',
        ]);
    }

    /**
     * Set a specific domain name.
     */
    public function withDomainName(string $domainName): static
    {
        return $this->state(fn (array $attributes) => [
            'domain_name' => strtolower(trim($domainName)),
        ]);
    }
}
