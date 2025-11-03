<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="space-y-4">
        <!-- Info Box -->
        <div class="rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 p-4">
            <div class="flex items-start gap-3">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="h-5 w-5 text-primary-500 mt-0.5"
                />
                <div class="flex-1 text-sm">
                    <p class="font-medium text-gray-900 dark:text-white mb-1">
                        {{ __('hosts.ssh_keys.inline_help_title') }}
                    </p>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ __('hosts.ssh_keys.inline_help_description') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Generate Button -->
        <div class="flex items-center gap-3">
            {{ ($this->generateAction)(['fqdn' => $get('fqdn')]) }}

            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('hosts.ssh_keys.or_paste_existing') }}
            </span>
        </div>
    </div>
</x-dynamic-component>

