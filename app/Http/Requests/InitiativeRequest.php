<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'featured_on_home' => $this->boolean('featured_on_home'),
            'remove_background' => $this->boolean('remove_background'),
            'remove_logo' => $this->boolean('remove_logo'),
            'order' => $this->input('order', 0) ?? 0,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['required', 'string', 'max:600'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'distinct', 'exists:categories,id'],
            'featured_on_home' => ['boolean'],
            'order' => ['integer', 'min:0', 'max:9999'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_background' => ['boolean'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'subtitle' => 'subtítulo',
            'url' => 'enlace',
            'categories' => 'ejes',
            'order' => 'orden',
            'background' => 'imagen de fondo',
            'logo' => 'logo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categories.required' => 'Seleccioná al menos un eje.',
            'categories.min' => 'Seleccioná al menos un eje.',
            'logo.mimes' => 'El logo debe ser una imagen PNG o JPG.',
        ];
    }
}
