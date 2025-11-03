<div class="flex items-center justify-center bg-base-200 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">
        {{-- Header --}}
        <div class="text-center">
            <h2 class="text-3xl font-bold text-base-content">
                {{ __('admin_otp.title') }}
            </h2>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('admin_otp.subtitle') }}
            </p>
        </div>

        {{-- Messages --}}
        @if($message)
            <div class="alert @if($messageType === 'success') alert-success @else alert-error @endif">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    @if($messageType === 'success')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @endif
                </svg>
                <span>{{ $message }}</span>
            </div>
        @endif

        {{-- OTP Form --}}
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <form wire:submit="verify" class="space-y-6">
                    {{-- OTP Code Input --}}
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('admin_otp.code_label') }}</span>
                        </label>
                        <input
                            type="text"
                            wire:model="code"
                            class="input input-bordered input-lg text-center text-2xl tracking-widest font-mono @error('code') input-error @enderror"
                            placeholder="000000"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            autofocus
                            @disabled($processing)
                        >
                        @error('code')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        <label class="label">
                            <span class="label-text-alt text-base-content/60">
                                {{ __('admin_otp.code_help') }}
                            </span>
                        </label>
                    </div>

                    {{-- Verify Button --}}
                    <div class="form-control">
                        <button
                            type="submit"
                            class="btn btn-primary btn-block"
                            @disabled($processing)
                        >
                            @if($processing)
                                <span class="loading loading-spinner"></span>
                                {{ __('admin_otp.verifying') }}
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('admin_otp.verify_button') }}
                            @endif
                        </button>
                    </div>
                </form>

                <div class="divider">{{ __('admin_otp.or') }}</div>

                {{-- Resend Button --}}
                <button
                    wire:click="resend"
                    class="btn btn-outline btn-block"
                    @disabled(!$canResend || $processing)
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    @if($canResend)
                        {{ __('admin_otp.resend_button') }}
                    @else
                        {{ __('admin_otp.resend_wait') }}
                    @endif
                </button>

                {{-- Cancel Button --}}
                <button
                    wire:click="cancel"
                    class="btn btn-ghost btn-sm btn-block mt-4"
                    @disabled($processing)
                >
                    {{ __('admin_otp.cancel_button') }}
                </button>
            </div>
        </div>

        {{-- Help Text --}}
        <div class="text-center text-sm text-base-content/60">
            <p>{{ __('admin_otp.help_text') }}</p>
        </div>
    </div>
</div>

