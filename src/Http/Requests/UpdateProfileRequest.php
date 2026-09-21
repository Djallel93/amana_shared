<?php
// src/Http/Requests/UpdateProfileRequest.php

declare(strict_types=1);

namespace Amana\Shared\Http\Requests;

use Amana\Shared\Support\PhoneFr;
use Illuminate\Foundation\Http\FormRequest;

/** Section « Mes informations » : nom, prénom, téléphone — et rien d'autre. */
class UpdateProfileRequest extends FormRequest
{
    protected $errorBag = 'informations';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $telephone = $this->input('telephone');
        $telephone = is_string($telephone) ? trim($telephone) : $telephone;

        $this->merge([
            'nom' => is_string($this->input('nom')) ? trim($this->input('nom')) : $this->input('nom'),
            'prenom' => is_string($this->input('prenom')) ? trim($this->input('prenom')) : $this->input('prenom'),
            'telephone' => $telephone === '' ? null : $telephone,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20', 'regex:' . PhoneFr::REGEX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 100 caractères.',
            'telephone.max' => 'Le téléphone ne doit pas dépasser 20 caractères.',
            'telephone.regex' => PhoneFr::MESSAGE,
        ];
    }
}
