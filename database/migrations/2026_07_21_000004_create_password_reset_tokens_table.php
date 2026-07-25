<?php
// database/migrations/2026_07_21_000004_create_password_reset_tokens_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table password_reset_tokens, nécessaire au flux "mot de passe oublié"
 * partagé (voir Amana\Shared\Http\Controllers\AuthController). La colonne
 * email correspond à ref_personnes.email. Laravel gère lui-même la
 * correspondance via le provider 'personnes' de chaque app — pas de FK
 * explicite nécessaire (ni possible : pas de FK cross-DB en MySQL).
 */
return new class extends Migration {
    public $connection = 'commun';

    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()
                ->comment('Email de la personne — correspond à ref_personnes.email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
