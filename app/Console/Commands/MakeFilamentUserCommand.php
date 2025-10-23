<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Filament\Commands\MakeUserCommand as FilamentMakeUserCommand;

/**
 * Override Filament's make:filament-user command
 *
 * This command is disabled because it doesn't fit our User model structure.
 * Users should be created using the `user:create` command instead,
 * which properly handles all our custom fields and validations.
 */
class MakeFilamentUserCommand extends FilamentMakeUserCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[DISABLED] Use "user:create --admin" instead';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->error('❌ This command has been disabled.');
        $this->newLine();
        $this->line('The default Filament user creation command doesn\'t fit our User model structure.');
        $this->line('Please use one of the following commands instead:');
        $this->newLine();
        $this->info('📌 For admin users:');
        $this->line('   php artisan user:create --admin');
        $this->newLine();
        $this->info('📌 For admin users (development with simple password):');
        $this->line('   php artisan user:create --admin --no-secure');
        $this->newLine();
        $this->info('📌 For authorized users (linked to a parent):');
        $this->line('   php artisan user:authorize');
        $this->newLine();

        return self::FAILURE;
    }
}
