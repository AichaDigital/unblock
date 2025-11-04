<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * GeoIP Service
 *
 * Provides IP geolocation using MaxMind GeoLite2 database
 * Enriches IP reputation data with geographic information
 */
class GeoIPService
{
    private ?Reader $reader = null;

    private bool $enabled;

    private string $databasePath;

    public function __construct()
    {
        $this->enabled = config('services.maxmind.enabled', true);
        $configPath = config('services.maxmind.database_path');

        // Normalize path: if relative, convert to absolute using base_path()
        $this->databasePath = $this->normalizePath($configPath);

        if ($this->enabled && file_exists($this->databasePath)) {
            try {
                $this->reader = new Reader($this->databasePath);
            } catch (Exception $e) {
                Log::warning('GeoIP: Failed to initialize reader', [
                    'error' => $e->getMessage(),
                    'path' => $this->databasePath,
                ]);
                $this->enabled = false;
            }
        } else {
            if ($this->enabled) {
                Log::info('GeoIP: Database not found, service disabled', [
                    'path' => $this->databasePath,
                    'config_path' => $configPath,
                    'file_exists' => file_exists($this->databasePath),
                ]);
            }
            $this->enabled = false;
        }
    }

    /**
     * Normalize database path (handle both absolute and relative paths)
     */
    private function normalizePath(string $path): string
    {
        // If path is already absolute, return as-is
        if ($path[0] === '/' || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:/', $path))) {
            return $path;
        }

        // If path is relative, resolve from base_path
        return base_path($path);
    }

    /**
     * Get geographic information for an IP address
     */
    public function lookup(string $ip): ?array
    {
        if (! $this->enabled || ! $this->reader) {
            return null;
        }

        // Skip private/local IPs
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        try {
            $record = $this->reader->city($ip);

            return [
                'country_code' => $record->country->isoCode,
                'country_name' => $record->country->name,
                'city' => $record->city->name,
                'postal_code' => $record->postal->code,
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'timezone' => $record->location->timeZone,
                'continent' => $record->continent->code,
            ];
        } catch (AddressNotFoundException $e) {
            // IP not found in database - this is normal
            return null;
        } catch (Exception $e) {
            Log::warning('GeoIP: Lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if IP is private/local
     */
    private function isPrivateIp(string $ip): bool
    {
        // Check if it's a valid IP
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if it's a private IP
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Check if GeoIP service is enabled and available
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->reader !== null;
    }

    /**
     * Get database info
     */
    public function getDatabaseInfo(): array
    {
        if (! $this->enabled || ! $this->reader) {
            return [
                'available' => false,
                'path' => $this->databasePath,
                'exists' => file_exists($this->databasePath),
            ];
        }

        return [
            'available' => true,
            'path' => $this->databasePath,
            'exists' => true,
            'build_time' => $this->reader->metadata()->buildEpoch,
            'database_type' => $this->reader->metadata()->databaseType,
        ];
    }
}
