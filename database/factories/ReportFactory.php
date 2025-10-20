<?php

namespace Database\Factories;

use App\Models\{Host, Report, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'logs' => [
                'csf' => [
                    $this->faker->sentence(),
                    $this->faker->sentence(),
                ],
                'exim' => [
                    $this->faker->sentence(),
                    $this->faker->sentence(),
                ],
            ],
            'analysis' => [
                'details' => $this->faker->paragraph(),
                'was_blocked' => $this->faker->boolean(),
            ],
            'ip' => $this->faker->ipv4(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'user_id' => User::factory(),
            'host_id' => Host::factory(),
        ];
    }
}
