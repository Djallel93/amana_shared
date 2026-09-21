<?php
// src/Models/Personne.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Modèle partagé — remplace User.php de Laravel pour toutes les apps AMANA.
 *
 * Vit dans amana_commun (ref_personnes). Chaque app se connecte à cette
 * table via la connexion 'commun' (voir config/amana-shared.php).
 *
 * Les relations et méthodes métier propres à UNE seule app (absences,
 * restrictions, créneaux, etc. — spécifiques à amana_web_planning) restent
 * dans l'app elle-même. Si un jour plusieurs apps ont besoin d'étendre ce
 * modèle, préférer un trait applicatif plutôt qu'un ajout ici.
 */
class Personne extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    MustVerifyEmailContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail, Notifiable;

    protected $table = 'ref_personnes';
    public $timestamps = false;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'date_debut_planning',
        'statut',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'date_debut_planning' => 'date',
        'email_verified_at' => 'datetime',
        'derniere_maj' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ref_personnes_roles', 'id_personne', 'id_role')
            ->withPivot('date_attribution');
    }

    /**
     * Surcharge de Illuminate\Notifications\HasDatabaseNotifications
     * (via le trait Notifiable ci-dessus) pour pointer vers
     * Amana\Shared\Models\Notification plutôt que le
     * Illuminate\Notifications\DatabaseNotification stock — seule façon
     * de récupérer les colonnes severity/resolved_at ajoutées par
     * create_notifications_table.php. Voir NotificationCenterService,
     * seul point d'entrée applicatif attendu pour lire ces données (ce
     * getter reste utilisable directement, notamment par le canal
     * 'amana-database', qui appelle notifications()->create()).
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\Amana\Shared\Models\Notification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Contrairement à Quartier/Famille (bases différentes, voir le
     * docblock de Quartier), BenevoleProfil vit dans amana_commun comme
     * Personne — la relation directe ne couple donc pas ce modèle partagé
     * à une seule app consommatrice, plusieurs apps AMANA pouvant y lire
     * un profil bénévole (familles aujourd'hui, amana_livraison demain).
     */
    public function benevoleProfil(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BenevoleProfil::class, 'id_personne');
    }

    // ── Rôles ─────────────────────────────────────────────────────────────
    //
    // appCode par défaut = config('amana-shared.app_code') de l'app courante
    // — chaque app ne teste ses propres rôles sans avoir à le répéter partout.

    public function hasRole(string $roleCode, ?string $appCode = null): bool
    {
        $appCode ??= config('amana-shared.app_code');

        return $this->roles()
            ->whereHas('application', fn($q) => $q->where('code', $appCode))
            ->where('ref_roles.code', $roleCode)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isGestionnaire(): bool
    {
        return $this->hasRole('gestionnaire');
    }

    /**
     * benevole est le rang le plus bas de la hiérarchie interne
     * (admin ⊇ gestionnaire ⊇ membre ⊇ benevole) — cascade donc depuis
     * isMembre(), qui cascade déjà depuis gestionnaire/admin. Voir le
     * docblock de Amana\Shared\Http\Middleware\EnsureRole pour la
     * hiérarchie complète et sa justification.
     */
    public function isBenevole(): bool
    {
        return $this->hasRole('benevole') || $this->isMembre();
    }

    /**
     * Rôle latéral (voir Amana\Shared\Http\Middleware\EnsureRole) — gère
     * des dossiers pour le compte d'une ou plusieurs organisations
     * partenaires (amana_web_familles, ajout du 28/08/2026), pas un rang
     * dans la hiérarchie admin/gestionnaire/membre/benevole. Volontairement
     * PAS inclus dans isGestionnaire()/isBenevole()/isMembre() ci-dessus :
     * un gestionnaire_externe n'hérite d'aucun accès interne.
     */
    public function isGestionnaireExterne(): bool
    {
        return $this->hasRole('gestionnaire_externe');
    }

    public function isMembre(): bool
    {
        return $this->hasRole('membre') || $this->isAdmin() || $this->isGestionnaire();
    }

    /**
     * Cascade identique à Amana\Shared\Http\Middleware\EnsureRole : admin
     * couvre gestionnaire/membre/benevole, gestionnaire couvre membre/
     * benevole, membre couvre benevole. Utilisé par le rendu de la sidebar
     * (config('amana-shared.nav')) pour filtrer les liens sans dupliquer
     * cette logique de cascade dans la vue.
     */
    public function hasAtLeastRole(string $role): bool
    {
        return match ($role) {
            'admin' => $this->isAdmin(),
            'gestionnaire' => $this->isAdmin() || $this->isGestionnaire(),
            'membre' => $this->isMembre(),
            'benevole' => $this->isBenevole(),
            default => false,
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeValide($query)
    {
        return $query->where('statut', 'Validé');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'En attente');
    }

    public function scopeAdminsDe($query, ?string $appCode = null)
    {
        $appCode ??= config('amana-shared.app_code');

        return $query->whereHas('roles', function ($q) use ($appCode) {
            $q->where('ref_roles.code', 'admin')
                ->whereHas('application', fn($q2) => $q2->where('code', $appCode));
        });
    }

    /**
     * Généralisation de scopeAdminsDe() à n'importe quel rôle — ajoutée le
     * 03/09/2026 pour les notifications ciblant un rôle donné (ex:
     * équipe_chargement, gestionnaire) plutôt qu'uniquement admin. Voir
     * NotificationCenterService et, côté amana_web_familles,
     * PackagingController::marquerPret()/RouteIncident (notifications).
     */
    public function scopeAvecRole($query, string $roleCode, ?string $appCode = null)
    {
        $appCode ??= config('amana-shared.app_code');

        return $query->whereHas('roles', function ($q) use ($roleCode, $appCode) {
            $q->where('ref_roles.code', $roleCode)
                ->whereHas('application', fn($q2) => $q2->where('code', $appCode));
        });
    }

    // ── Accesseurs ────────────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . strtoupper($this->nom);
    }

    /**
     * Initiales pour l'avatar (pastille de la sidebar, page « Mon profil ») :
     * première LETTRE du prénom + première LETTRE du nom, en majuscules.
     * Multi-octets (fonctions mb_ et classe \p{L}) : « Émilie » → « É », « Jean-Pierre » → « J »
     * (seule la première lettre de chaque champ compte), et les caractères
     * non alphabétiques en tête (« (Ali », « 'Omar ») sont ignorés. Champs
     * vides ou sans aucune lettre → « ? » plutôt qu'une pastille vide.
     */
    public function getInitialesAttribute(): string
    {
        $initiales = '';

        foreach ([$this->prenom, $this->nom] as $partie) {
            if (is_string($partie) && preg_match('/\p{L}/u', $partie, $m) === 1) {
                $initiales .= mb_substr(mb_strtoupper($m[0], 'UTF-8'), 0, 1, 'UTF-8');
            }
        }

        return $initiales !== '' ? $initiales : '?';
    }

    /**
     * Couleur de fond de l'avatar, déterministe à partir de l'ID — l'ID vient
     * de ref_personnes (base commune), donc une même personne a la MÊME
     * couleur dans toutes les apps AMANA. Teinte par angle d'or (137,508°)
     * pour que des IDs consécutifs soient bien distincts ; saturation 55 % et
     * luminosité 30 % : texte blanc lisible quelle que soit la teinte
     * (contraste ≥ 4,5:1, vérifié sur les 360° — voir PersonneAvatarTest),
     * et suffisamment clair pour se détacher du fond sombre `bg-sidebar`
     * avec l'anneau de la pastille. Valeur CSS complète, à poser en `style`
     * inline (aucune dépendance à un safelist Tailwind).
     */
    public function getCouleurAvatarAttribute(): string
    {
        $id = (int) $this->getKey();
        $teinte = $id > 0 ? (int) round(fmod($id * 137.508, 360.0)) : 210;

        return "hsl({$teinte}, 55%, 30%)";
    }

    /**
     * Email de réinitialisation de mot de passe (broker 'personnes') : version
     * française habillée AMANA (Notifications\ResetPasswordNotification) à la
     * place de l'email par défaut de Laravel. Une app qui surcharge cette
     * méthode dans son propre modèle garde la main.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->getEmailForPasswordReset()]);
        $minutes = (int) config('auth.passwords.personnes.expire', 60);

        $this->notify(new \Amana\Shared\Notifications\ResetPasswordNotification((string) $this->prenom, $url, $minutes));
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
