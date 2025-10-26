<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\{Host, Hosting, User};
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{DB, Log};
use Lorisleiva\Actions\Concerns\{AsAction, AsCommand};

/**
 * WHMCS Synchronization Action
 *
 * Esta acción sincroniza usuarios y sus servicios entre WHMCS y Unblock.
 * Se ejecuta diariamente durante la noche y realiza las siguientes operaciones:
 *
 * 1. Sincronización de Usuarios:
 *    - Añade usuarios nuevos que existen en WHMCS pero no en Unblock
 *    - Realiza soft-delete de usuarios que ya no están activos en WHMCS
 *    - Preserva usuarios autorizados manualmente (con parent_user_id)
 *
 * 2. Sincronización de Hostings:
 *    - Crea nuevos hostings para dominios activos en WHMCS
 *    - Actualiza hostings existentes si han cambiado de servidor
 *    - Realiza soft-delete de hostings que ya no están activos
 *    - Restaura hostings previamente eliminados si se reactivan en WHMCS
 *
 *
 * @todo Implementar procesamiento por lotes para mejor rendimiento
 * @todo Implementar mecanismo de reintento para sincronizaciones fallidas
 * @todo Añadir soporte para sincronización de campos personalizados
 */
class WhmcsSynchro
{
    use AsAction;
    use AsCommand;

    /**
     * @var string Command signature for CLI usage
     */
    public string $commandSignature = 'whmcs:sync
                                      {--debug : Enable debug mode with detailed output}
                                      {--user= : Process only specific user by email}
                                      {--dry-run : Show what would be done without making changes}';

    /**
     * @var string Command description for CLI usage
     */
    public string $commandDescription = 'Synchronize users and hostings from WHMCS';

    protected bool $showOutput = false;

    protected Collection $hosts;

    protected array $config;

    protected bool $debugMode = false;

    protected ?string $specificUserEmail = null;

    public function __construct()
    {
        $this->config = config('unblock.whmcs.sync', []);
        $this->hosts = collect();
    }

    /**
     * Handle the synchronization process
     *
     * This is the main entry point for the synchronization process.
     * It loads all hosts and starts the user synchronization process.
     *
     * @param  Command|null  $command  Command instance for CLI output
     *
     * @throws Exception If synchronization fails
     */
    public function handle(?Command $command = null): void
    {
        if (! $this->isEnabled()) {
            $this->output($command, 'WHMCS synchronization is disabled');

            return;
        }

        // Set debug mode and specific user from command options
        if ($command) {
            $this->debugMode = $command->option('debug') ?? false;
            $this->specificUserEmail = $command->option('user');

            if ($this->debugMode) {
                $this->output($command, '🐛 DEBUG MODE ENABLED - Detailed output will be shown');
            }

            if ($this->specificUserEmail) {
                $this->output($command, "🎯 Processing only user: {$this->specificUserEmail}");
            }

            if ($command->option('dry-run')) {
                $this->output($command, '🔍 DRY RUN MODE - No changes will be made');
            }
        }

        $this->loadHosts();
        $this->output($command, 'Loaded '.count($this->hosts).' hosts');

        if ($this->shouldSyncUsers()) {
            $this->synchroUsers($command);
        }

        if ($this->shouldSyncHostings()) {
            $this->output($command, 'Hostings synchronization is enabled but handled per user');
        }
    }

    protected function output(?Command $command, string $message): void
    {
        if ($command) {
            $command->info($message);
        }

        Log::info($message);
    }

    /**
     * Check if WHMCS synchronization is enabled
     *
     * @return bool True if synchronization is enabled
     */
    protected function isEnabled(): bool
    {
        return config('unblock.whmcs.enabled', false);
    }

    /**
     * Load all hosts into memory
     *
     * This improves performance by avoiding multiple database queries
     */
    public function loadHosts(): void
    {
        $this->hosts = Host::whereNotNull('whmcs_server_id')->get();

        if ($this->debugMode) {
            Log::info('🐛 DEBUG: Loaded hosts with WHMCS server IDs: '.
                $this->hosts->pluck('whmcs_server_id')->join(', '));
        }
    }

