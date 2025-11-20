<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex-shrink-0">
                        <div class="h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary-600 dark:text-primary-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="max-width: 24px; max-height: 24px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ auth()->user()->email }}
                        </p>
                    </div>
                </div>

                <form wire:submit="save">
                    {{ $this->form }}

                    <div class="mt-6 flex justify-end">
                        <x-filament::button type="submit">
                            {{ __('user_profile.actions.save') }}
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400 shrink-0" viewBox="0 0 20 20" fill="currentColor" style="max-width: 20px; max-height: 20px;">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>{{ __('Note') }}:</strong> {{ __('user_profile.info.note') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

