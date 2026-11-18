<?php

namespace App\Http\Requests;

use App\Services\Pfi\PfiMemberships;
use App\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PfiMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $member = User::query()->find($this->route('userId'));

        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [
                'integer',
                'distinct',
                Rule::in(app(PfiMemberships::class)->selectableCategories($member)->modelKeys()),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_values(array_map('intval', $this->validated('categories')));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categories' => 'categorías PFI',
            'categories.*' => 'categoría PFI',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categories.required' => 'Seleccioná al menos una categoría PFI.',
            'categories.min' => 'Seleccioná al menos una categoría PFI.',
            'categories.*.in' => 'Alguna de las categorías PFI seleccionadas no está disponible.',
        ];
    }
}
