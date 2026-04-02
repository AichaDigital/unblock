<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Report;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check Recent Block History Action
 *
 * Queries the database for recent reports where the IP was actually blocked
 * on the same host. This provides context when a current check finds no blocks,
 * helping users understand timing gaps (IP checked between temporary blocks).
 */
class CheckRecentBlockHistoryAction
{
    use AsAction;

    /**
     * @param  string  $ip  IP address to check history for
     * @param  int  $hostId  Host ID to scope the search
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array{count: int, last_blocked_at: ?string, dates: array<string>}
     */
    public function handle(string $ip, int $hostId, int $days = 7): array
    {
        $recentBlocks = Report::where('ip', $ip)
            ->where('host_id', $hostId)
            ->whereJsonContains('analysis->was_blocked', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'created_at']);

        return [
            'count' => $recentBlocks->count(),
            'last_blocked_at' => $recentBlocks->first()?->created_at?->toISOString(),
            'dates' => $recentBlocks->pluck('created_at')
                ->map(fn ($d) => $d->format('d/m/Y H:i'))
                ->toArray(),
        ];
    }
}
