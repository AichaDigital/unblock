<?php

use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
use App\Actions\CheckFirewallAction;
use App\Models\{Host, Hosting, User};
use App\Traits\AuditLoginTrait;
use WireUi\Traits\WireUiActions;
use Illuminate\Support\Facades\DB;

new
#[Layout('components.layouts.app')]
#[Title('dashboard')]
class extends Component {
    use AuditLoginTrait, WireUiActions;

    // User and permissions
    public User $user;
    public bool $isAdmin = false;

    // Search configuration
    public string $searchTerm = '';

    // Available options
    public array $availableDomains = [];
    public array $availableServers = [];

    // Selected values - now unified
    public ?string $selectedType = null; // 'hosting' | 'host'
    public ?int $selectedId = null;

    // IP configuration
    public string $ipAddress = '';
    public string $detectedIp = '';
    public bool $showIpHelper = false;

    // Form state - Enhanced UX states
    public bool $showForm = true;
    public bool $isProcessing = false; // New state for immediate feedback
    public ?string $errorMessage = null;
    public ?string $lastProcessedIp = null; // Track last processed IP
    public ?string $lastProcessedTarget = null; // Track last processed target

    // User copy selection (admin only)
    public string $copyUserSearch = '';
    public ?int $selectedCopyUserId = null;
    public array $availableCopyUsers = [];
    public ?array $selectedCopyUserData = null;

    public function mount(): void
    {
        if (!auth()->check()) {
            abort(401);
        }

        $this->user = auth()->user();
        $this->isAdmin = $this->user->is_admin;

        // Detect client IP
        $this->detectedIp = $this->detectClientIp();
        $this->ipAddress = $this->detectedIp;

        // Load available options based on user permissions
        $this->loadAvailableOptions();
    }

    /**
     * Detect client IP address intelligently
     */
    private function detectClientIp(): string
    {
        // Check for IP from various headers (proxy-aware)
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Validate IP and ensure it's not private
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to request IP
        return request()->ip() ?? '0.0.0.0';
    }

    /**
     * Load available domains and servers based on user permissions
     */
    private function loadAvailableOptions(): void
    {
        if ($this->isAdmin) {
            // Admins see everything
            $this->loadAdminOptions();
        } else {
            // Regular users see only their authorized options
            $this->loadUserOptions();
        }
    }

    /**
     * Load all options for admin users
     */
    private function loadAdminOptions(): void
    {
        // All domains
        $this->availableDomains = Hosting::select(['id', 'domain', 'host_id'])
            ->whereNull('deleted_at')
            ->orderBy('domain')
            ->get()
            ->toArray();

        // All servers
        $this->availableServers = Host::select(['id', 'fqdn', 'alias'])
            ->whereNull('deleted_at')
            ->orderBy('fqdn')
            ->get()
            ->toArray();
    }

    /**
     * Load authorized options for regular users (differentiated logic for principal vs authorized users)
     */
    private function loadUserOptions(): void
    {
        $userId = $this->user->id;
        $parentUserId = $this->user->parent_user_id;

        if ($parentUserId) {
            // AUTHORIZED USER: Only see specifically assigned resources
            $this->loadAuthorizedUserOptions($userId);
        } else {
            // PRINCIPAL USER: See owned resources + specific permissions
            $this->loadPrincipalUserOptions($userId);
        }
    }