    /**
     * Synchronize users from WHMCS
     *
     * This method handles the main user synchronization logic:
     * 1. Gets active WHMCS client IDs
     * 2. Deactivates users no longer in WHMCS
     * 3. Processes active WHMCS clients
     *
     * @param  Command|null  $command  Command instance for CLI output
     */
    protected function synchroUsers(?Command $command): void
    {
        $this->output($command, 'Starting user synchronization...');

        $activeWhmcsIds = $this->getActiveWhmcsClientIds();
        $this->output($command, 'Found '.count($activeWhmcsIds).' active WHMCS clients');

        if ($this->debugMode) {
            $this->debugOutput($command, 'Active WHMCS client IDs: '.implode(', ', array_slice($activeWhmcsIds, 0, 10)).(count($activeWhmcsIds) > 10 ? '...' : ''));
        }

        $this->deactivateInactiveUsers($activeWhmcsIds);
        $this->processActiveWhmcsClients($command);
    }

    protected function getActiveWhmcsClientIds(): array
    {
        $query = $this->getActiveWhmcsClientsQuery();

        if ($this->specificUserEmail) {
            $query->where('email', $this->specificUserEmail);
        }

        return $query->pluck('id')->toArray();
    }

    protected function deactivateInactiveUsers(array $activeWhmcsIds): void
    {
        if ($this->specificUserEmail) {
            // Skip deactivation when processing specific user
            return;
        }

        $inactiveUsers = User::whereNotNull('whmcs_client_id')
            ->whereNotIn('whmcs_client_id', $activeWhmcsIds)
            ->whereNull('deleted_at')
            ->get();

        foreach ($inactiveUsers as $user) {
            Log::info("Deactivating inactive user: {$user->email}");

            // Deactivate user's hostings (only automatic ones, not manual)
            $userHostings = Hosting::where('user_id', $user->id)
                ->where('hosting_manual', false)
                ->whereNull('deleted_at')
                ->get();

            foreach ($userHostings as $hosting) {
                Log::info("Deactivating hosting {$hosting->domain} for inactive user {$user->email}");
                $hosting->delete(); // Soft delete
            }

            $user->delete(); // Soft delete
        }

        if ($this->debugMode && $inactiveUsers->count() > 0) {
            Log::info('🐛 DEBUG: Deactivated users: '.$inactiveUsers->pluck('email')->join(', '));
        }
    }

    protected function processActiveWhmcsClients(?Command $command): void
    {
        $query = $this->getActiveWhmcsClientsQuery();

        if ($this->specificUserEmail) {
            $query->where('email', $this->specificUserEmail);
            $this->output($command, "🎯 Filtering for user: {$this->specificUserEmail}");
        }

        $whmcsClients = $query->get();
        $this->output($command, 'Processing '.$whmcsClients->count().' WHMCS clients');

        foreach ($whmcsClients as $whmcsClient) {
            $this->debugOutput($command, "Processing WHMCS client ID: {$whmcsClient->id}, Email: {$whmcsClient->email}");
            $this->processClient($whmcsClient, $command);
        }
    }

    protected function getActiveWhmcsClientsQuery(): Builder
    {
        return DB::connection('whmcs')
            ->table('tblclients')
            ->where('status', 'Active')
            ->orderBy('id');
    }

    /**
     * Process a single WHMCS client
     *
     * This method handles the synchronization of a single client and their services:
     * 1. Find or create user
     * 2. Synchronizes their hosting services if enabled
     *
     * @param  object  $whmcsClient  WHMCS client data
     * @param  Command|null  $command  Command instance for CLI output
     */
    protected function processClient(object $whmcsClient, ?Command $command): void
    {
        $this->debugOutput($command, "�� Processing client: {$whmcsClient->email} (WHMCS ID: {$whmcsClient->id})");

        $user = $this->findOrCreateUser($whmcsClient, $command);

        if (! $user) {
            $this->debugOutput($command, "❌ Could not find or create user for: {$whmcsClient->email}");

            return;
        }

        $this->output($command, "Processing hostings for user: {$user->email}");

        if ($this->shouldSyncHostings()) {
            // Count hostings before sync
            $activeHostingsBefore = Hosting::where('user_id', $user->id)->whereNull('deleted_at')->count();
            $this->debugOutput($command, "📊 User {$user->email} has {$activeHostingsBefore} active hostings before sync");

            $this->processActiveHostings($user->id, $command);

            // Count hostings after sync
            $activeHostingsAfter = Hosting::where('user_id', $user->id)->whereNull('deleted_at')->count();
            $this->debugOutput($command, "📊 User {$user->email} has {$activeHostingsAfter} active hostings after sync");

            Log::info("User {$user->email} hostings: Before={$activeHostingsBefore}, After={$activeHostingsAfter}");
        }
    }

