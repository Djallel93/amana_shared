<?php
// src/Http/Requests/UpdatePasswordRequest.php

declare(strict_types=1);

namespace Amana\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement de mot de passe depuis « Mon profil ». min:8 comme le flux de
 * réinitialisation (le login partagé accepte 6, mais on ne crée plus de mots
 * de passe aussi courts).
 */
class UpdatePasswordRequest extends FormRequest
{
    protected $errorBag = 'password';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Saisissez votre mot de passe actuel.',
            'current_password.current_password' => 'Mot de passe actuel incorrect.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
            'password.different' => "Le nouveau mot de passe doit être différent de l'actuel.",
        ];
    }
}
