<?php

namespace Database\Factories;

use App\Models\{Host, Hosting, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class HostingFactory extends Factory
{
    protected $model = Hosting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'host_id' => Host::factory(),
            'domain' => $this->faker->unique()->domainName(),
            'username' => $this->faker->userName(),
            'hosting_manual' => false,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Create a manual hosting that won't be synchronized with WHMCS
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'hosting_manual' => true,
        ]);
    }
}
