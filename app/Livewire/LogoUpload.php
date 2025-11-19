<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\{Component, WithFileUploads};

class LogoUpload extends Component
{
    use WithFileUploads;

    public $logo;

    public ?User $user = null;

    public bool $uploading = false;

    public function mount(): void
    {
        $this->user = auth()->user();

        if (! $this->user || ! $this->user->is_admin) {
            abort(403, 'Unauthorized');
        }
    }

    public function updatedLogo(): void
    {
        $this->validate([
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,svg,webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=1000,max_height=1000',
            ],
        ], [
            'logo.image' => 'El archivo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser un archivo de tipo: png, jpg, jpeg, svg, webp.',
            'logo.max' => 'El logo no puede ser mayor a 2MB.',
            'logo.dimensions' => 'El logo debe tener un tamaño mínimo de 100x100px y máximo de 1000x1000px.',
        ]);
    }

    public function saveLogo(): void
    {
        if (! $this->user->is_admin) {
            abort(403, 'Unauthorized');
        }

        $this->validate([
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,svg,webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=1000,max_height=1000',
            ],
        ]);

        $this->uploading = true;

        try {
            // Delete old logo if exists
            if ($this->user->logo_path && Storage::disk('public')->exists($this->user->logo_path)) {
                Storage::disk('public')->delete($this->user->logo_path);
            }

            // Store new logo
            $path = $this->logo->store('logos', 'public');

            // Update user
            $this->user->update([
                'logo_path' => $path,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'title' => 'Logo actualizado',
                'description' => 'El logo se ha actualizado correctamente.',
            ]);

            $this->reset('logo');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'title' => 'Error',
                'description' => 'No se pudo actualizar el logo.',
            ]);
        } finally {
            $this->uploading = false;
        }
    }

    public function removeLogo(): void
    {
        if (! $this->user->is_admin) {
            abort(403, 'Unauthorized');
        }

        try {
            // Delete logo file
            if ($this->user->logo_path && Storage::disk('public')->exists($this->user->logo_path)) {
                Storage::disk('public')->delete($this->user->logo_path);
            }

            // Update user
            $this->user->update([
                'logo_path' => null,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'title' => 'Logo eliminado',
                'description' => 'El logo se ha eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'title' => 'Error',
                'description' => 'No se pudo eliminar el logo.',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.logo-upload');
    }
}