    /**
     * Load options for principal users (no parent_user_id)
     */
    private function loadPrincipalUserOptions(int $userId): void
    {
        // Get hostings owned by user OR with specific permissions
        $this->availableDomains = Hosting::where(function ($query) use ($userId) {
            // Hostings owned by user
            $query->where('user_id', $userId)
            // OR hostings with specific permissions for user
            ->orWhereHas('hostingPermissions', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId)
                    ->where('is_active', true);
            });
        })
        ->select(['id', 'domain', 'host_id'])
        ->whereNull('deleted_at')
        ->orderBy('domain')
        ->get()
        ->toArray();

        // Get hosts with direct permissions OR through owned hostings OR through specific hosting permissions
        $this->availableServers = Host::where(function ($query) use ($userId) {
            // Hosts with direct permissions for user
            $query->whereExists(function ($subQuery) use ($userId) {
                $subQuery->select(DB::raw(1))
                    ->from('user_host_permissions')
                    ->whereColumn('user_host_permissions.host_id', 'hosts.id')
                    ->where('user_host_permissions.user_id', $userId)
                    ->where('user_host_permissions.is_active', true);
            })
            // OR hosts with hosting-specific permissions for user
            ->orWhereHas('hostings.hostingPermissions', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId)
                    ->where('is_active', true);
            })
            // OR hosts owned by user through hostings
            ->orWhereHas('hostings', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId);
            });
        })
        ->select(['id', 'fqdn', 'alias'])
        ->whereNull('deleted_at')
        ->get()
        ->toArray();
    }

    /**
     * Load options for authorized users (with parent_user_id) - ONLY specifically assigned resources
     */
    private function loadAuthorizedUserOptions(int $userId): void
    {
        // Get ONLY hostings with specific permissions for this authorized user
        $this->availableDomains = Hosting::whereHas('hostingPermissions', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('is_active', true);
        })
        ->select(['id', 'domain', 'host_id'])
        ->whereNull('deleted_at')
        ->orderBy('domain')
        ->get()
        ->toArray();

        // Get ONLY hosts with direct permissions for this authorized user
        $this->availableServers = Host::whereExists(function ($query) use ($userId) {
            $query->select(DB::raw(1))
                ->from('user_host_permissions')
                ->whereColumn('user_host_permissions.host_id', 'hosts.id')
                ->where('user_host_permissions.user_id', $userId)
                ->where('user_host_permissions.is_active', true);
        })
        ->select(['id', 'fqdn', 'alias'])
        ->whereNull('deleted_at')
        ->get()
        ->toArray();
    }

    /**
     * Select a hosting domain
     */
    public function selectHosting(int $hostingId): void
    {
        $this->selectedType = 'hosting';
        $this->selectedId = $hostingId;
        $this->searchTerm = '';
    }

    /**
     * Select a host server
     */
    public function selectHost(int $hostId): void
    {
        $this->selectedType = 'host';
        $this->selectedId = $hostId;
        $this->searchTerm = '';
    }

    /**
     * Clear selection
     */
    public function clearSelection(): void
    {
        $this->selectedType = null;
        $this->selectedId = null;
    }

    /**
     * Toggle IP helper visibility
     */
    public function toggleIpHelper(): void
    {
        $this->showIpHelper = !$this->showIpHelper;
    }

    /**
     * Use the detected IP address
     */
    public function useDetectedIp(): void
    {
        $this->ipAddress = $this->detectedIp;
        $this->showIpHelper = false;
    }

    /**
     * Search for users to send copy (admin only)
     */
    public function searchCopyUsers(): void
    {
        // Trim whitespace and validate minimum length
        $searchTerm = trim($this->copyUserSearch);

        if (!$this->isAdmin || strlen($searchTerm) < 2) {
            $this->availableCopyUsers = [];
            return;
        }

        $this->availableCopyUsers = User::where(function ($query) use ($searchTerm) {
            $query->where('first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('email', 'like', '%' . $searchTerm . '%')
                ->orWhere('company_name', 'like', '%' . $searchTerm . '%');
        })
        ->where('id', '!=', $this->user->id) // Don't include current user
        ->select(['id', 'first_name', 'last_name', 'email', 'company_name'])
        ->limit(10)
        ->get()
        ->toArray();
    }

    /**
     * Select a user to receive copy of the report
     */
    public function selectCopyUser(int $userId): void
    {
        $this->selectedCopyUserId = $userId;

        // Store the selected user data before clearing the search results
        $selectedUser = collect($this->availableCopyUsers)->firstWhere('id', $userId);
        $this->selectedCopyUserData = $selectedUser ? $selectedUser : null;

        $this->copyUserSearch = '';
        $this->availableCopyUsers = [];
    }

    /**
     * Clear copy user selection
     */
    public function clearCopyUser(): void
    {
        $this->selectedCopyUserId = null;
        $this->selectedCopyUserData = null;
        $this->copyUserSearch = '';
        $this->availableCopyUsers = [];
    }

    /**
     * Submit a firewall check form
     */
    public function submitForm(): void
    {
        // Immediate feedback - disable button and show processing
        $this->isProcessing = true;
        $this->errorMessage = null;

        // Dispatch event to reset Alpine.js state on validation errors
        $this->dispatch('reset-submitting-state');

        // Validation rules
        $rules = [
            'ipAddress' => 'required|ip',
            'selectedType' => 'required|in:hosting,host',
            'selectedId' => 'required|integer',
        ];

        // Custom validation messages
        $messages = [
            'selectedType.required' => __('firewall.validation.selection_required'),
            'selectedId.required' => __('firewall.validation.selection_required'),
            'ipAddress.required' => __('firewall.validation.ip_required'),
            'ipAddress.ip' => __('firewall.validation.invalid_ip'),
        ];

        try {
            $validatedData = $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isProcessing = false;
            throw $e;
        }

        try {
            // Determine the target host
            $targetHost = null;
            $targetName = '';

            if ($this->selectedType === 'hosting') {
                $hosting = collect($this->availableDomains)
                    ->firstWhere('id', $this->selectedId);

                if ($hosting) {
                    $targetHost = Host::find($hosting['host_id']);
                    $targetName = $hosting['domain'];
                }
            } else if ($this->selectedType === 'host') {
                $targetHost = Host::find($this->selectedId);
                if ($targetHost) {
                    $targetName = $targetHost->fqdn;
                }
            }

            if (!$targetHost) {
                throw new \Exception(__('firewall.errors.invalid_target'));
            }

            // Store processed information for display
            $this->lastProcessedIp = $validatedData['ipAddress'];
            $this->lastProcessedTarget = $targetName;

            // Execute firewall check, which now dispatches a job
            app(\App\Actions\CheckFirewallAction::class)->handle(
                ip: $validatedData['ipAddress'],
                userId: $this->user->id,
                hostId: $targetHost->id,
                copyUserId: $this->selectedCopyUserId // Pass the selected copy user ID
            );

            // Since the job is dispatched, we show a standard success message.
            // The user will be notified upon completion.
            $this->notification()->success(
                __('firewall.notifications.query_sent_title'),
                __('firewall.notifications.query_sent_async') // A new translation key for the async message
            );

            $this->showForm = false;
            $this->isProcessing = false;

        } catch (\Exception $e) {
            $this->isProcessing = false;
            $this->errorMessage = __('firewall.errors.process_error');
            $this->notification()->error(
                __('firewall.notifications.error_title'),
                $this->errorMessage
            );
        }
    }

    /**
     * Start a new firewall check (better semantic than "reload")
     */
    public function startNewCheck(): void
    {
        $this->showForm = true;
        $this->isProcessing = false;
        $this->errorMessage = null;
        $this->searchTerm = '';
        $this->selectedType = null;
        $this->selectedId = null;
        $this->showIpHelper = false;
        $this->lastProcessedIp = null;
        $this->lastProcessedTarget = null;

        // Clear copy user selection
        $this->clearCopyUser();

        // Clear IP but keep detected IP available
        $this->ipAddress = $this->detectedIp;
    }

    /**
     * Legacy method for backward compatibility
     */
    public function reload(): void
    {
        $this->startNewCheck();
        $this->redirect(route('dashboard'));
    }

}; ?>

