<?php

declare(strict_types=1);

namespace App\Console\Commands\GeoIP;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{File, Http, Log};

/**
 * Update GeoIP Database Command
 *
 * Downloads and updates MaxMind GeoLite2-City database
 * Scheduled to run weekly to keep geographic data current
 */
class UpdateDatabaseCommand extends Command
{
    protected $signature = 'geoip:update
                            {--force : Force download even if database is recent}';

    protected $description = 'Download and update MaxMind GeoLite2-City database';

    private const MAXMIND_URL = 'https://download.maxmind.com/app/geoip_download';

    private const EDITION_ID = 'GeoLite2-City';

    private const MAX_DB_AGE_DAYS = 7; // Download if older than 7 days

    public function handle(): int
    {
        if (! $this->validateConfiguration()) {
            return self::FAILURE;
        }

        $this->info('🌍 MaxMind GeoIP Database Updater');
        $this->newLine();

        // Check if update is needed
        if (! $this->option('force') && ! $this->needsUpdate()) {
            $this->info('✓ Database is up to date (less than '.self::MAX_DB_AGE_DAYS.' days old)');
            $this->line('  Use --force to update anyway');

            return self::SUCCESS;
        }

        try {
            // Download database
            $this->info('→ Downloading GeoLite2-City database...');
            $archivePath = $this->downloadDatabase();

            // Extract database
            $this->info('→ Extracting database...');
            $mmdbPath = $this->extractDatabase($archivePath);

            // Move to final location
            $this->info('→ Installing database...');
            $this->installDatabase($mmdbPath);

            // Cleanup
            $this->info('→ Cleaning up temporary files...');
            $this->cleanup($archivePath);

            $this->newLine();
            $this->info('✓ GeoIP database updated successfully!');
            $this->line('  Database: '.config('services.maxmind.database_path'));

            Log::info('GeoIP database updated successfully');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('✗ Failed to update GeoIP database');
            $this->error('  Error: '.$e->getMessage());

            Log::error('GeoIP database update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Validate MaxMind configuration
     */
    private function validateConfiguration(): bool
    {
        $accountId = config('services.maxmind.account_id');
        $licenseKey = config('services.maxmind.license_key');

        if (empty($accountId) || empty($licenseKey)) {
            $this->error('✗ MaxMind configuration missing');
            $this->line('  Please set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY in .env');

            return false;
        }

        return true;
    }

    /**
     * Check if database needs update
     */
    private function needsUpdate(): bool
    {
        $dbPath = config('services.maxmind.database_path');

        if (! file_exists($dbPath)) {
            $this->line('  Database not found, will download');

            return true;
        }

        $age = now()->diffInDays(now()->setTimestamp(filemtime($dbPath)));

        if ($age >= self::MAX_DB_AGE_DAYS) {
            $this->line("  Database is {$age} days old, will update");

            return true;
        }

        return false;
    }

    /**
     * Download database from MaxMind
     */
    private function downloadDatabase(): string
    {
        $licenseKey = config('services.maxmind.license_key');

        $url = self::MAXMIND_URL.'?'.http_build_query([
            'edition_id' => self::EDITION_ID,
            'license_key' => $licenseKey,
            'suffix' => 'tar.gz',
        ]);

        $tempPath = storage_path('app/temp/geoip-'.time().'.tar.gz');
        File::ensureDirectoryExists(dirname($tempPath));

        $response = Http::timeout(300)->get($url);

        if (! $response->successful()) {
            throw new Exception('Failed to download database: HTTP '.$response->status());
        }

        File::put($tempPath, $response->body());

        $this->line('  Downloaded: '.File::size($tempPath).' bytes');

        return $tempPath;
    }

    /**
     * Extract .mmdb file from archive
     */
    private function extractDatabase(string $archivePath): string
    {
        $extractPath = storage_path('app/temp/geoip-extract-'.time());
        File::ensureDirectoryExists($extractPath);

        // Extract tar.gz
        $command = 'tar -xzf '.escapeshellarg($archivePath).' -C '.escapeshellarg($extractPath);
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Failed to extract archive');
        }

        // Find .mmdb file recursively
        $mmdbFiles = File::glob($extractPath.'/**/*.mmdb');

        if (empty($mmdbFiles)) {
            throw new Exception('No .mmdb file found in archive');
        }

        $this->line('  Found: '.basename($mmdbFiles[0]));

        return $mmdbFiles[0];
    }

    /**
     * Install database to final location
     */
    private function installDatabase(string $sourcePath): void
    {
        $targetPath = config('services.maxmind.database_path');

        File::ensureDirectoryExists(dirname($targetPath));

        // Backup existing database
        if (file_exists($targetPath)) {
            $backupPath = $targetPath.'.backup';
            File::copy($targetPath, $backupPath);
            $this->line('  Backed up existing database');
        }

        // Move new database
        File::move($sourcePath, $targetPath);

        $this->line('  Installed to: '.$targetPath);
    }

    /**
     * Cleanup temporary files
     */
    private function cleanup(string $archivePath): void
    {
        // Remove archive
        if (file_exists($archivePath)) {
            File::delete($archivePath);
        }

        // Remove extraction directory
        $extractDir = dirname($archivePath);
        if (File::isDirectory($extractDir) && basename($extractDir) === 'temp') {
            $tempDirs = File::glob($extractDir.'/geoip-extract-*');
            foreach ($tempDirs as $dir) {
                File::deleteDirectory($dir);
            }
        }

        $this->line('  Removed temporary files');
    }
}
