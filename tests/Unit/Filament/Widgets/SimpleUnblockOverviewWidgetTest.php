<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\SimpleUnblockOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleUnblockOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_otp_success_rate_returns_zero_when_no_stats(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $widget = new SimpleUnblockOverviewWidget;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getOtpSuccessRate');
        $method->setAccessible(true);

        // Should return 0.0 when no OTP codes exist
        $result = $method->invoke($widget);

        $this->assertSame(0.0, $result);
    }

    public function test_get_otp_success_chart_returns_zeros_when_no_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $widget = new SimpleUnblockOverviewWidget;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getOtpSuccessChart');
        $method->setAccessible(true);

        $result = $method->invoke($widget);

        // Should return array of 7 zeros (last 7 days)
        $this->assertIsArray($result);
        $this->assertCount(7, $result);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $result);
    }

    public function test_widget_does_not_throw_division_by_zero_error(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        // This test verifies the fix for DivisionByZeroError
        $widget = new SimpleUnblockOverviewWidget;

        // Attempt to get stats - should not throw any error
        try {
            $reflection = new \ReflectionClass($widget);
            $method = $reflection->getMethod('getStats');
            $method->setAccessible(true);

            $stats = $method->invoke($widget);

            $this->assertIsArray($stats);
            $this->assertNotEmpty($stats);
        } catch (\DivisionByZeroError $e) {
            $this->fail('DivisionByZeroError should not be thrown: '.$e->getMessage());
        }
    }
}
