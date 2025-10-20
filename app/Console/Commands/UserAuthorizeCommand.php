<?php

namespace App\Console\Commands;

use App\Models\{User, UserHostingPermission};
use Illuminate\Console\Command;

use function Laravel\Prompts\{confirm, multiselect, search, select, text};

class UserAuthorizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:authorize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage user authorizations (create authorized users and assign permissions)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔐 User Authorization Management');
        $this->newLine();

        $action = select(
            label: 'What action do you want to perform?',
            options: [
                'create' => '👤 Create authorized user',
                'assign' => '🔑 Assign permissions to existing user',
                'revoke' => '❌ Revoke permissions',
                'reassign' => '🔄 Reassign user to another parent',
                'list' => '📋 View existing authorizations',
            ],
            default: 'assign'
        );

        return match ($action) {
            'create' => $this->createAuthorizedUser(),
            'assign' => $this->assignPermissions(),
            'revoke' => $this->revokePermissions(),
            'reassign' => $this->reassignUserToParent(),
            'list' => $this->listAuthorizations(),
            default => 0,
        };
    }

    private function createAuthorizedUser(): int
    {
        $this->info('👤 Create Authorized User');
        $this->newLine();

        // Select parent user
        $parentUsers = User::whereNull('parent_user_id')->get();
        if ($parentUsers->isEmpty()) {
            $this->error('No parent users available.');

            return 1;
        }

        $parentUserId = search(
            label: 'Select the parent user:',
            options: function (string $value) use ($parentUsers) {
                if (strlen($value) === 0) {
                    return $parentUsers->take(10)->mapWithKeys(fn ($user) => [
                        (string) $user->id => "$user->name ($user->email)",
                    ])->toArray();
                }

                return $parentUsers->filter(function ($user) use ($value) {
                    return str_contains(strtolower($user->name), strtolower($value)) ||
                           str_contains(strtolower($user->email), strtolower($value));
                })->mapWithKeys(fn ($user) => [
                    (string) $user->id => "$user->name ($user->email)",
                ])->toArray();
            }
        );

        // Get user details
        $firstName = text(
            label: 'Authorized user first name:',
            required: true
        );

        $lastName = text(
            label: 'Last name (optional):'
        );

        $email = text(
            label: 'Authorized user email:',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Must be a valid email.',
                User::where('email', $value)->exists() => 'This email is already in use.',
                default => null
            }
        );

        $companyName = text(label: 'Company (optional):');

        // Create user
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'company_name' => $companyName,
            'parent_user_id' => $parentUserId,
            'password' => bcrypt('temp-password-'.uniqid()),
        ]);

        $this->info("✅ Authorized user created: $user->name ($user->email)");
        $this->newLine();

        // Ask if you want to assign permissions immediately
        if (confirm('Do you want to assign permissions now?')) {
            return $this->assignPermissionsToUser($user);
        }

        return 0;
    }

    private function assignPermissions(): int
    {
        $this->info('🔑 Assign Permissions');
        $this->newLine();

        // Select an authorized user
        $authorizedUsers = User::whereNotNull('parent_user_id')->with('parentUser')->get();
        if ($authorizedUsers->isEmpty()) {
            $this->error('No authorized users available.');

            return 1;
        }

        $userId = search(
            label: 'Select the authorized user:',
            options: function (string $value) use ($authorizedUsers) {
                if (strlen($value) === 0) {
                    return $authorizedUsers->take(10)->mapWithKeys(fn ($user) => [
                        (string) $user->id => "$user->name ($user->email) - Parent: {$user->parentUser->name}",
                    ])->toArray();
                }

                return $authorizedUsers->filter(function ($user) use ($value) {
                    return str_contains(strtolower($user->name), strtolower($value)) ||
                           str_contains(strtolower($user->email), strtolower($value)) ||
                           str_contains(strtolower($user->parentUser->name), strtolower($value));
                })->mapWithKeys(fn ($user) => [
                    (string) $user->id => "$user->name ($user->email) - Parent: {$user->parentUser->name}",
                ])->toArray();
            }
        );

        $user = User::find($userId);

        return $this->assignPermissionsToUser($user);
    }

    private function assignPermissionsToUser(User $user): int
    {
        $this->info("🔑 Assigning permissions to: $user->name");
        $this->newLine();

        // Check what resources are available for the parent user
        $parentUser = $user->parentUser;
        $hasHostings = $parentUser->hostings()->exists();
        $hasHosts = $parentUser->hosts()->wherePivot('is_active', true)->exists();

        if (! $hasHostings && ! $hasHosts) {
            $this->error('❌ The parent user has no available resources (neither hostings nor servers).');

            return 1;
        }

        // Build permission options based on available resources
        $permissionOptions = [];

        if ($hasHostings) {
            $permissionOptions['hosting'] = '🌐 Specific hosting permissions';
        }

        if ($hasHosts) {
            $permissionOptions['host'] = '🖥️ Complete server permissions';
        }

        if ($hasHostings && $hasHosts) {
            $permissionOptions['both'] = '🔄 Both types';
        }

        if (empty($permissionOptions)) {
            $this->error('❌ No permission types available.');

            return 1;
        }

        $permissionType = select(
            label: 'What type of permissions do you want to assign?',
            options: $permissionOptions
        );

        $success = true;

        if (in_array($permissionType, ['hosting', 'both'])) {
            $success = $this->assignHostingPermissions($user) && $success;
        }

        if (in_array($permissionType, ['host', 'both'])) {
            $success = $this->assignHostPermissions($user) && $success;
        }

        return $success ? 0 : 1;
    }

    private function assignHostingPermissions(User $user): bool
    {
        $this->info('🌐 Assign Hosting Permissions');
        $this->newLine();

        // Get available hostings from parent user
        $parentUser = $user->parentUser;
        $availableHostings = $parentUser->hostings()->with('host')->get();

        if ($availableHostings->isEmpty()) {
            $this->warn('⚠️ The parent user has no available hostings.');

            return true;
        }

        // Get already assigned hostings
        $assignedHostingIds = $user->hostingPermissions()->pluck('hosting_id')->toArray();
        $availableHostings = $availableHostings->whereNotIn('id', $assignedHostingIds);

        if ($availableHostings->isEmpty()) {
            $this->warn('⚠️ All hostings are already assigned to this user.');

            return true;
        }

        if ($availableHostings->count() === 1) {
            // If there's only one hosting, ask directly
            $hosting = $availableHostings->first();
            $shouldAssign = confirm(
                "Assign access to hosting: {$hosting->domain} ({$hosting->username}) - {$hosting->host->fqdn}?",
                true
            );
            $selectedHostingIds = $shouldAssign ? [$hosting->id] : [];
        } else {
            $selectedHostingIds = multiselect(
                label: 'Select the hostings to authorize:',
                options: $availableHostings->mapWithKeys(fn ($hosting) => [
                    (string) $hosting->id => "{$hosting->domain} ({$hosting->username}) - {$hosting->host->fqdn}",
                ])->toArray(),
                required: false
            );
        }

        if (empty($selectedHostingIds)) {
            $this->info('No hostings selected.');

            return true;
        }

        $isActive = confirm('Activate permissions immediately?', true);

        foreach ($selectedHostingIds as $hostingId) {
            UserHostingPermission::create([
                'user_id' => $user->id,
                'hosting_id' => $hostingId,
                'is_active' => $isActive,
            ]);
        }

        $this->info('✅ Assigned '.count($selectedHostingIds).' hosting permissions.');

        return true;
    }

    private function assignHostPermissions(User $user): bool
    {
        $this->info('🖥️ Assign Server Permissions');
        $this->newLine();

        // Get available hosts from the parent user (not all hosts in system)
        $parentUser = $user->parentUser;
        $availableHosts = $parentUser->hosts()->wherePivot('is_active', true)->get();

        if ($availableHosts->isEmpty()) {
            $this->warn('⚠️ The parent user has no access to any server.');

            return true;
        }

        // Get already assigned hosts to this authorized user
        $assignedHostIds = $user->hosts()->pluck('hosts.id')->toArray();
        $availableHosts = $availableHosts->whereNotIn('id', $assignedHostIds);

        if ($availableHosts->isEmpty()) {
            $this->warn('⚠️ All servers from the parent user are already assigned to this user.');

            return true;
        }

        if ($availableHosts->count() === 1) {
            // If there's only one host, ask directly
            $host = $availableHosts->first();
            $shouldAssign = confirm(
                "Assign access to server: {$host->fqdn} ({$host->ip}) - {$host->panel}?",
                true
            );
            $selectedHostIds = $shouldAssign ? [$host->id] : [];
        } else {
            $selectedHostIds = multiselect(
                label: 'Select the servers to authorize:',
                options: $availableHosts->mapWithKeys(fn ($host) => [
                    (string) $host->id => "{$host->fqdn} ({$host->ip}) - {$host->panel}",
                ])->toArray(),
                required: false
            );
        }

        if (empty($selectedHostIds)) {
            $this->info('No servers selected.');

            return true;
        }

        $isActive = confirm('Activate permissions immediately?', true);

        foreach ($selectedHostIds as $hostId) {
            $user->hosts()->attach($hostId, ['is_active' => $isActive]);
        }

        $this->info('✅ Assigned '.count($selectedHostIds).' server permissions.');

        return true;
    }

    private function revokePermissions(): int
    {
        $this->info('❌ Revoke Permissions');
        $this->newLine();

        // Select an authorized user
        $authorizedUsers = User::whereNotNull('parent_user_id')
            ->with(['parentUser', 'hostingPermissions.hosting', 'hosts'])
            ->get()
            ->filter(fn ($user) => $user->hostingPermissions->isNotEmpty() || $user->hosts->isNotEmpty());

        if ($authorizedUsers->isEmpty()) {
            $this->warn('⚠️ No authorized users with assigned permissions.');

            return 0;
        }

        $userId = search(
            label: 'Select the authorized user:',
            options: function (string $value) use ($authorizedUsers) {
                if (strlen($value) === 0) {
                    return $authorizedUsers->take(10)->mapWithKeys(fn ($user) => [
                        (string) $user->id => "{$user->name} ({$user->email}) - Parent: {$user->parentUser->name}",
                    ])->toArray();
                }

                return $authorizedUsers->filter(function ($user) use ($value) {
                    return str_contains(strtolower($user->name), strtolower($value)) ||
                           str_contains(strtolower($user->email), strtolower($value)) ||
                           str_contains(strtolower($user->parentUser->name), strtolower($value));
                })->mapWithKeys(fn ($user) => [
                    (string) $user->id => "{$user->name} ({$user->email}) - Parent: {$user->parentUser->name}",
                ])->toArray();
            }
        );

        $user = User::with(['hostingPermissions.hosting.host', 'hosts'])->find($userId);

        $permissionType = select(
            label: 'What permissions do you want to revoke?',
            options: [
                'hosting' => '🌐 Hosting permissions',
                'host' => '🖥️ Server permissions',
                'all' => '🗑️ All permissions',
            ]
        );

        if (in_array($permissionType, ['hosting', 'all'])) {
            $this->revokeHostingPermissions($user);
        }

        if (in_array($permissionType, ['host', 'all'])) {
            $this->revokeHostPermissions($user);
        }

        return 0;
    }

    private function revokeHostingPermissions(User $user): void
    {
        $permissions = $user->hostingPermissions()->with('hosting.host')->get();

        if ($permissions->isEmpty()) {
            $this->info('No hosting permissions to revoke.');

            return;
        }

        $selectedPermissionIds = multiselect(
            label: 'Select the hosting permissions to revoke:',
            options: $permissions->mapWithKeys(fn ($permission) => [
                (string) $permission->id => "{$permission->hosting->domain} - {$permission->hosting->host->fqdn} ".
                    ($permission->is_active ? '(Active)' : '(Inactive)'),
            ])->toArray(),
            required: false
        );

        if (empty($selectedPermissionIds)) {
            return;
        }

        UserHostingPermission::whereIn('id', $selectedPermissionIds)->delete();
        $this->info('✅ Revoked '.count($selectedPermissionIds).' hosting permissions.');
    }

    private function revokeHostPermissions(User $user): void
    {
        $hosts = $user->hosts;

        if ($hosts->isEmpty()) {
            $this->info('No server permissions to revoke.');

            return;
        }

        $selectedHostIds = multiselect(
            label: 'Select the server permissions to revoke:',
            options: $hosts->mapWithKeys(fn ($host) => [
                (string) $host->id => "{$host->fqdn} ({$host->ip}) ".
                    ($host->pivot->is_active ? '(Active)' : '(Inactive)'),
            ])->toArray(),
            required: false
        );

        if (empty($selectedHostIds)) {
            return;
        }

        $user->hosts()->detach($selectedHostIds);
        $this->info('✅ Revoked '.count($selectedHostIds).' server permissions.');
    }

    private function listAuthorizations(): int
    {
        $this->info('📋 Existing Authorizations');
        $this->newLine();

        // Get all authorized users with their permissions
        $authorizedUsers = User::whereNotNull('parent_user_id')
            ->with(['parentUser', 'hostingPermissions.hosting.host', 'hosts'])
            ->get();

        if ($authorizedUsers->isEmpty()) {
            $this->warn('⚠️ No authorized users.');

            return 0;
        }

        foreach ($authorizedUsers as $user) {
            $this->info("👤 {$user->name} ({$user->email})");
            $this->line("   Parent: {$user->parentUser->name}");

            // Hosting permissions
            if ($user->hostingPermissions->isNotEmpty()) {
                $this->line('   🌐 Hosting Permissions:');
                foreach ($user->hostingPermissions as $permission) {
                    $status = $permission->is_active ? '✅' : '❌';
                    $this->line("      {$status} {$permission->hosting->domain} - {$permission->hosting->host->fqdn}");
                }
            }

            // Host permissions
            if ($user->hosts->isNotEmpty()) {
                $this->line('   🖥️ Server Permissions:');
                foreach ($user->hosts as $host) {
                    $status = $host->pivot->is_active ? '✅' : '❌';
                    $this->line("      {$status} {$host->fqdn} ({$host->ip})");
                }
            }

            if ($user->hostingPermissions->isEmpty() && $user->hosts->isEmpty()) {
                $this->line('   ⚠️ No permissions assigned');
            }

            $this->newLine();
        }

        return 0;
    }

    private function reassignUserToParent(): int
    {
        $this->info('🔄 Reassign User to Another Parent');
        $this->newLine();

        // Select an authorized user
        $authorizedUsers = User::whereNotNull('parent_user_id')->with('parentUser')->get();
        if ($authorizedUsers->isEmpty()) {
            $this->error('No authorized users available.');

            return 1;
        }

        $userId = search(
            label: 'Select the authorized user:',
            options: function (string $value) use ($authorizedUsers) {
                if (strlen($value) === 0) {
                    return $authorizedUsers->take(10)->mapWithKeys(fn ($user) => [
                        (string) $user->id => "$user->name ($user->email) - Current Parent: {$user->parentUser->name}",
                    ])->toArray();
                }

                return $authorizedUsers->filter(function ($user) use ($value) {
                    return str_contains(strtolower($user->name), strtolower($value)) ||
                           str_contains(strtolower($user->email), strtolower($value)) ||
                           str_contains(strtolower($user->parentUser->name), strtolower($value));
                })->mapWithKeys(fn ($user) => [
                    (string) $user->id => "$user->name ($user->email) - Current Parent: {$user->parentUser->name}",
                ])->toArray();
            }
        );

        $user = User::with(['parentUser', 'hostingPermissions.hosting', 'hosts'])->find($userId);
        $currentParent = $user->parentUser;

        $this->info("Selected user: {$user->name} ({$user->email})");
        $this->info("Current parent: {$currentParent->name} ({$currentParent->email})");
        $this->newLine();

        // Check existing permissions
        $hasHostingPermissions = $user->hostingPermissions->isNotEmpty();
        $hasHostPermissions = $user->hosts->isNotEmpty();

        if ($hasHostingPermissions || $hasHostPermissions) {
            $this->warn('⚠️ This user has existing permissions that will be affected:');

            if ($hasHostingPermissions) {
                $this->line("   🌐 {$user->hostingPermissions->count()} hosting permissions");
            }

            if ($hasHostPermissions) {
                $this->line("   🖥️ {$user->hosts->count()} server permissions");
            }

            $this->newLine();
        }

        // Select new parent user (exclude current parent)
        $parentUsers = User::whereNull('parent_user_id')
            ->where('id', '!=', $currentParent->id)
            ->get();

        if ($parentUsers->isEmpty()) {
            $this->error('No other parent users available.');

            return 1;
        }

        $parentUserId = search(
            label: 'Select the new parent user:',
            options: function (string $value) use ($parentUsers) {
                if (strlen($value) === 0) {
                    return $parentUsers->take(10)->mapWithKeys(fn ($user) => [
                        (string) $user->id => "$user->name ($user->email)",
                    ])->toArray();
                }

                return $parentUsers->filter(function ($user) use ($value) {
                    return str_contains(strtolower($user->name), strtolower($value)) ||
                           str_contains(strtolower($user->email), strtolower($value));
                })->mapWithKeys(fn ($user) => [
                    (string) $user->id => "$user->name ($user->email)",
                ])->toArray();
            }
        );

        $newParent = User::find($parentUserId);

        // Handle existing permissions
        if ($hasHostingPermissions || $hasHostPermissions) {
            $permissionAction = select(
                label: 'What do you want to do with existing permissions?',
                options: [
                    'revoke' => '❌ Revoke all existing permissions',
                    'keep' => '⚠️ Keep permissions (may cause conflicts if new parent doesn\'t own resources)',
                ],
                default: 'revoke'
            );

            if ($permissionAction === 'revoke') {
                // Revoke all permissions
                if ($hasHostingPermissions) {
                    $user->hostingPermissions()->delete();
                    $this->info("✅ Revoked {$user->hostingPermissions->count()} hosting permissions");
                }

                if ($hasHostPermissions) {
                    $user->hosts()->detach();
                    $this->info("✅ Revoked {$user->hosts->count()} server permissions");
                }
            } else {
                $this->warn('⚠️ Keeping existing permissions - ensure new parent owns the resources');
            }
        }

        // Confirm reassignment
        if (! confirm("Confirm reassignment from '{$currentParent->name}' to '{$newParent->name}'?")) {
            $this->info('❌ Reassignment cancelled.');

            return 0;
        }

        // Update user's parent
        $user->parent_user_id = $parentUserId;
        $user->save();

        $this->info('✅ User successfully reassigned:');
        $this->line("   👤 User: {$user->name} ({$user->email})");
        $this->line("   📤 From: {$currentParent->name}");
        $this->line("   📥 To: {$newParent->name}");

        return 0;
    }
}
