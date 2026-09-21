<?php
// src/Http/Requests/RequestEmailChangeRequest.php

declare(strict_types=1);

namespace Amana\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Demande de changement d'adresse : nouvelle adresse + mot de passe actuel. */
class RequestEmailChangeRequest extends FormRequest
{
    protected $errorBag = 'email';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge(['email' => is_string($email) ? mb_strtolower(trim($email)) : $email]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Unicité sur la connexion PARTAGÉE, avec le préfixe de connexion :
        // sans lui, la règle interroge la table `ref_personnes` LOCALE de
        // l'app (vide) et laisse passer des doublons. Piège déjà rencontré.
        $connexion = config('amana-shared.connection', 'commun');
        $id = (int) $this->user()?->getAuthIdentifier();

        // email:rfc,dns comme le formulaire admin ; le contrôle DNS se
        // désactive (clé profile_email_dns) pour les environnements sans réseau.
        $format = config('amana-shared.profile_email_dns', true) ? 'email:rfc,dns' : 'email:rfc';

        return [
            'email' => ['required', $format, 'max:255', "unique:{$connexion}.ref_personnes,email,{$id}"],
            'current_password' => ['required', 'current_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "Format d'adresse email invalide.",
            'email.max' => "L'adresse email ne doit pas dépasser 255 caractères.",
            // Message neutre : ne confirme pas qu'une adresse est déjà inscrite.
            'email.unique' => 'Cette adresse ne peut pas être utilisée. Essayez-en une autre.',
            'current_password.required' => 'Saisissez votre mot de passe actuel.',
            'current_password.current_password' => 'Mot de passe actuel incorrect.',
        ];
    }
}
