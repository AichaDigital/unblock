<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Hash, Validator};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\{confirm, password, text};

/**
 * Create new users (admin or normal) with proper validation
 *
 * This command supports:
 * - Creating admin users with full access
 * - Creating normal users (WHMCS clients)
 * - Development mode (--no-secure) for simple passwords in local environments
 */
class UserCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {--no-secure : Disable complex password requirements (development only)}
                            {--admin : Create an admin user instead of normal user}
                            {--email= : Email address for the user}
                            {--first-name= : First name for the user}
                            {--last-name= : Last name for the user}
                            {--company-name= : Company name for the user (optional)}
                            {--password= : Password for the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user (admin or normal) with proper validation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('👤 User Creation');
        $this->newLine();

        // Warning for --no-secure option
        if ($this->option('no-secure')) {
            $this->warn('⚠️  Running in DEVELOPMENT MODE - Password complexity disabled');
            $this->warn('    This should NEVER be used in production!');
            $this->newLine();
        }

        // Check if all required parameters are provided for non-interactive mode
        $isNonInteractive = $this->option('email') && 
                           $this->option('first-name') && 
                           $this->option('last-name') && 
                           $this->option('password');

        if ($isNonInteractive) {
            return $this->handleNonInteractive();
        }

        // Interactive mode (original behavior)
        return $this->handleInteractive();
    }

    /**
     * Handle non-interactive user creation with command line parameters
     */
    private function handleNonInteractive(): int
    {
        $email = $this->option('email');
        $firstName = $this->option('first-name');
        $lastName = $this->option('last-name');
        $companyName = $this->option('company-name');
        $password = $this->option('password');
        $isAdmin = $this->option('admin');

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('❌ Invalid email address: ' . $email);
            return self::FAILURE;
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error('❌ Email already exists: ' . $email);
            return self::FAILURE;
        }

        // Validate names
        if (strlen($firstName) < 2) {
            $this->error('❌ First name must be at least 2 characters');
            return self::FAILURE;
        }

        if (strlen($lastName) < 2) {
            $this->error('❌ Last name must be at least 2 characters');
            return self::FAILURE;
        }

        // Validate password if not in development mode
        if (!$this->option('no-secure')) {
            try {
                $validator = Validator::make(
                    ['password' => $password],
                    ['password' => ['required', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()]]
                );

                if ($validator->fails()) {
                    $this->error('❌ Password validation failed: ' . $validator->errors()->first('password'));
                    return self::FAILURE;
                }
            } catch (ValidationException $e) {
                $this->error('❌ Password validation error: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        // Summary
        $this->info('📋 User Summary:');
        $this->line("   Email:       {$email}");
        $this->line("   Name:        {$firstName} {$lastName}");
        if ($companyName) {
            $this->line("   Company:     {$companyName}");
        }
        $this->line('   Type:        '.($isAdmin ? '👑 Administrator' : '👤 Normal User'));
        $this->line('   Security:    '.($this->option('no-secure') ? '⚠️  Development mode' : '✅ Production mode'));
        $this->newLine();

        // Create user in transaction
        try {
            DB::transaction(function () use ($email, $firstName, $lastName, $companyName, $password, $isAdmin) {
                User::create([
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => $companyName,
                    'password' => Hash::make($password),
                    'is_admin' => $isAdmin,
                ]);
            });

            $this->info('✅ User created successfully!');

            if ($isAdmin) {
                $this->line('   🔑 This user can now access the Filament admin panel.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to create user: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Handle interactive user creation (original behavior)
     */
    private function handleInteractive(): int
    {
        // Determine user type
        $isAdmin = $this->option('admin');
        if (! $isAdmin) {
            $isAdmin = confirm(
                label: 'Create an admin user?',
                default: false,
                hint: 'Admin users have full access to Filament panel'
            );
        }

        // Collect user data
        $email = text(
            label: 'Email address',
            placeholder: $isAdmin ? 'admin@example.com' : 'user@example.com',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Must be a valid email address.',
                User::where('email', $value)->exists() => 'This email is already registered.',
                default => null
            }
        );

        $firstName = text(
            label: 'First name',
            placeholder: 'John',
            required: true,
            validate: fn (string $value) => strlen($value) < 2
                ? 'First name must be at least 2 characters.'
                : null
        );

        $lastName = text(
            label: 'Last name',
            placeholder: 'Doe',
            required: true,
            validate: fn (string $value) => strlen($value) < 2
                ? 'Last name must be at least 2 characters.'
                : null
        );

        $companyName = text(
            label: 'Company name (optional)',
            placeholder: 'Acme Corp',
            required: false
        );

        // Password handling
        if ($this->option('no-secure')) {
            $userPassword = password(
                label: 'Password',
                placeholder: 'Simple password allowed in dev mode',
                required: true
            );
        } else {
            $userPassword = password(
                label: 'Password',
                placeholder: 'At least 10 chars, mixed case, numbers, symbols',
                required: true,
                validate: function (string $value) {
                    try {
                        $validator = Validator::make(
                            ['password' => $value],
                            ['password' => ['required', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()]]
                        );

                        if ($validator->fails()) {
                            return $validator->errors()->first('password');
                        }

                        return null;
                    } catch (ValidationException $e) {
                        return $e->getMessage();
                    }
                }
            );

            // Confirm password
            $confirmPassword = password(
                label: 'Confirm password',
                required: true
            );

            if ($userPassword !== $confirmPassword) {
                $this->error('❌ Passwords do not match!');

                return self::FAILURE;
            }
        }

        // Summary
        $this->newLine();
        $this->info('📋 User Summary:');
        $this->line("   Email:       {$email}");
        $this->line("   Name:        {$firstName} {$lastName}");
        if ($companyName) {
            $this->line("   Company:     {$companyName}");
        }
        $this->line('   Type:        '.($isAdmin ? '👑 Administrator' : '👤 Normal User'));
        $this->line('   Security:    '.($this->option('no-secure') ? '⚠️  Development mode' : '✅ Production mode'));
        $this->newLine();

        // Confirm creation
        if (! confirm('Create this user?', true)) {
            $this->warn('❌ User creation cancelled.');

            return self::SUCCESS;
        }

        // Create user in transaction
        try {
            DB::transaction(function () use ($email, $firstName, $lastName, $companyName, $userPassword, $isAdmin) {
                User::create([
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => $companyName,
                    'password' => Hash::make($userPassword),
                    'is_admin' => $isAdmin,
                ]);
            });

            $this->newLine();
            $this->info('✅ User created successfully!');

            if ($isAdmin) {
                $this->line('   🔑 This user can now access the Filament admin panel.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to create user: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
