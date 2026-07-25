<?php
// src/Models/AuditLog.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour audit_logs (amana_commun).
 * Enregistre toute action sensible dans n'importe quelle app AMANA.
 *
 * Utilisation via le helper global : audit('create', 'personnes', ...)
 * user_id est résolu automatiquement depuis Auth::id(). Null pour les
 * actions système (jobs en queue, webhook, etc.)
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'id_application',
        'action',
        'module',
        'entity_id',
        'entity_type',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'id_application');
    }
}