<div class="relative z-10" x-data="{ open: true }" x-show="open">
    <!-- Background backdrop -->
    <div class="fixed inset-0 bg-gray-900/20 transition-opacity"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <!-- Modal panel -->
            <div class="relative w-full max-w-lg transform overflow-hidden rounded-lg bg-white px-4 py-6 text-left shadow-2xl ring-1 ring-black/10 transition-all sm:px-6 sm:py-8">
                @if ($showForm)
                    <div>
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.25-8.25-3.285Z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">
                                {{ $isAdmin ? __('firewall.service.admin_title') : __('firewall.service.title') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{ __('firewall.service.description') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if ($errorMessage)
                        <div class="mt-4 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-800">{{ $errorMessage }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                                        <form wire:submit.prevent="submitForm" class="mt-6 space-y-6"
                          x-data="{
                              isSubmitting: false,
                              init() {
                                  // Listen for validation errors to reset state
                                  $wire.on('reset-submitting-state', () => {
                                      setTimeout(() => {
                                          this.isSubmitting = false;
                                      }, 100);
                                  });
                              }
                          }"
                          @submit="isSubmitting = true"
                          x-bind:class="{ 'pointer-events-none': isSubmitting }"
                          wire:loading.class="pointer-events-none"
                          wire:target="submitForm">
                        @csrf

                        <!-- Service/Server Selection with Command Palette -->
                        <div x-data="{
                            searchTerm: @entangle('searchTerm'),
                            selectedType: @entangle('selectedType'),
                            selectedId: @entangle('selectedId'),
                            availableDomains: @js($availableDomains),
                            availableServers: @js($availableServers),
                            showDomainHelp: false,
                            showServerHelp: false,
                            get filteredDomains() {
                                if (!this.searchTerm) return this.availableDomains;
                                return this.availableDomains.filter(domain =>
                                    domain.domain.toLowerCase().includes(this.searchTerm.toLowerCase())
                                );
                            },
                            get filteredServers() {
                                if (!this.searchTerm) return this.availableServers;
                                return this.availableServers.filter(server =>
                                    server.fqdn.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                                    (server.alias && server.alias.toLowerCase().includes(this.searchTerm.toLowerCase()))
                                );
                            },
                            get selectedItem() {
                                if (this.selectedType === 'hosting') {
                                    return this.availableDomains.find(d => d.id == this.selectedId);
                                } else if (this.selectedType === 'host') {
                                    return this.availableServers.find(s => s.id == this.selectedId);
                                }
                                return null;
                            }
                        }">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">
                                        {{ __('firewall.search.select_target') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="button"
                                                @click="showDomainHelp = !showDomainHelp"
                                                class="text-xs text-indigo-600 hover:text-indigo-500">
                                            {{ __('firewall.help.domain_explanation.title') }}
                                        </button>
                                        <button type="button"
                                                @click="showServerHelp = !showServerHelp"
                                                class="text-xs text-indigo-600 hover:text-indigo-500">
                                            {{ __('firewall.help.server_explanation.title') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Domain Help -->
                                <div x-show="showDomainHelp" class="mb-4 rounded-md bg-blue-50 p-4">
                                    <h4 class="font-medium text-blue-800 mb-2">{{ __('firewall.help.domain_explanation.title') }}</h4>
                                    <p class="text-sm text-blue-700 mb-2">{{ __('firewall.help.domain_explanation.description') }}</p>
                                    <p class="text-sm text-blue-600 mb-2">{{ __('firewall.help.domain_explanation.examples') }}</p>
                                    <p class="text-xs text-blue-500">{{ __('firewall.help.domain_explanation.note') }}</p>
                                </div>

                                <!-- Server Help -->
                                <div x-show="showServerHelp" class="mb-4 rounded-md bg-green-50 p-4">
                                    <h4 class="font-medium text-green-800 mb-2">{{ __('firewall.help.server_explanation.title') }}</h4>
                                    <p class="text-sm text-green-700 mb-2">{{ __('firewall.help.server_explanation.description') }}</p>
                                    <p class="text-sm text-green-600 mb-2">{{ __('firewall.help.server_explanation.examples') }}</p>
                                    <p class="text-xs text-green-500">{{ __('firewall.help.server_explanation.note') }}</p>
                                </div>

                                <div class="mt-2">
                                    <!-- Selected item display -->
                                    <div x-show="selectedType && selectedId" class="rounded-md bg-blue-50 px-4 py-3 mb-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <svg x-show="selectedType === 'hosting'" class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.03 7.03 0 0 0 2.255 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12q.208.58.468 1.068c.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.487.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z" />
                                                </svg>
                                                <svg x-show="selectedType === 'host'" class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 512 512">
                                                    <path d="M64 32C28.7 32 0 60.7 0 96l0 64c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-64c0-35.3-28.7-64-64-64L64 32zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm48 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM64 288c-35.3 0-64 28.7-64 64l0 64c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-64c0-35.3-28.7-64-64-64L64 288zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm56 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/>
                                                </svg>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <p class="text-sm font-medium text-blue-800">
                                                    <span x-show="selectedType === 'hosting'" x-text="selectedItem?.domain"></span>
                                                    <span x-show="selectedType === 'host'" x-text="selectedItem?.fqdn"></span>
                                                </p>
                                                <p class="text-xs text-blue-600" x-show="selectedType === 'host' && selectedItem?.alias" x-text="selectedItem?.alias"></p>
                                            </div>
                                            <button type="button" wire:click="clearSelection" class="flex-shrink-0">
                                                <svg class="h-4 w-4 text-blue-400 hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Search input -->
                                    <div x-show="!selectedType || !selectedId">
                                        <input type="text"
                                               x-model.debounce.300ms="searchTerm"
                                               class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                               placeholder="{{ __('firewall.search.placeholder') }}">

                                        <!-- Command palette results -->
                                        <div x-show="searchTerm" class="mt-2 max-h-64 overflow-y-auto rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                                            <!-- Hostings Group -->
                                            <div x-show="filteredDomains.length > 0">
                                                <div class="bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                    {{ __('firewall.groups.hostings') }}
                                                </div>
                                                <template x-for="hosting in filteredDomains.slice(0, 5)" :key="'hosting-' + hosting.id">
                                                    <button type="button"
                                                            class="group flex w-full items-center px-3 py-2 text-left text-sm hover:bg-gray-100 border-b border-gray-100 last:border-b-0"
                                                            @click="$wire.selectHosting(hosting.id)">
                                                        <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-600" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.03 7.03 0 0 0 2.255 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12q.208.58.468 1.068c.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.487.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z" />
                                                        </svg>
                                                        <span class="font-medium text-gray-900" x-text="hosting.domain"></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <!-- Servers Group -->
                                            <div x-show="filteredServers.length > 0">
                                                <div class="bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                    {{ __('firewall.groups.servers') }}
                                                </div>
                                                <template x-for="server in filteredServers.slice(0, 5)" :key="'server-' + server.id">
                                                    <button type="button"
                                                            class="group flex w-full items-center px-3 py-2 text-left text-sm hover:bg-gray-100 border-b border-gray-100 last:border-b-0"
                                                            @click="$wire.selectHost(server.id)">
                                                        <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-600" fill="currentColor" viewBox="0 0 512 512">
                                                            <path d="M64 32C28.7 32 0 60.7 0 96l0 64c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-64c0-35.3-28.7-64-64-64L64 32zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm48 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM64 288c-35.3 0-64 28.7-64 64l0 64c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-64c0-35.3-28.7-64-64-64L64 288zm280 72a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm56 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/>
                                                        </svg>
                                                        <div class="flex-1">
                                                            <span class="font-medium text-gray-900" x-text="server.fqdn"></span>
                                                            <div x-show="server.alias" class="text-xs text-gray-500" x-text="server.alias"></div>
                                                        </div>
                                                    </button>
                                                </template>
                                            </div>

                                            <!-- No results -->
                                            <div x-show="filteredDomains.length === 0 && filteredServers.length === 0" class="px-3 py-2 text-sm text-gray-500">
                                                {{ __('firewall.search.no_results') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- IP Address Section -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="ipAddress" class="block text-sm font-medium leading-6 text-gray-900">
                                    {{ __('firewall.ip.label') }}
                                </label>
                                @if (!$isAdmin)
                                    <button type="button"
                                            wire:click="toggleIpHelper"
                                            class="text-xs text-indigo-600 hover:text-indigo-500">
                                        {{ __('firewall.help.need_help') }}
                                    </button>
                                @endif
                            </div>

                            <!-- IP Helper (only for non-admin users) -->
                            @if (!$isAdmin && $showIpHelper)
                                <div class="mb-4 rounded-md bg-blue-50 p-4">
                                    <h4 class="font-medium text-blue-800 mb-2">{{ __('firewall.help.ip_explanation.title') }}</h4>
                                    <p class="text-sm text-blue-700 mb-2">{{ __('firewall.help.ip_explanation.description') }}</p>

                                    <div class="bg-blue-100 rounded-md p-3 mb-3">
                                        <h5 class="font-medium text-blue-800 mb-2">{{ __('firewall.help.ip_explanation.what_is_ip') }}</h5>
                                        <p class="text-sm text-blue-600 mb-2">{{ __('firewall.help.ip_explanation.why_default') }}</p>
                                    </div>

                                    <div class="text-sm text-blue-600 mb-2">
                                        <p class="mb-1"><strong>{{ __('firewall.help.ip_explanation.current_ip') }}</strong></p>
                                        <p class="mb-1"><strong>{{ __('firewall.help.ip_explanation.problem_ip') }}</strong></p>
                                    </div>

                                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-3">
                                        <p class="text-sm text-yellow-800 font-medium mb-1">{{ __('firewall.help.ip_explanation.example') }}</p>
                                        <p class="text-xs text-yellow-700">{{ __('firewall.help.ip_explanation.note') }}</p>
                                    </div>

                                    <div class="border-t border-blue-200 pt-3">
                                        <h5 class="font-medium text-blue-800 mb-2">{{ __('firewall.help.ip_detection.title') }}</h5>
                                        <ul class="text-sm text-blue-700 space-y-1 mb-3">
                                            <li>• {{ __('firewall.help.ip_detection.current_device') }}</li>
                                            <li>· {!! __('firewall.help.ip_detection.how_to_find', ['url' => '<a href="https://whatismyipaddress.com" target="_blank">whatismyipaddress.com</a>']) !!}</li>
                                            <li>• {{ __('firewall.help.ip_detection.common_issues') }}</li>
                                        </ul>
                                        @if ($detectedIp !== '0.0.0.0')
                                            <button type="button"
                                                    wire:click="useDetectedIp"
                                                    class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                                                {{ __('firewall.help.ip_detection.use_detected', ['ip' => $detectedIp]) }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <input type="text"
                                   id="ipAddress"
                                   wire:model="ipAddress"
                                   class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                   placeholder="{{ __('firewall.ip.placeholder') }}">

                            @error('ipAddress')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('selectedType')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('selectedId')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Copy User Selection (Admin only) -->
                        @if ($isAdmin)
                                                        <div x-data="{
                                copyUserSearch: @entangle('copyUserSearch'),
                                selectedCopyUserId: @entangle('selectedCopyUserId'),
                                availableCopyUsers: @entangle('availableCopyUsers'),
                                selectedCopyUserData: @entangle('selectedCopyUserData'),
                                formatUserName(user) {
                                    if (!user) return '';
                                    const firstName = user.first_name || '';
                                    const lastName = user.last_name || '';
                                    return (firstName + (lastName ? ' ' + lastName : '')).trim();
                                }
                            }"
                            x-init="$watch('copyUserSearch', value => $wire.searchCopyUsers())"
                            >
                                <div>
                                    <label class="block text-sm font-medium leading-6 text-gray-900">
                                        {{ __('firewall.copy_report.title') }} <span class="text-gray-500">{{ __('firewall.copy_report.optional') }}</span>
                                    </label>
                                    <p class="mt-1 text-xs text-gray-600">{{ __('firewall.copy_report.description') }}</p>

                                    <!-- Selected user display -->
                                    <div x-show="selectedCopyUserId && selectedCopyUserData" class="mt-2 rounded-md bg-blue-50 px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <p class="text-sm font-medium text-blue-800" x-text="formatUserName(selectedCopyUserData)"></p>
                                                <p class="text-xs text-blue-600" x-text="selectedCopyUserData?.email || ''"></p>
                                                <p x-show="selectedCopyUserData?.company_name" class="text-xs text-blue-500" x-text="selectedCopyUserData?.company_name || ''"></p>
                                            </div>
                                            <button type="button" wire:click="clearCopyUser" class="flex-shrink-0">
                                                <svg class="h-4 w-4 text-blue-400 hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Search input -->
                                    <div x-show="!selectedCopyUserId || !selectedCopyUserData" class="mt-2">
                                        <input type="text"
                                               x-model.debounce.500ms="copyUserSearch"
                                               class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                               placeholder="{{ __('firewall.copy_report.search_placeholder') }}">

                                        <!-- User search results -->
                                        <div x-show="copyUserSearch && availableCopyUsers.length > 0" class="mt-2 max-h-48 overflow-y-auto rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                                            <template x-for="user in availableCopyUsers" :key="'copy-user-' + user.id">
                                                <button type="button"
                                                        class="group flex w-full items-center px-3 py-2 text-left text-sm hover:bg-gray-100 border-b border-gray-100 last:border-b-0"
                                                        @click="$wire.selectCopyUser(user.id)">
                                                    <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                                                    </svg>
                                                    <div class="flex-1">
                                                        <span class="font-medium text-gray-900" x-text="formatUserName(user)"></span>
                                                        <div class="text-xs text-gray-500" x-text="user.email || ''"></div>
                                                        <div x-show="user.company_name" class="text-xs text-gray-400" x-text="user.company_name || ''"></div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>

                                        <!-- No results -->
                                        <div x-show="copyUserSearch && availableCopyUsers.length === 0 && copyUserSearch.length >= 2" class="mt-2 px-3 py-2 text-sm text-gray-500">
                                            {{ __('firewall.copy_report.no_users_found') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Action buttons -->
                        <div class="mt-6 -mx-4 -mb-6 px-4 py-4 bg-gray-50 rounded-b-lg sm:-mx-6 sm:-mb-8 sm:px-6 sm:py-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end sm:gap-3">
                                <button type="button"
                                        @click="open = false"
                                        x-bind:disabled="isSubmitting || @js($isProcessing)"
                                        x-bind:class="{ 'opacity-50 cursor-not-allowed': isSubmitting || @js($isProcessing) }"
                                        class="w-full inline-flex justify-center rounded-2xl bg-white px-6 py-3 text-base font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-400 active:scale-95 sm:w-auto">
                                    {{ __('firewall.actions.cancel') }}
                                </button>
                                                                <button type="submit"
                                        x-bind:disabled="isSubmitting || @js($isProcessing)"
                                        class="w-full inline-flex justify-center rounded-2xl px-6 py-3 text-base font-semibold text-white shadow-lg transition-all duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 active:scale-95 sm:w-auto"
                                        x-bind:class="{
                                            'bg-gray-400 cursor-not-allowed': isSubmitting || @js($isProcessing),
                                            'bg-blue-600 hover:bg-blue-500 hover:shadow-xl hover:scale-105': !(isSubmitting || @js($isProcessing))
                                        }"
                                        wire:loading.attr="disabled"
                                        wire:target="submitForm">
                                    <template x-if="isSubmitting || @js($isProcessing)">
                                        <div class="flex items-center">
                                            <svg class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            {{ __('firewall.actions.processing') }}
                                        </div>
                                    </template>
                                    <template x-if="!(isSubmitting || @js($isProcessing))">
                                        <div class="flex items-center">
                                            <svg class="mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.25-8.25-3.285Z" />
                                            </svg>
                                            {{ __('firewall.actions.check') }}
                                        </div>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    <!-- Request Submitted Successfully -->
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.25-8.25-3.285Z" />
                            </svg>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('firewall.status.request_submitted') }}</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{ __('firewall.status.submitted_message') }}
                                </p>
                                @if ($lastProcessedIp && $lastProcessedTarget)
                                    <div class="mt-3 bg-gray-50 rounded-md p-3 text-left">
                                        <dl class="text-sm">
                                            <div class="mb-1">
                                                <dt class="inline font-medium text-gray-700">{{ __('firewall.status.ip_checked') }}: </dt>
                                                <dd class="inline font-mono text-gray-900">{{ $lastProcessedIp }}</dd>
                                            </div>
                                            <div>
                                                <dt class="inline font-medium text-gray-700">{{ __('firewall.status.target_checked') }}: </dt>
                                                <dd class="inline text-gray-900">{{ $lastProcessedTarget }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-6 -mx-4 -mb-6 px-4 py-4 bg-gray-50 rounded-b-lg text-center sm:-mx-6 sm:-mb-8 sm:px-6 sm:py-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:justify-center sm:gap-3">
                                <button type="button"
                                        @click="open = false"
                                        class="w-full inline-flex justify-center rounded-2xl bg-white px-6 py-3 text-base font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-400 active:scale-95 sm:w-auto">
                                    <svg class="mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    {{ __('firewall.actions.close') }}
                                </button>
                                <button type="button"
                                        wire:click="startNewCheck"
                                        class="w-full inline-flex justify-center rounded-2xl bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg transition-all duration-200 hover:bg-blue-500 hover:shadow-xl hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 active:scale-95 sm:w-auto">
                                    <svg class="mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ __('firewall.actions.new_check') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
