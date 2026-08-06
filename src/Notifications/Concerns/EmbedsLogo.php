<?php
// src/Notifications/Concerns/EmbedsLogo.php
//
// Déplacé depuis amana_web_planning/amana_web_familles le 04/08/2026 —
// trait identique octet pour octet entre les deux apps, aucune adaptation
// de contenu nécessaire au-delà du changement de namespace.

declare(strict_types=1);

namespace Amana\Shared\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Throwable;

/**
 * Attache le logo AMANA aux emails en tant que pièce jointe inline (CID),
 * au lieu de le référencer par une URL distante.
 *
 * Pourquoi : les images chargées par URL distante ne s'affichaient de façon
 * fiable sur aucun client testé (Gmail web/Android, Thunderbird), très
 * probablement à cause de Cloudflare qui bloque/challenge les requêtes des
 * proxys d'images de ces clients. Un CID embarqué dans l'email élimine
 * totalement cette dépendance réseau.
 *
 * Utilisation dans une classe Notification :
 *
 *   use Amana\Shared\Notifications\Concerns\EmbedsLogo;
 *
 *   return $this->embedLogo(new MailMessage)
 *       ->subject('...')
 *       ->view('emails.xxx', [
 *           'logoCid' => $this->logoCid(),
 *           ...
 *       ]);
 */
trait EmbedsLogo
{
    private const LOGO_CID = 'logo-amana@amana-shared';

    private function embedLogo(MailMessage $message): MailMessage
    {
        $chemin = public_path('images/amana-logo.png');

        return $message->withSymfonyMessage(function (Email $symfonyMessage) use ($chemin): void {
            if (!is_file($chemin) || !is_readable($chemin)) {
                return;
            }

            try {
                $piece = (new DataPart(new File($chemin), 'amana-logo.png', 'image/png'))
                    ->asInline()
                    ->setContentId(self::LOGO_CID);

                $symfonyMessage->addPart($piece);
            } catch (Throwable) {
                // Problème de lecture du fichier : on n'interrompt pas l'envoi.
            }
        });
    }

    private function logoCid(): string
    {
        return 'cid:' . self::LOGO_CID;
    }
}
