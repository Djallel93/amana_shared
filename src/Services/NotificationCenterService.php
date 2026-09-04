<?php
// src/Services/NotificationCenterService.php

declare(strict_types=1);

namespace Amana\Shared\Services;

use Amana\Shared\Models\Notification;
use Amana\Shared\Models\Personne;
use Illuminate\Support\Collection;

/**
 * Point d'entrée applicatif du centre de notifications partagé — voir
 * create_notifications_table.php pour le schéma et le prompt du
 * 03/09/2026 (amana_web_familles, domaine livraison) pour le premier
 * usage réel (incidents tournée = urgent, packaging prêt = info).
 *
 * Volontairement pas de méthode notifier() ici : l'envoi reste
 * `$personne->notify(new XxxNotification(...))`, standard Laravel — ce
 * service ne fait que la LECTURE (liste, non-lues, urgentes non
 * résolues) et la résolution en masse (voir resoudreParDonnee(), utilisé
 * quand un incident sous-jacent est traité côté app).
 */
class NotificationCenterService
{
    /**
     * Flux pour le centre de notifications d'une personne : toutes les
     * alertes 'urgent' non résolues (quel que soit leur âge — elles ne
     * doivent pas disparaître silencieusement) + les notifications
     * 'info' des 30 derniers jours, les plus récentes en premier.
     */
    public function pourPersonne(Personne $personne, int $limiteInfo = 30): Collection
    {
        $urgentes = $personne->notifications()
            ->where('severity', 'urgent')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->get();

        $infos = $personne->notifications()
            ->where('severity', 'info')
            ->orderByDesc('created_at')
            ->limit($limiteInfo)
            ->get();

        return $urgentes->concat($infos);
    }

    public function urgentesNonResolues(Personne $personne): Collection
    {
        return $personne->notifications()
            ->where('severity', 'urgent')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function marquerLue(Personne $personne, string $id): bool
    {
        $notification = $personne->notifications()->where('id', $id)->first();

        if (!$notification) {
            return false;
        }

        $notification->marquerLue();

        return true;
    }

    /**
     * Résout en masse toutes les notifications 'urgent' non résolues dont
     * `data[$cle] === $valeur` — utilisé quand l'app consommatrice
     * résout l'entité sous-jacente (ex: RouteIncident) plutôt que la
     * notification elle-même : l'app ne connaît que l'entité, pas quelles
     * notifications en ont découlé ni pour qui.
     */
    public function resoudreParDonnee(string $cle, int|string $valeur): int
    {
        $notifications = Notification::where('severity', 'urgent')
            ->whereNull('resolved_at')
            ->where("data->{$cle}", $valeur)
            ->get();

        $notifications->each(fn (Notification $n) => $n->marquerResolue());

        return $notifications->count();
    }
}
