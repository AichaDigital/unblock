<?php

declare(strict_types=1);

namespace App\Console\Commands\GeoIP;

use App\Services\GeoIPService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * GeoIP Status Command
 *
 * Displays current status of GeoIP database and configuration
 */
class StatusCommand extends Command
{
    protected $signature = 'geoip:status';

    protected $description = 'Show GeoIP database status and configuration';

    public function handle(GeoIPService $geoIpService): int
    {
        $this->info('🌍 GeoIP Status');
        $this->newLine();

        // Configuration
        $this->line('<fg=cyan>Configuration:</>');
        $this->displayConfiguration();
        $this->newLine();

        // Database Status
        $this->line('<fg=cyan>Database Status:</>');
        $this->displayDatabaseStatus($geoIpService);
        $this->newLine();

        // Service Status
        $this->line('<fg=cyan>Service Status:</>');
        $this->displayServiceStatus($geoIpService);

        return self::SUCCESS;
    }

    private function displayConfiguration(): void
    {
        $accountId = config('services.maxmind.account_id');
        $licenseKey = config('services.maxmind.license_key');
        $dbPath = config('services.maxmind.database_path');
        $enabled = config('services.maxmind.enabled');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Account ID', $accountId ? '✓ Set ('.substr($accountId, 0, 6).'...)' : '✗ Not set'],
                ['License Key', $licenseKey ? '✓ Set ('.substr($licenseKey, 0, 6).'...)' : '✗ Not set'],
                ['Database Path', $dbPath],
                ['Enabled', $enabled ? '✓ Yes' : '✗ No'],
            ]
        );
    }

    private function displayDatabaseStatus(GeoIPService $geoIpService): void
    {
        $dbPath = config('services.maxmind.database_path');
        $exists = file_exists($dbPath);

        $rows = [
            ['Exists', $exists ? '✓ Yes' : '✗ No'],
        ];

        if ($exists) {
            $size = File::size($dbPath);
            $sizeFormatted = $this->formatBytes($size);
            $modified = Carbon::createFromTimestamp(File::lastModified($dbPath))->format('Y-m-d H:i:s');
            $age = now()->diffInDays(now()->setTimestamp(filemtime($dbPath)));

            $rows[] = ['Size', $sizeFormatted];
            $rows[] = ['Last Modified', $modified];
            $rows[] = ['Age', "{$age} days"];
            $rows[] = ['Update Needed', $age >= 7 ? '⚠ Yes (>7 days)' : '✓ No'];
        }

        $this->table(['Property', 'Value'], $rows);
    }

    private function displayServiceStatus(GeoIPService $geoIpService): void
    {
        $info = $geoIpService->getDatabaseInfo();
        $available = $geoIpService->isAvailable();

        $rows = [
            ['Available', $available ? '✓ Yes' : '✗ No'],
        ];

        if ($available) {
            $buildDate = Carbon::createFromTimestamp($info['build_time'])->format('Y-m-d');
            $rows[] = ['Database Type', $info['database_type']];
            $rows[] = ['Build Date', $buildDate];

            // Test lookup
            $testIp = '8.8.8.8';
            $testResult = $geoIpService->lookup($testIp);
            $testStatus = $testResult ? '✓ Success ('.$testResult['country_code'].')' : '✗ Failed';
            $rows[] = ['Test Lookup (8.8.8.8)', $testStatus];
        } else {
            $rows[] = ['Reason', $info['exists'] ? 'Failed to initialize reader' : 'Database file not found'];
        }

        $this->table(['Property', 'Value'], $rows);

        if (! $available) {
            $this->newLine();
            $this->warn('💡 Run "php artisan geoip:update" to download the database');
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
