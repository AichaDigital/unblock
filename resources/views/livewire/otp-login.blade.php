<div>
    <!-- Logo y título profesionales -->
    <div class="mb-6 text-center">
        <h2 class="mt-4 text-xl font-bold leading-9 tracking-tight text-gray-800 sm:text-2xl">
            {{ __('Solo cuentas de cliente') }}
        </h2>
    </div>

    @if(!$otpSent)
        <!-- Paso 1: Solicitar código -->
        <form wire:submit.prevent="sendOtp" class="space-y-6">
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text font-medium">{{ __('Correo electrónico') }}</span>
                </div>
                <label class="input input-bordered flex items-center gap-2">
                    <svg class="h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <input
                        type="email"
                        wire:model="email"
                        class="grow"
                        placeholder="{{ __('Cuenta de correo electrónico de usuario') }}"
                    />
                </label>
                <div class="label">
                    <span class="label-text-alt text-gray-500">{{ __('Cuenta de usuario o usuario autorizado') }}</span>
                </div>
            </label>

            <div class="mt-6">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendOtp"
                    @disabled($sendingOtp)
                    class="w-full inline-flex justify-center rounded-2xl px-6 py-3 text-base font-semibold text-white shadow-lg transition-all duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-95
                    @if($sendingOtp)
                        bg-emerald-400 cursor-not-allowed opacity-75
                    @else
                        bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 focus-visible:outline-emerald-600
                    @endif"
                >
                    <span wire:loading.remove wire:target="sendOtp">
                        @if($sendingOtp)
                            Enviando código...
                        @else
                            {{ __('Enviar código de acceso') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="sendOtp" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Enviando código...
                    </span>
                </button>
            </div>
        </form>
    @else
        <!-- Paso 2: Verificar código -->
        <form wire:submit.prevent="verifyOtp" class="space-y-6">
            <!-- Información del email -->
            <div class="text-center mb-6">
                <p class="text-sm text-gray-600">
                    Se ha enviado un código de 6 dígitos a:
                </p>
                <p class="font-semibold text-gray-800">{{ $email }}</p>
            </div>

            <!-- Campo OTP con auto-verificación -->
            <div class="space-y-4">
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text font-medium">Código de verificación</span>
                    </div>
                    <label class="input input-bordered flex items-center gap-2">
                        <svg class="h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                        </svg>
                        <input
                            type="text"
                            wire:model.live="oneTimePassword"
                            class="grow text-center text-lg tracking-widest font-mono"
                            placeholder="000000"
                            maxlength="6"
                            x-data="{
                                autoVerifyTimeout: null,
                                init() {
                                    this.$watch('$wire.oneTimePassword', (value) => {
                                        // Clear previous timeout
                                        if (this.autoVerifyTimeout) {
                                            clearTimeout(this.autoVerifyTimeout);
                                        }

                                        // If we have exactly 6 characters, auto-verify after 500ms
                                        if (value && value.length === 6 && !$wire.authenticating) {
                                            this.autoVerifyTimeout = setTimeout(() => {
                                                $wire.verifyOtp();
                                            }, 500);
                                        }
                                    });
                                }
                            }"
                        />
                    </label>
                    <div class="label">
                        <span class="label-text-alt text-gray-500">Introduce o pega el código de 6 dígitos que recibiste por email</span>
                    </div>
                </label>

                <!-- Indicador visual de progreso -->
                <div class="flex justify-center space-x-1">
                    @for($i = 1; $i <= 6; $i++)
                        <div class="w-3 h-1 rounded-full transition-colors duration-200
                            @if(strlen($oneTimePassword) >= $i)
                                bg-emerald-500
                            @else
                                bg-gray-200
                            @endif
                        "></div>
                    @endfor
                </div>
            </div>

            <div class="mt-6 space-y-3">
                <!-- Botón verificar (secundario, auto-submit es primario) -->
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="verifyOtp"
                    @disabled($authenticating || empty($oneTimePassword))
                    class="w-full inline-flex justify-center rounded-2xl px-6 py-3 text-base font-semibold text-white shadow-lg transition-all duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-95
                    @if($authenticating)
                        bg-emerald-400 cursor-not-allowed opacity-75
                    @elseif(empty($oneTimePassword))
                        bg-gray-400 cursor-not-allowed opacity-75
                    @else
                        bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 focus-visible:outline-emerald-600
                    @endif"
                >
                    <span wire:loading.remove wire:target="verifyOtp">
                        @if($authenticating)
                            <span class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Verificando...
                            </span>
                        @else
                            {{ __('Verificar código') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="verifyOtp" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verificando...
                    </span>
                </button>

                <!-- Texto explicativo sobre auto-verificación -->
                <p class="text-xs text-gray-500 text-center">
                    El código se verifica automáticamente al completar 6 dígitos
                </p>

                <!-- Botón reenviar -->
                <div class="text-center pt-2">
                    <button
                        type="button"
                        wire:click="resendOtp"
                        @disabled(!$canResend)
                        class="text-sm transition-colors duration-200
                        @if($canResend)
                            text-emerald-600 hover:text-emerald-700 underline cursor-pointer
                        @else
                            text-gray-400 cursor-not-allowed
                        @endif"
                    >
                        @if($canResend)
                            Reenviar código
                        @else
                            Puedes reenviar en 60 segundos
                        @endif
                    </button>
                </div>

                <!-- Botón volver -->
                <div class="text-center">
                    <button
                        type="button"
                        wire:click="resetForm"
                        class="text-sm text-gray-500 hover:text-gray-700 underline transition-colors duration-200"
                    >
                        Usar otra cuenta de email
                    </button>
                </div>
            </div>
        </form>
    @endif

    <!-- Información de ayuda (solo para usuarios no admin) -->
    @if(!$otpSent && !auth()->check())
        <div class="mt-8 border-t border-gray-200 pt-6">
            <details class="group">
                <summary class="flex cursor-pointer items-center justify-between text-sm font-medium text-gray-700 hover:text-gray-900">
                    <span>¿Necesitas ayuda?</span>
                    <svg class="h-5 w-5 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </summary>
                <div class="mt-3 text-sm text-gray-600 space-y-2">
                    <p>Este sistema de acceso utiliza códigos de un solo uso enviados por email para mayor seguridad.</p>
                    <p>Si no recibes el código:</p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>Revisa tu carpeta de spam</li>
                        <li>Verifica que el email sea correcto</li>
                        <li>Espera hasta 5 minutos</li>
                        <li>Contacta al soporte técnico si persiste el problema</li>
                    </ul>
                </div>
            </details>
        </div>
    @endif
</div>
