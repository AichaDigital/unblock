<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLogoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,svg,webp',
                'max:2048', // 2MB max
                'dimensions:min_width=100,min_height=100,max_width=1000,max_height=1000',
            ],
        ];
    }

    /**
     * Get custom validation messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'El archivo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser un archivo de tipo: png, jpg, jpeg, svg, webp.',
            'logo.max' => 'El logo no puede ser mayor a 2MB.',
            'logo.dimensions' => 'El logo debe tener un tamaño mínimo de 100x100px y máximo de 1000x1000px.',
        ];
    }
}
