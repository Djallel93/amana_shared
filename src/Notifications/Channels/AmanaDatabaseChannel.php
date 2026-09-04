<?php
// src/Notifications/Channels/AmanaDatabaseChannel.php

declare(strict_types=1);

namespace Amana\Shared\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Canal 'amana-database' — variante du canal 'database' stock de Laravel
 * qui écrit en plus `severity` (voir create_notifications_table.php et
 * Amana\Shared\Models\Notification). Enregistré comme canal Notification
 * additionnel plutôt que remplacement du canal 'database' stock — voir
 * AmanaSharedServiceProvider::boot() — pour ne jamais entrer en conflit
 * avec une notification tierce (ex: du framework lui-même) qui utiliserait
 * encore 'database' au sens Laravel standard.
 *
 * Une classe Notification qui veut apparaître dans le centre de
 * notifications AMANA :
 *   - inclut 'amana-database' dans via()
 *   - implémente toDatabase($notifiable): array (ou toArray(), repli
 *     standard si toDatabase() est absent)
 *   - expose optionnellement une propriété publique $severity ('info' par
 *     défaut si absente — voir RouteIncidentNotification pour un exemple
 *     'urgent', côté amana_web_familles)
 */
class AmanaDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): mixed
    {
        $data = method_exists($notification, 'toDatabase')
            ? $notification->toDatabase($notifiable)
            : $notification->toArray($notifiable);

        return $notifiable->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => get_class($notification),
            'data' => $data,
            'severity' => property_exists($notification, 'severity') ? $notification->severity : 'info',
            'read_at' => null,
        ]);
    }
}
