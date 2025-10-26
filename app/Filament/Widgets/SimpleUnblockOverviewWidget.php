<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\{AbuseIncident, EmailReputation, IpReputation};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SimpleUnblockOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            // Total Requests (Today)
            Stat::make('Requests Today', $this->getRequestsToday())
                ->description($this->getRequestsTrend())
                ->descriptionIcon($this->getRequestsTrendIcon())
                ->color($this->getRequestsTrendColor())
                ->chart($this->getRequestsChart()),

            // Average IP Reputation
            Stat::make('Avg IP Reputation', $this->getAverageIpReputation().'/100')
                ->description('Across all tracked IPs')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($this->getIpReputationColor()),

            // Average Email Reputation
            Stat::make('Avg Email Reputation', $this->getAverageEmailReputation().'/100')
                ->description('Across all tracked emails')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($this->getEmailReputationColor()),

            // Critical/High Incidents
            Stat::make('Active Incidents', $this->getActiveIncidentsCount())
                ->description($this->getActiveIncidentsDescription())
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($this->getActiveIncidentsColor())
                ->url(route('filament.admin.resources.abuse-incidents.index', [
                    'tableFilters[resolved][value]' => 'false',
                ])),

            // OTP Verification Rate
            Stat::make('OTP Success Rate', $this->getOtpSuccessRate().'%')
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($this->getOtpSuccessColor())
                ->chart($this->getOtpSuccessChart()),

            // Blocked Attempts
            Stat::make('Blocked Today', $this->getBlockedToday())
                ->description('Prevented malicious attempts')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger'),
        ];
    }

    /**
     * Get total requests today across all IPs
     */
    private function getRequestsToday(): int
    {
        return IpReputation::query()
            ->where('last_seen_at', '>=', now()->startOfDay())
            ->sum('total_requests');
    }

    /**
     * Get requests trend (compare to yesterday)
     */
    private function getRequestsTrend(): string
    {
        $today = $this->getRequestsToday();
        $yesterday = IpReputation::query()
            ->whereBetween('last_seen_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('total_requests');

        if ($yesterday === 0) {
            return 'First day of tracking';
        }

        $percentChange = round((($today - $yesterday) / $yesterday) * 100, 1);
        $direction = $percentChange > 0 ? 'increase' : 'decrease';

        return abs($percentChange).'% '.$direction.' from yesterday';
    }

    /**
     * Get trend icon
     */
    private function getRequestsTrendIcon(): string
    {
        $today = $this->getRequestsToday();
        $yesterday = IpReputation::query()
            ->whereBetween('last_seen_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('total_requests');

        return $today > $yesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    /**
     * Get trend color
     */
    private function getRequestsTrendColor(): string
    {
        $today = $this->getRequestsToday();
        $yesterday = IpReputation::query()
            ->whereBetween('last_seen_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('total_requests');

        return $today > $yesterday ? 'success' : 'danger';
    }

    /**
     * Get requests chart (last 7 days)
     */
    private function getRequestsChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = IpReputation::query()
                ->whereDate('last_seen_at', $date)
                ->sum('total_requests');
            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get average IP reputation score
     */
    private function getAverageIpReputation(): int
    {
        return (int) IpReputation::query()->avg('reputation_score');
    }

    /**
     * Get IP reputation color
     */
    private function getIpReputationColor(): string
    {
        $avg = $this->getAverageIpReputation();

        return match (true) {
            $avg >= 80 => 'success',
            $avg >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Get average email reputation score
     */
    private function getAverageEmailReputation(): int
    {
        return (int) EmailReputation::query()->avg('reputation_score');
    }

    /**
     * Get email reputation color
     */
    private function getEmailReputationColor(): string
    {
        $avg = $this->getAverageEmailReputation();

        return match (true) {
            $avg >= 80 => 'success',
            $avg >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Get count of active critical/high incidents
     */
    private function getActiveIncidentsCount(): int
    {
        return AbuseIncident::unresolved()
            ->whereIn('severity', ['critical', 'high'])
            ->count();
    }

    /**
     * Get active incidents description
     */
    private function getActiveIncidentsDescription(): string
    {
        $critical = AbuseIncident::unresolved()->where('severity', 'critical')->count();
        $high = AbuseIncident::unresolved()->where('severity', 'high')->count();

        if ($critical > 0 && $high > 0) {
            return "{$critical} critical, {$high} high severity";
        }

        if ($critical > 0) {
            return "{$critical} critical incidents";
        }

        if ($high > 0) {
            return "{$high} high severity incidents";
        }

        return 'No active incidents';
    }

    /**
     * Get active incidents color
     */
    private function getActiveIncidentsColor(): string
    {
        $critical = AbuseIncident::unresolved()->where('severity', 'critical')->count();

        return $critical > 0 ? 'danger' : 'warning';
    }

    /**
     * Get OTP success rate (last 7 days)
     */
    private function getOtpSuccessRate(): float
    {
        $stats = EmailReputation::query()
            ->where('last_seen_at', '>=', now()->subDays(7))
            ->selectRaw('SUM(total_requests) as total, SUM(verified_requests) as verified')
            ->first();

        if (! $stats || $stats->total === 0) {
            return 0.0;
        }

        return round(($stats->verified / $stats->total) * 100, 1);
    }

    /**
     * Get OTP success color
     */
    private function getOtpSuccessColor(): string
    {
        $rate = $this->getOtpSuccessRate();

        return match (true) {
            $rate >= 70 => 'success',
            $rate >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Get OTP success chart (last 7 days)
     */
    private function getOtpSuccessChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $stats = EmailReputation::query()
                ->whereDate('last_seen_at', $date)
                ->selectRaw('SUM(total_requests) as total, SUM(verified_requests) as verified')
                ->first();

            if ($stats && $stats->total > 0) {
                $rate = ($stats->verified / $stats->total) * 100;
                $data[] = (int) $rate;
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }

    /**
     * Get blocked attempts today
     */
    private function getBlockedToday(): int
    {
        return IpReputation::query()
            ->where('last_seen_at', '>=', now()->startOfDay())
            ->sum('blocked_count');
    }
}
