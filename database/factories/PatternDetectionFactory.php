<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PatternDetection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatternDetection>
 */
class PatternDetectionFactory extends Factory
{
    protected $model = PatternDetection::class;

    public function definition(): array
    {
        return [
            'pattern_type' => fake()->randomElement([
                PatternDetection::TYPE_DISTRIBUTED_ATTACK,
                PatternDetection::TYPE_SUBNET_SCAN,
                PatternDetection::TYPE_COORDINATED_ATTACK,
                PatternDetection::TYPE_ANOMALY,
                PatternDetection::TYPE_ANOMALY_SPIKE,
                PatternDetection::TYPE_OTHER,
            ]),
            'severity' => fake()->randomElement([
                PatternDetection::SEVERITY_LOW,
                PatternDetection::SEVERITY_MEDIUM,
                PatternDetection::SEVERITY_HIGH,
                PatternDetection::SEVERITY_CRITICAL,
            ]),
            'confidence_score' => fake()->numberBetween(0, 100),
            'email_hash' => fake()->optional()->sha256(),
            'ip_address' => fake()->optional()->ipv4(),
            'subnet' => fake()->optional()->ipv4().'/24',
            'domain' => fake()->optional()->domainName(),
            'affected_ips_count' => fake()->numberBetween(1, 100),
            'affected_emails_count' => fake()->numberBetween(0, 50),
            'time_window_minutes' => fake()->randomElement([15, 30, 60, 120, 240]),
            'detection_algorithm' => fake()->randomElement(['statistical', 'rule_based', 'ml_based', 'heuristic']),
            'detected_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'first_incident_at' => fake()->optional()->dateTimeBetween('-2 months', '-1 month'),
            'last_incident_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'pattern_data' => fake()->optional()->randomElement([
                ['threshold' => 10, 'actual' => 25],
                ['ips' => ['192.168.1.1', '192.168.1.2']],
                null,
            ]),
            'related_incidents' => fake()->optional()->randomElement([
                [1, 2, 3],
                [5, 8, 13],
                null,
            ]),
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }

    /**
     * Indicate that the pattern is resolved.
     */
    public function resolved(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
            'resolution_notes' => $notes ?? 'Pattern resolved during testing',
        ]);
    }

    /**
     * Indicate that the pattern is unresolved.
     */
    public function unresolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);
    }

    /**
     * Set a specific pattern type.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'pattern_type' => $type,
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

    /**
     * Set a specific confidence score.
     */
    public function confidence(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => $score,
        ]);
    }

    /**
     * Set detection timestamp.
     */
    public function detectedAt($timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'detected_at' => $timestamp,
        ]);
    }
}
