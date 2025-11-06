<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AbuseIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbuseIncident>
 */
class AbuseIncidentFactory extends Factory
{
    protected $model = AbuseIncident::class;

    public function definition(): array
    {
        return [
            'incident_type' => fake()->randomElement([
                'rate_limit_exceeded',
                'ip_spoofing_attempt',
                'otp_bruteforce',
                'honeypot_triggered',
                'invalid_otp_attempts',
                'ip_mismatch',
                'suspicious_pattern',
                'other',
            ]),
            'ip_address' => fake()->ipv4(),
            'email_hash' => fake()->optional()->sha256(),
            'domain' => fake()->optional()->domainName(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => fake()->sentence(10),
            'metadata' => fake()->optional()->randomElement([
                ['user_agent' => 'Mozilla/5.0', 'attempts' => 5],
                ['port' => 22, 'protocol' => 'ssh'],
                null,
            ]),
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate that the incident is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
        ]);
    }

    /**
     * Indicate that the incident is unresolved.
     */
    public function unresolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => null,
        ]);
    }

    /**
     * Set a specific incident type.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'incident_type' => $type,
        ]);
    }

    /**
     * Set a specific severity.
     */
    public function severity(string $severity): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => $severity,
        ]);
    }
}