    /**
     * Find a host by its WHMCS ID
     *
     * @param  mixed  $whmcsId  WHMCS server ID
     * @return Host|null Host instance if found, null otherwise
     */
    public function findHostByWhmcsId(mixed $whmcsId): ?Host
    {
        // Ensure we have a string value for comparison
        $whmcsId = (string) $whmcsId;

        // First try with the exact ID
        $host = $this->hosts->where('whmcs_server_id', $whmcsId)->first();

        if (! $host && $this->debugMode) {
            Log::warning("🐛 DEBUG: No host found with WHMCS server ID: {$whmcsId}. Available WHMCS server IDs: ".
                $this->hosts->pluck('whmcs_server_id')->join(', '));
        }

        return $host;
    }

    /**
     * Create a new user from WHMCS client data
     */
    public function createUser(object $whmcsClient): User
    {
        return User::create([
            'first_name' => $whmcsClient->firstname,
            'last_name' => $whmcsClient->lastname,
            'company_name' => $whmcsClient->companyname,
            'email' => $whmcsClient->email,
            'password_whmcs' => $whmcsClient->password,
            'password' => bcrypt(Str::random(32)),
            'whmcs_client_id' => $whmcsClient->id,
        ]);
    }

    /**
     * Find or create a user from WHMCS client data
     */
    public function findOrCreateUser(object $whmcsClient, ?Command $command): ?User
    {
        // Search for user including deleted ones
        $user = User::withTrashed()
            ->where('whmcs_client_id', $whmcsClient->id)
            ->first();

        if (! $user && $this->shouldCreateUser()) {
            $user = $this->createUser($whmcsClient);
            $this->output($command, "✅ User created: {$user->email}");
            $this->debugOutput($command, "🆕 Created user with WHMCS client ID: {$whmcsClient->id}");
        }
        // If user exists and is deleted, restore it
        elseif ($user && $user->trashed()) {
            $user->restore();
            $this->output($command, "🔄 User restored: {$user->email}");
            $this->debugOutput($command, "♻️ Restored deleted user: {$user->email}");
        } elseif ($user) {
            $this->debugOutput($command, "✅ Found existing user: {$user->email}");
        }

        return $user;
    }

    protected function shouldCreateUser(): bool
    {
        return $this->config['users']['create_if_not_exists'] ?? false;
    }

    protected function shouldSyncUsers(): bool
    {
        return $this->config['users']['enabled'] ?? false;
    }

    protected function shouldSyncHostings(): bool
    {
        return $this->config['hostings']['enabled'] ?? false;
    }

