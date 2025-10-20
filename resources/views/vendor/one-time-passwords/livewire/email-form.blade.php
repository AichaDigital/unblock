<div>
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        {{ __('one-time-passwords::form.email_form_title') }}
    </h2>

    <form wire:submit="submitEmail" class="mt-6 space-y-6">
        <div>
            <label for="email" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                {{ __('one-time-passwords::form.email_label') }}
            </label>
            <input
                class="p-2 mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-emerald-500 dark:focus:border-emerald-600 focus:ring-emerald-500 dark:focus:ring-emerald-600 rounded-md shadow-sm"
                id="email"
                type="email"
                wire:model="email"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                required
            >
            @error('email')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400 space-y-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submitEmail"
                class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-semibold text-white shadow-lg transition-all duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-95 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 focus-visible:outline-emerald-600"
                wire:loading.class="bg-emerald-400 cursor-not-allowed opacity-75"
            >
                <span wire:loading.remove wire:target="submitEmail">
                    {{ __('one-time-passwords::form.send_login_code_button') }}
                </span>
                <span wire:loading wire:target="submitEmail" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Enviando código...
                </span>
            </button>
        </div>
    </form>
</div>
