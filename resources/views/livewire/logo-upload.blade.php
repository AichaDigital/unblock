<div class="space-y-4">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-base-content">Logo personalizado</h3>
        <p class="text-sm text-base-content/60 mt-1">
            Sube tu logo para mostrarlo en las páginas de acceso (máx. 2MB, 100x100 a 1000x1000px)
        </p>
    </div>

    @if($user->logo_url)
        <!-- Preview del logo actual -->
        <div class="flex flex-col items-center gap-4 p-4 bg-base-200 rounded-lg">
            <div class="w-32 h-32 flex items-center justify-center bg-base-100 rounded-lg shadow-md p-2">
                <img src="{{ $user->logo_url }}" alt="Logo actual" class="max-w-full max-h-full object-contain">
            </div>
            <button
                type="button"
                wire:click="removeLogo"
                wire:loading.attr="disabled"
                class="btn btn-error btn-sm"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Eliminar logo
            </button>
        </div>
    @endif

    <!-- Form de upload -->
    <form wire:submit.prevent="saveLogo" class="space-y-4">
        <div class="form-control w-full">
            <label class="label">
                <span class="label-text font-medium">
                    {{ $user->logo_url ? 'Cambiar logo' : 'Subir logo' }}
                </span>
            </label>
            <input
                type="file"
                wire:model="logo"
                accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp"
                class="file-input file-input-bordered w-full"
                @disabled($uploading)
            />
            @error('logo')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror

            <!-- Loading indicator durante upload -->
            <div wire:loading wire:target="logo" class="label">
                <span class="label-text-alt text-info">Validando imagen...</span>
            </div>
        </div>

        <!-- Preview temporal del nuevo logo -->
        @if($logo && !$errors->has('logo'))
            <div class="flex flex-col items-center gap-2 p-4 bg-base-200 rounded-lg">
                <p class="text-sm font-medium text-base-content">Vista previa:</p>
                <div class="w-32 h-32 flex items-center justify-center bg-base-100 rounded-lg shadow-md p-2">
                    @if(method_exists($logo, 'temporaryUrl'))
                        <img src="{{ $logo->temporaryUrl() }}" alt="Vista previa" class="max-w-full max-h-full object-contain">
                    @endif
                </div>
            </div>
        @endif

        <!-- Botón de guardar -->
        @if($logo)
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="$set('logo', null)"
                    class="btn btn-ghost"
                    @disabled($uploading)
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="btn btn-primary"
                    @disabled($uploading)
                >
                    @if($uploading)
                        <span class="loading loading-spinner loading-sm"></span>
                        Guardando...
                    @else
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Guardar logo
                    @endif
                </button>
            </div>
        @endif
    </form>

    <!-- Información de requisitos -->
    <div class="text-xs text-base-content/60 space-y-1 bg-info/10 p-3 rounded-lg">
        <p class="font-semibold text-base-content/80">Requisitos:</p>
        <ul class="list-disc list-inside ml-2 space-y-0.5">
            <li>Formatos permitidos: PNG, JPG, JPEG, SVG, WEBP</li>
            <li>Tamaño máximo: 2MB</li>
            <li>Dimensiones: mínimo 100x100px, máximo 1000x1000px</li>
            <li>Recomendado: fondo transparente (PNG o SVG)</li>
        </ul>
    </div>
</div>