    /**
     * Process active hostings for a user
     *
     * @throws Exception When there is an error processing a hosting
     */
    public function processActiveHostings(int $userId, ?Command $command = null): void
    {
        $user = User::find($userId);
        if (! $user) {
            throw new Exception("User {$userId} not found");
        }

        $whmcsClientId = $user->whmcs_client_id;
        if (! $whmcsClientId) {
            $this->debugOutput($command, "⚠️ User {$user->email} has no WHMCS client ID, skipping hosting sync");

            return;
        }

        $whmcsHostings = DB::connection('whmcs')
            ->table('tblhosting')
            ->where('userid', $whmcsClientId)
            ->where('domainstatus', 'Active')
            ->get();

        $this->debugOutput($command, "🏠 Found {$whmcsHostings->count()} active hostings in WHMCS for user ID: {$userId} (WHMCS Client ID: {$whmcsClientId})");

        if ($this->debugMode && $whmcsHostings->count() > 0) {
            $domains = $whmcsHostings->pluck('domain')->join(', ');
            $this->debugOutput($command, "🌐 Domains found: {$domains}");
        }

        Log::info('Processing '.$whmcsHostings->count()." active hostings for user ID: {$userId}");

        foreach ($whmcsHostings as $whmcsHosting) {
            try {
                $this->debugOutput($command, "🔍 Processing hosting: {$whmcsHosting->domain} (Server: {$whmcsHosting->server})");

                if (empty($whmcsHosting->domain)) {
                    $this->debugOutput($command, "⚠️ Skipping hosting with empty domain for user ID: {$userId}");
                    Log::warning("Skipping hosting with empty domain for user ID: {$userId}");

                    continue;
                }

                $host = $this->findHostByWhmcsId((string) $whmcsHosting->server);
                if (! $host) {
                    $this->debugOutput($command, "❌ No host found for WHMCS server ID: {$whmcsHosting->server}");
                    Log::warning("No host found for WHMCS server ID: {$whmcsHosting->server}");

                    continue;
                }

                $this->debugOutput($command, "✅ Found host: {$host->name} (ID: {$host->id}) for server: {$whmcsHosting->server}");

                // Search for existing hosting
                $hosting = Hosting::withTrashed()
                    ->where('domain', $whmcsHosting->domain)
                    ->first();

                if (! $hosting) {
                    // Create new hosting
                    $this->debugOutput($command, "🆕 Creating new hosting {$whmcsHosting->domain} for user {$userId}");
                    Log::info("Creating new hosting {$whmcsHosting->domain} for user {$userId}");
                    $hosting = Hosting::create([
                        'user_id' => $userId,
                        'host_id' => $host->id,
                        'domain' => $whmcsHosting->domain,
                        'username' => $whmcsHosting->username,
                        'hosting_manual' => false,
                    ]);
                    $this->debugOutput($command, "✅ Created hosting ID: {$hosting->id}");
                } else {
                    $this->debugOutput($command, "🔍 Found existing hosting: {$hosting->domain} (ID: {$hosting->id})");

                    // Skip manual hostings
                    if ($hosting->hosting_manual) {
                        $this->debugOutput($command, "🔒 Skipping manual hosting: {$hosting->domain} (ID: {$hosting->id})");

                        continue;
                    }

                    // If hosting was deleted, restore it
                    if ($hosting->trashed()) {
                        $this->debugOutput($command, "♻️ Restoring deleted hosting {$whmcsHosting->domain}");
                        Log::info("Restoring hosting {$whmcsHosting->domain} for user {$userId}");
                        $hosting->restore();
                    }

                    // Update hosting data
                    $oldHostId = $hosting->host_id;
                    $oldUserId = $hosting->user_id;

                    $hosting->update([
                        'user_id' => $userId,
                        'host_id' => $host->id,
                        'username' => $whmcsHosting->username,
                    ]);

                    if ($oldUserId !== $userId) {
                        $this->debugOutput($command, "🔄 Updated user for {$whmcsHosting->domain} from {$oldUserId} to {$userId}");
                    }

                    // If host changed, update permissions
                    if ($oldHostId !== $host->id) {
                        $this->debugOutput($command, "🔄 Updating host for {$whmcsHosting->domain} from {$oldHostId} to {$host->id}");
                        Log::info("Updating host for {$whmcsHosting->domain} from {$oldHostId} to {$host->id}");
                        $user->hosts()->detach($oldHostId);
                    }
                }

                // Ensure host permissions
                $this->debugOutput($command, "🔐 Ensuring host permissions for user {$userId} on host {$host->id}");
                $user->hosts()->syncWithoutDetaching([$host->id => ['is_active' => true]]);

            } catch (Exception $e) {
                $this->debugOutput($command, "❌ Error processing hosting {$whmcsHosting->domain}: ".$e->getMessage());
                Log::error("Error processing hosting {$whmcsHosting->domain}: ".$e->getMessage());
                throw $e;
            }
        }

        // Deactivate hostings that are no longer active in WHMCS (but not manual ones)
        $activeDomains = $whmcsHostings->pluck('domain')->toArray();
        $inactiveHostings = Hosting::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('hosting_manual', false) // Only sync automatic hostings
            ->whereNotIn('domain', $activeDomains)
            ->get();

        if ($inactiveHostings->count() > 0) {
            $this->debugOutput($command, "🗑️ Found {$inactiveHostings->count()} hostings to deactivate");
        }

        foreach ($inactiveHostings as $inactiveHosting) {
            $this->debugOutput($command, "🗑️ Deactivating hosting {$inactiveHosting->domain}");
            Log::info("Deactivating hosting {$inactiveHosting->domain} for user {$userId}");
            $inactiveHosting->delete(); // Soft delete
        }

        // Final verification
        $activeHostings = Hosting::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->get();

        $this->debugOutput($command, "📊 Final count: {$activeHostings->count()} active hostings for user {$userId}");
        $this->debugOutput($command, '🌐 Active domains: '.$activeHostings->pluck('domain')->implode(', '));

        Log::info("Finished processing hostings for user {$userId}. Active hostings: ".
            $activeHostings->pluck('domain')->implode(', '));
    }

    /**
     * Run the synchronization as a console command
     *
     * @param  Command  $command  Command instance
     */
    public function asCommand(Command $command): void
    {
        try {
            $this->handle($command);
            $command->info('✅ Synchronization completed successfully');
        } catch (Exception $e) {
            $command->error('❌ Synchronization failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function debugOutput(?Command $command, string $message): void
    {
        if ($this->debugMode) {
            $this->output($command, "🐛 DEBUG: {$message}");
        }
    }
}
