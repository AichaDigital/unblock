<?php

namespace App\Filament\Widgets;

use App\Models\PatternDetection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatternDetectionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $total = PatternDetection::count();
        $active = PatternDetection::unresolved()->count();
        $resolved = PatternDetection::resolved()->count();
        $critical = PatternDetection::where('severity', PatternDetection::SEVERITY_CRITICAL)
            ->unresolved()
            ->count();

        $last7Days = PatternDetection::where('detected_at', '>=', now()->subDays(7))->count();
        $previous7Days = PatternDetection::whereBetween('detected_at', [
            now()->subDays(14),
            now()->subDays(7),
        ])->count();

        $trend = $previous7Days > 0
            ? (($last7Days - $previous7Days) / $previous7Days) * 100
            : 0;

        return [
            Stat::make('Active Patterns', $active)
                ->description('Unresolved detections')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($active > 0 ? 'warning' : 'success'),

            Stat::make('Critical Patterns', $critical)
                ->description('Requires immediate attention')
                ->descriptionIcon('heroicon-o-shield-exclamation')
                ->color($critical > 0 ? 'danger' : 'success'),

            Stat::make('Last 7 Days', $last7Days)
                ->description($trend >= 0 ? "{$trend}% increase" : "{$trend}% decrease")
                ->descriptionIcon($trend >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($trend >= 0 ? 'danger' : 'success'),

            Stat::make('Total Resolved', $resolved)
                ->description('All-time resolved patterns')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
