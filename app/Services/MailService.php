<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi d'e-mails par SMTP (Systalink). Sans SMTP_HOTE (poste de développement), le message est écrit dans storage/logs/mail.log
 * au lieu de partir : on y retrouve le lien d'un mot de passe oublié sans serveur de messagerie.
 */
final class MailService
{
    public function __construct(private readonly ?string $journal = null)
    {
    }

    public static function depuisEnv(): self
    {
        return new self();
    }

    public function envoyer(string $a, string $sujet, string $texte): bool
    {
        $hote = Env::get('SMTP_HOTE');
        if ($hote === null) {
            return $this->ecrire($a, $sujet, $texte);
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $hote;
            $mail->Port = (int) Env::get('SMTP_PORT', '587');
            $mail->SMTPAuth = Env::get('SMTP_UTILISATEUR') !== null;
            $mail->Username = (string) Env::get('SMTP_UTILISATEUR', '');
            $mail->Password = (string) Env::get('SMTP_MOT_DE_PASSE', '');
            $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) Env::get('SMTP_EXPEDITEUR', 'bonjour@allomecano.ci'), (string) Env::get('SMTP_NOM', 'allomécano'));
            $mail->addAddress($a);
            $mail->Subject = $sujet;
            $mail->Body = $texte;
            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('Envoi e-mail impossible : ' . $e->getMessage());
            return false;
        }
    }

    private function ecrire(string $a, string $sujet, string $texte): bool
    {
        $fichier = $this->journal ?? dirname(__DIR__, 2) . '/storage/logs/mail.log';
        $dossier = dirname($fichier);
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $entree = '[' . gmdate('Y-m-d H:i:s') . " UTC] À : $a\nSujet : $sujet\n\n$texte\n" . str_repeat('-', 60) . "\n";
        return file_put_contents($fichier, $entree, FILE_APPEND) !== false;
    }
}
