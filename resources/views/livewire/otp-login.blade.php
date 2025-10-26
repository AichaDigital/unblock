<div>
    <!-- Logo y título profesionales -->
    <div class="mb-6 text-center">
        <h2 class="mt-4 text-xl font-bold leading-9 tracking-tight text-base-content sm:text-2xl">
            {{ $this->getTitle() }}
        </h2>
    </div>

    @if(!$otpSent)
        <!-- Paso 1: Solicitar código -->
        <form wire:submit.prevent="sendOtp" class="space-y-6">
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text font-medium">{{ __('Correo electrónico') }}</span>
                </div>
                <label class="input input-bordered flex items-center gap-2 w-full">
                    <svg class="h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <input
                        type="email"
                        wire:model="email"
                        class="grow"
                        placeholder="{{ $this->getEmailPlaceholder() }}"
                    />
                </label>
                <div class="label mt-2">
                    <span class="label-text-alt text-base-content/60 break-words">{{ $this->getEmailHelpText() }}</span>
                </div>
            </label>

            <div class="mt-6">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendOtp"
                    @disabled($sendingOtp)
                    class="btn btn-primary w-full rounded-2xl px-6 py-3 text-base font-semibold shadow-lg"
                >
                    <span wire:loading.remove wire:target="sendOtp">
                        @if($sendingOtp)
                            Enviando código...
                        @else
                            {{ __('Enviar código de acceso') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="sendOtp" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
                <p class="text-sm text-base-content/70">
                    Se ha enviado un código de 6 dígitos a:
                </p>
                <p class="font-semibold text-base-content">{{ $email }}</p>
            </div>

            <!-- Campo OTP con auto-verificación - 6 casillas separadas estilo Stripe -->
            <div class="space-y-4">
                <div class="form-control w-full">
                    <div class="label justify-center">
                        <span class="label-text font-medium">Código de verificación</span>
                    </div>

                    <!-- 6 casillas OTP estilo Stripe -->
                    <div class="flex flex-col items-center gap-4"
                         x-data="{
                             get code() {
                                 return $wire.oneTimePassword || '';
                             },
                             getDigit(index) {
                                 return this.code[index] || '';
                             },
                             focusInput() {
                                 $refs.otpInput.focus();
                             }
                         }"
                         x-init="
                             const handlePaste = (e) => {
                                 const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                                 const digits = pastedText.replace(/[^0-9]/g, '');
                                 if (digits.length === 6) {
                                     e.preventDefault();
                                     $wire.oneTimePassword = digits;
                                 }
                             };
                             document.addEventListener('paste', handlePaste);
                             $el.addEventListener('destroy', () => {
                                 document.removeEventListener('paste', handlePaste);
                             });
                         ">
                        <!-- Input real que captura todo -->
                        <div class="relative w-full max-w-sm mx-auto">
                            <input
                                x-ref="otpInput"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                wire:model.live="oneTimePassword"
                                x-init="
                                    $el.focus();
                                    $watch('$wire.oneTimePassword', (value) => {
                                        if (value && value.length === 6 && !$wire.authenticating) {
                                            setTimeout(() => $wire.verifyOtp(), 500);
                                        }
                                    });
                                "
                                @input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '')"
                                class="w-full h-0 opacity-0 absolute top-0"
                                style="width: 1px; height: 1px; position: absolute; left: 50%; caret-color: transparent;"
                                autocomplete="one-time-code"
                            />

                            <!-- 6 casillas visuales que muestran los dígitos -->
                            <div class="flex justify-center gap-2" @click="focusInput()">
                                @for($i = 0; $i < 6; $i++)
                                    <div
                                        class="input input-bordered w-12 h-14 flex items-center justify-center text-2xl font-mono font-bold cursor-text transition-all"
                                        :class="{ 'ring-2 ring-primary': code.length === {{ $i }} }"
                                    >
                                        <span x-text="getDigit({{ $i }})"></span>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div class="label mt-2">
                        <span class="label-text-alt text-base-content/60 break-words text-center w-full">Introduce o pega el código de 6 dígitos que recibiste por email</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                <!-- Botón verificar (secundario, auto-submit es primario) -->
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="verifyOtp"
                    @disabled($authenticating || empty($oneTimePassword))
                    class="btn btn-primary w-full rounded-2xl px-6 py-3 text-base font-semibold shadow-lg"
                >
                    <span wire:loading.remove wire:target="verifyOtp">
                        @if($authenticating)
                            <span class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Verificando...
                            </span>
                        @else
                            {{ __('Verificar código') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="verifyOtp" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verificando...
                    </span>
                </button>

                <!-- Texto explicativo sobre auto-verificación -->
                <p class="text-xs text-base-content/60 text-center">
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
                            text-primary hover:text-primary-focus underline cursor-pointer
                        @else
                            text-base-content/40 cursor-not-allowed
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
                        class="text-sm text-base-content/60 hover:text-base-content underline transition-colors duration-200"
                    >
                        Usar otra cuenta de email
                    </button>
                </div>
            </div>
        </form>
    @endif

    <!-- Información de ayuda (solo para usuarios no admin) -->
    @if(!$otpSent && !auth()->check())
        <div class="mt-8 border-t border-base-300 pt-6">
            <details class="group">
                <summary class="flex cursor-pointer items-center justify-between text-sm font-medium text-base-content hover:text-primary">
                    <span>¿Necesitas ayuda?</span>
                    <svg class="h-5 w-5 text-base-content/40 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </summary>
                <div class="mt-3 text-sm text-base-content/70 space-y-2">
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
