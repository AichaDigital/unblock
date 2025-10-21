<div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                {{ __('simple_unblock.title') }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                {{ __('simple_unblock.subtitle') }}
            </p>
        </div>

        @if ($message)
            <div class="rounded-md p-4 {{ $messageType === 'success' ? 'bg-green-50' : 'bg-red-50' }}">
                <div class="flex">
                    <div class="flex-shrink-0">
                        @if ($messageType === 'success')
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium {{ $messageType === 'success' ? 'text-green-800' : 'text-red-800' }}">
                            {{ $message }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit="submit" class="mt-8 space-y-6">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="ip" class="sr-only">{{ __('simple_unblock.ip_label') }}</label>
                    <input
                        wire:model="ip"
                        id="ip"
                        type="text"
                        required
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm @error('ip') border-red-500 @enderror"
                        placeholder="{{ __('simple_unblock.ip_placeholder') }}"
                    />
                    @error('ip')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="domain" class="sr-only">{{ __('simple_unblock.domain_label') }}</label>
                    <input
                        wire:model="domain"
                        id="domain"
                        type="text"
                        required
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm @error('domain') border-red-500 @enderror"
                        placeholder="{{ __('simple_unblock.domain_placeholder') }}"
                    />
                    @error('domain')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="sr-only">{{ __('simple_unblock.email_label') }}</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        required
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm @error('email') border-red-500 @enderror"
                        placeholder="{{ __('simple_unblock.email_placeholder') }}"
                    />
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                    <span wire:loading.remove>{{ __('simple_unblock.submit_button') }}</span>
                    <span wire:loading class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('simple_unblock.processing') }}
                    </span>
                </button>
            </div>
        </form>

        <div class="text-center">
            <p class="text-xs text-gray-500">
                {{ config('company.name') }}
            </p>
        </div>
    </div>
</div>
