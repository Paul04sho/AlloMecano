<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Format;

/**
 * Mot de passe oublié (RG-C3) : un lien par e-mail, valable 30 minutes, à usage unique. Sans e-mail sur le compte, c'est un admin qui réinitialise.
 * On ne dit jamais si une adresse a un compte : l'écran « Regardez vos e-mails » est le même dans tous les cas.
 */
final class ReinitialisationService
{
    public const VALIDITE_MIN = 30;
    public const RENVOI_S = 60;

    public function __construct(private readonly Database $db, private readonly MailService $mail)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance(), MailService::depuisEnv());
    }

    /** « koffi.yao@gmail.com » → « k•••@gmail.com » */
    public static function masquer(string $email): string
    {
        $email = trim($email);
        $arobase = strrpos($email, '@');
        if ($arobase === false || $arobase === 0) {
            return $email;
        }
        return mb_substr($email, 0, 1) . '•••' . substr($email, $arobase);
    }

    /**
     * Envoie le lien si l'adresse est celle d'un compte (admin ou mécano). Renvoie toujours l'adresse masquée.
     *
     * @return array{email_masque: string, envoye: bool}
     */
    public function demander(string $email, ?\DateTimeImmutable $maintenant = null): array
    {
        $email = mb_strtolower(trim($email));
        $masque = self::masquer($email);
        $compte = $this->compteParEmail($email);
        if ($compte === null) {
            return ['email_masque' => $masque, 'envoye' => false];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $jeton = bin2hex(random_bytes(32));
        $this->db->inserer('jetons_reinitialisation', [
            'mecanicien_id' => $compte['profil'] === 'mecano' ? $compte['id'] : null,
            'admin_id' => $compte['profil'] === 'admin' ? $compte['id'] : null,
            'jeton_hash' => hash('sha256', $jeton),
            'expire_le' => $maintenant->modify('+' . self::VALIDITE_MIN . ' minutes')->format('Y-m-d H:i:s'),
            'cree_le' => $maintenant->format('Y-m-d H:i:s'),
        ]);
        $lien = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/') . '/mot-de-passe/nouveau/' . $jeton;
        $envoye = $this->mail->envoyer($email, 'Votre lien pour choisir un nouveau mot de passe', // texte à valider : non dessiné dans Figma
            "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien dans les " . self::VALIDITE_MIN . " minutes :\n$lien\n\n"
            . "Il ne sert qu'une fois. Si vous n'avez rien demandé, ignorez ce message : votre mot de passe ne change pas.\n\nallomécano");
        return ['email_masque' => $masque, 'envoye' => $envoye];
    }

    /**
     * L'état d'un lien : valide | expire | inconnu (jamais vu, ou déjà utilisé : on ne distingue pas).
     *
     * @return array{etat: string, email_masque?: string, email?: string, envoye_le?: \DateTimeImmutable}
     */
    public function verifier(string $jeton, ?\DateTimeImmutable $maintenant = null): array
    {
        if (preg_match('/^[0-9a-f]{64}$/', $jeton) !== 1) {
            return ['etat' => 'inconnu'];
        }
        $l = $this->db->un('SELECT id, mecanicien_id, admin_id, expire_le, utilise_le, cree_le FROM jetons_reinitialisation WHERE jeton_hash = :h', ['h' => hash('sha256', $jeton)]);
        if ($l === null || $l['utilise_le'] !== null) {
            return ['etat' => 'inconnu'];
        }
        $utc = new \DateTimeZone('UTC');
        $maintenant ??= new \DateTimeImmutable('now', $utc);
        $email = (string) ($l['admin_id'] !== null
            ? $this->db->valeur('SELECT email FROM admins WHERE id = :i', ['i' => $l['admin_id']])
            : $this->db->valeur('SELECT email FROM mecaniciens WHERE id = :i', ['i' => $l['mecanicien_id']]));
        $r = ['email_masque' => self::masquer($email), 'email' => $email, 'envoye_le' => new \DateTimeImmutable((string) $l['cree_le'], $utc)];
        return ['etat' => new \DateTimeImmutable((string) $l['expire_le'], $utc) > $maintenant ? 'valide' : 'expire'] + $r;
    }

    /**
     * Le nouveau mot de passe : 8 caractères au moins dont 1 chiffre, et les deux saisies identiques (Figma 130:178 à 130:203).
     *
     * @return array<string, string>
     */
    public function valider(string $motDePasse, string $confirmation): array
    {
        $erreurs = [];
        if (mb_strlen($motDePasse) < 8 || preg_match('/\d/', $motDePasse) !== 1) {
            $erreurs['mot_de_passe'] = '8 caractères au minimum, dont 1 chiffre.';
        }
        if ($confirmation !== $motDePasse) {
            $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
        }
        return $erreurs;
    }

    /** Enregistre le nouveau mot de passe, brûle le lien et tous les autres liens du compte, lève les blocages. */
    public function changer(string $jeton, string $motDePasse, ?\DateTimeImmutable $maintenant = null): bool
    {
        $v = $this->verifier($jeton, $maintenant);
        if ($v['etat'] !== 'valide' || $this->valider($motDePasse, $motDePasse) !== []) {
            return false;
        }
        $l = $this->db->un('SELECT id, mecanicien_id, admin_id FROM jetons_reinitialisation WHERE jeton_hash = :h', ['h' => hash('sha256', $jeton)]);
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $this->db->transaction(function () use ($l, $hash, $v): void {
            if ($l['admin_id'] !== null) {
                $this->db->modifier('admins', ['mot_de_passe_hash' => $hash], ['id' => $l['admin_id']]);
                $this->db->executer('DELETE FROM jetons_reinitialisation WHERE admin_id = :i AND id <> :j', ['i' => $l['admin_id'], 'j' => $l['id']]);
            } else {
                $this->db->modifier('mecaniciens', ['mot_de_passe_hash' => $hash, 'mot_de_passe_modifie_le' => gmdate('Y-m-d H:i:s')], ['id' => $l['mecanicien_id']]);
                $this->db->executer('DELETE FROM jetons_reinitialisation WHERE mecanicien_id = :i AND id <> :j', ['i' => $l['mecanicien_id'], 'j' => $l['id']]);
            }
            $this->db->modifier('jetons_reinitialisation', ['utilise_le' => gmdate('Y-m-d H:i:s')], ['id' => $l['id']]);
            $this->db->executer('DELETE FROM tentatives_connexion WHERE identifiant = :e', ['e' => mb_strtolower((string) $v['email'])]);
        });
        return true;
    }

    /** « Recevoir un nouveau lien » depuis un lien expiré : le nouveau part vers la même adresse. */
    public function renvoyerDepuis(string $jeton): ?array
    {
        $v = $this->verifier($jeton);
        return isset($v['email']) ? $this->demander($v['email']) : null;
    }

    /** @return ?array{profil: string, id: int} */
    private function compteParEmail(string $email): ?array
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $a = $this->db->valeur('SELECT id FROM admins WHERE email = :e AND actif = 1', ['e' => $email]);
        if ($a !== null && $a !== false) {
            return ['profil' => 'admin', 'id' => (int) $a];
        }
        $m = $this->db->valeur('SELECT id FROM mecaniciens WHERE email = :e', ['e' => $email]);
        return $m !== null && $m !== false ? ['profil' => 'mecano', 'id' => (int) $m] : null;
    }
}
