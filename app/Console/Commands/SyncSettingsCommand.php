<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class SyncSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync application settings from .env to database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Syncing settings from .env to database...');

        $mappings = [
            'company_name' => config('company.name'),
            'support_email' => config('company.support.email'),
            'support_url' => config('company.support.url'),
            'privacy_policy_url' => config('company.legal.privacy_policy_url'),
            'terms_url' => config('company.legal.terms_url'),
            'data_protection_url' => config('company.legal.data_protection_url'),
        ];

        $synced = 0;
        foreach ($mappings as $key => $value) {
            if ($value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
                $this->line("  ✓ Synced: {$key}");
                $synced++;
            } else {
                $this->line("  ⊘ Skipped: {$key} (not set in .env)");
            }
        }

        $this->newLine();
        $this->info("✅ Settings synced successfully! ({$synced} settings updated)");

        return self::SUCCESS;
    }
}
