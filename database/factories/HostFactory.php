<?php

namespace Database\Factories;

use App\Models\Host;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class HostFactory extends Factory
{
    protected $model = Host::class;

    public function definition(): array
    {
        return [
            'fqdn' => $this->faker->unique()->domainName(),
            'alias' => $this->faker->unique()->word(),
            'ip' => $this->faker->ipv4(),
            'port_ssh' => $this->faker->numberBetween(22, 2222),
            'hash' => Crypt::encryptString($this->faker->password()),
            'panel' => $this->faker->randomElement(['cpanel', 'directadmin']),
            'admin' => 'admin',
            'whmcs_server_id' => null,
            'hash_public' => null,
            'hosting_manual' => false,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Create a host with a specific WHMCS server ID (for shared hosting)
     */
    public function withWhmcsServerId(int $serverId): static
    {
        return $this->state(fn (array $attributes) => [
            'whmcs_server_id' => $serverId,
        ]);
    }

    /**
     * Create a VPS host (no WHMCS server ID)
     */
    public function vps(): static
    {
        return $this->state(fn (array $attributes) => [
            'whmcs_server_id' => null,
        ]);
    }
}
