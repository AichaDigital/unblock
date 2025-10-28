<div class="min-h-screen flex items-center justify-center bg-base-200 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-base-content">
                {{ __('simple_unblock.title') }}
            </h2>
            <p class="mt-2 text-center text-sm text-base-content/70">
                {{ __('simple_unblock.subtitle') }}
            </p>

            {{-- Progress indicator --}}
            @if (!$isOtpVerified)
            <div class="mt-4 flex justify-center items-center space-x-2">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-8 h-8 {{ $step === 1 ? 'bg-primary text-primary-content' : 'bg-success text-success-content' }} rounded-full">
                        @if ($step === 1)
                            1
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @endif
                    </div>
                    <span class="ml-2 text-xs font-medium {{ $step === 1 ? 'text-primary' : 'text-base-content/60' }}">
                        {{ __('simple_unblock.step1_label') }}
                    </span>
                </div>
                <div class="w-12 h-0.5 {{ $step === 2 ? 'bg-primary' : 'bg-base-300' }}"></div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-8 h-8 {{ $step === 2 ? 'bg-primary text-primary-content' : 'bg-base-300 text-base-content/60' }} rounded-full">
                        2
                    </div>
                    <span class="ml-2 text-xs font-medium {{ $step === 2 ? 'text-primary' : 'text-base-content/60' }}">
                        {{ __('simple_unblock.step2_label') }}
                    </span>
                </div>
            </div>
            @endif
        </div>

        @if ($message)
            <div class="alert {{ $messageType === 'success' ? 'alert-success' : 'alert-error' }}">
                <div class="flex">
                    <div class="flex-shrink-0">
                        @if ($messageType === 'success')
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">
                            {{ $message }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 1: Request OTP --}}
        @if ($step === 1)
            <form wire:submit="sendOtp" class="mt-8 space-y-6" x-data="{ honeypot: false }">
                <x-honeypot />

                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="form-control">
                            <label class="label" for="ip">
                                <span class="label-text">{{ __('simple_unblock.ip_label') }}</span>
                            </label>
                            <input
                                wire:model="ip"
                                id="ip"
                                type="text"
                                required
                                class="input input-bordered @error('ip') input-error @enderror"
                                placeholder="{{ __('simple_unblock.ip_placeholder') }}"
                            />
                            @error('ip')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label" for="domain">
                                <span class="label-text">{{ __('simple_unblock.domain_label') }}</span>
                            </label>
                            <input
                                wire:model="domain"
                                id="domain"
                                type="text"
                                required
                                class="input input-bordered @error('domain') input-error @enderror"
                                placeholder="{{ __('simple_unblock.domain_placeholder') }}"
                            />
                            @error('domain')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label" for="email">
                                <span class="label-text">{{ __('simple_unblock.email_label') }}</span>
                            </label>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                class="input input-bordered @error('email') input-error @enderror"
                                placeholder="{{ __('simple_unblock.email_placeholder') }}"
                            />
                            @error('email')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control mt-6">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="btn btn-primary"
                            >
                                <span wire:loading.remove>
                                    {{ __('simple_unblock.send_otp_button') }}
                                </span>
                                <span wire:loading>
                                    {{ __('simple_unblock.sending') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endif

        {{-- Step 2: Verify OTP --}}
        @if ($step === 2)
            <form wire:submit="verifyOtp" class="mt-8 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="form-control">
                            <label class="label" for="oneTimePassword">
                                <span class="label-text">{{ __('simple_unblock.otp_label') }}</span>
                            </label>
                            <input
                                wire:model="oneTimePassword"
                                id="oneTimePassword"
                                type="text"
                                required
                                maxlength="6"
                                pattern="[0-9]{6}"
                                class="input input-bordered text-center text-2xl tracking-widest @error('oneTimePassword') input-error @enderror"
                                placeholder="••••••"
                                autocomplete="one-time-code"
                            />
                            @error('oneTimePassword')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                            <label class="label">
                                <span class="label-text-alt text-base-content/70">
                                    {{ __('simple_unblock.otp_help') }}
                                </span>
                            </label>
                        </div>

                        <div class="form-control mt-6">
                            <div class="flex space-x-4">
                                <button
                                    type="button"
                                    wire:click="backToStep1"
                                    class="btn btn-outline flex-1"
                                >
                                    {{ __('simple_unblock.back_button') }}
                                </button>
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="btn btn-primary flex-1"
                                >
                                    <span wire:loading.remove>
                                        {{ __('simple_unblock.verify_button') }}
                                    </span>
                                    <span wire:loading>
                                        {{ __('simple_unblock.verifying') }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endif

        {{-- Direct processing form when OTP is already verified --}}
        @if ($isOtpVerified)
            <form wire:submit="processUnblock" class="mt-8 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="form-control">
                            <label class="label" for="ip">
                                <span class="label-text">{{ __('simple_unblock.ip_label') }}</span>
                            </label>
                            <input
                                wire:model="ip"
                                id="ip"
                                type="text"
                                required
                                class="input input-bordered @error('ip') input-error @enderror"
                                placeholder="{{ __('simple_unblock.ip_placeholder') }}"
                            />
                            @error('ip')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label" for="domain">
                                <span class="label-text">{{ __('simple_unblock.domain_label') }}</span>
                            </label>
                            <input
                                wire:model="domain"
                                id="domain"
                                type="text"
                                required
                                class="input input-bordered @error('domain') input-error @enderror"
                                placeholder="{{ __('simple_unblock.domain_placeholder') }}"
                            />
                            @error('domain')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control mt-6">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="btn btn-primary"
                            >
                                <span wire:loading.remove>
                                    {{ __('simple_unblock.process_button') }}
                                </span>
                                <span wire:loading>
                                    {{ __('simple_unblock.processing') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endif

        <div class="text-center">
            <p class="text-xs text-base-content/70">
                {{ __('simple_unblock.help_text') }}
            </p>
        </div>
    </div>
</div>
