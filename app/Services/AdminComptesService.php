<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use PDOException;

/** Gestion des comptes de l’équipe admin : le principal administre les comptes, chacun gère son profil. */
final class AdminComptesService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** @return list<array{id: int, prenom: string, nom: string, email: string, actif: bool, est_principal: bool, derniere_connexion_le: ?string}> */
    public function administrateurs(int $acteur): array
    {
        if (!$this->estPrincipal($acteur)) {
            return [];
        }
        return array_map(static fn (array $a): array => [
            'id' => (int) $a['id'], 'prenom' => (string) $a['prenom'], 'nom' => (string) $a['nom'], 'email' => (string) $a['email'],
            'actif' => (bool) $a['actif'], 'est_principal' => (bool) $a['est_principal'],
            'derniere_connexion_le' => $a['derniere_connexion_le'] !== null ? (string) $a['derniere_connexion_le'] : null,
        ], $this->db->tous('SELECT id, prenom, nom, email, actif, est_principal, derniere_connexion_le FROM admins ORDER BY est_principal DESC, actif DESC, nom, prenom'));
    }

    /** @param array<string, mixed> $saisie @return array{ok: true, id: int, mot_de_passe_temporaire: string}|array{ok: false, erreur: string, erreurs?: array<string, string>} */
    public function creer(int $acteur, array $saisie, string $ip = ''): array
    {
        if (!$this->estPrincipal($acteur)) {
            return ['ok' => false, 'erreur' => 'permission'];
        }
        $donnees = $this->identiteValidee($saisie);
        if (isset($donnees['erreurs'])) {
            return ['ok' => false, 'erreur' => 'validation', 'erreurs' => $donnees['erreurs']];
        }
        if ($this->emailExiste($donnees['email'])) {
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }

        $temporaire = self::motDePasseTemporaire();
        try {
            $id = $this->db->transaction(function () use ($acteur, $donnees, $temporaire, $ip): int {
                $id = $this->db->inserer('admins', [
                    'prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'email' => $donnees['email'],
                    'mot_de_passe_hash' => password_hash($temporaire, PASSWORD_DEFAULT), 'actif' => 1,
                    'est_principal' => 0, 'mot_de_passe_a_changer' => 1,
                ]);
                $this->journal()->noter($acteur, 'creer_admin', 'admin', $id, ['prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'email' => $donnees['email']], $ip);
                return $id;
            });
        } catch (PDOException $e) {
            if (!$this->estDoublon($e)) {
                throw $e;
            }
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        return ['ok' => true, 'id' => $id, 'mot_de_passe_temporaire' => $temporaire];
    }

    /** @param array<string, mixed> $saisie @return array{ok: bool, erreur?: string, erreurs?: array<string, string>} */
    public function modifier(int $acteur, array $saisie, string $ip = ''): array
    {
        if (!$this->estPrincipal($acteur)) {
            return ['ok' => false, 'erreur' => 'permission'];
        }
        $id = filter_var($saisie['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || (int) $id === $acteur) {
            return ['ok' => false, 'erreur' => 'compte_interdit'];
        }
        $cible = $this->db->un('SELECT id, est_principal FROM admins WHERE id = :id', ['id' => (int) $id]);
        if ($cible === null || (bool) $cible['est_principal']) {
            return ['ok' => false, 'erreur' => 'compte_introuvable'];
        }
        $donnees = $this->identiteValidee($saisie);
        if (isset($donnees['erreurs'])) {
            return ['ok' => false, 'erreur' => 'validation', 'erreurs' => $donnees['erreurs']];
        }
        if ($this->emailExiste($donnees['email'], (int) $id)) {
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        try {
            $this->db->transaction(function () use ($acteur, $id, $donnees, $ip): void {
                $this->db->modifier('admins', ['prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'email' => $donnees['email']], ['id' => (int) $id]);
                $this->journal()->noter($acteur, 'modifier_admin', 'admin', (int) $id, $donnees, $ip);
            });
        } catch (PDOException $e) {
            if (!$this->estDoublon($e)) {
                throw $e;
            }
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        return ['ok' => true];
    }

    /** @return array{ok: bool, erreur?: string} */
    public function changerActif(int $acteur, int $cible, bool $actif, string $ip = ''): array
    {
        if (!$this->estPrincipal($acteur)) {
            return ['ok' => false, 'erreur' => 'permission'];
        }
        if ($acteur === $cible) {
            return ['ok' => false, 'erreur' => 'compte_interdit'];
        }
        $compte = $this->db->un('SELECT id, actif, est_principal FROM admins WHERE id = :id', ['id' => $cible]);
        if ($compte === null || (bool) $compte['est_principal']) {
            return ['ok' => false, 'erreur' => 'compte_introuvable'];
        }
        if ((bool) $compte['actif'] === $actif) {
            return ['ok' => true];
        }
        return $this->db->transaction(function () use ($acteur, $cible, $actif, $ip): array {
            $this->db->modifier('admins', ['actif' => $actif ? 1 : 0], ['id' => $cible]);
            $this->journal()->noter($acteur, $actif ? 'reactiver_admin' : 'desactiver_admin', 'admin', $cible, ['actif' => $actif], $ip);
            return ['ok' => true];
        });
    }

    /** @return array{ok: true, mot_de_passe_temporaire: string}|array{ok: false, erreur: string} */
    public function reinitialiserMotDePasse(int $acteur, int $cible, string $ip = ''): array
    {
        if (!$this->estPrincipal($acteur)) {
            return ['ok' => false, 'erreur' => 'permission'];
        }
        if ($acteur === $cible) {
            return ['ok' => false, 'erreur' => 'compte_interdit'];
        }
        $compte = $this->db->un('SELECT id, est_principal FROM admins WHERE id = :id', ['id' => $cible]);
        if ($compte === null || (bool) $compte['est_principal']) {
            return ['ok' => false, 'erreur' => 'compte_introuvable'];
        }
        $temporaire = self::motDePasseTemporaire();
        $this->db->transaction(function () use ($acteur, $cible, $temporaire, $ip): void {
            $this->db->modifier('admins', [
                'mot_de_passe_hash' => password_hash($temporaire, PASSWORD_DEFAULT), 'mot_de_passe_a_changer' => 1,
            ], ['id' => $cible]);
            $this->journal()->noter($acteur, 'reinitialiser_mot_de_passe_admin', 'admin', $cible, ['mot_de_passe_temporaire' => true], $ip);
        });
        return ['ok' => true, 'mot_de_passe_temporaire' => $temporaire];
    }

    /** @return ?array{id: int, prenom: string, nom: string, email: string, est_principal: bool, actif: bool, a_un_mot_de_passe: bool} */
    public function monCompte(int $id): ?array
    {
        $a = $this->db->un('SELECT id, prenom, nom, email, est_principal, actif, (mot_de_passe_hash IS NOT NULL AND mot_de_passe_hash <> \'\') AS a_un_mot_de_passe FROM admins WHERE id = :id AND actif = 1', ['id' => $id]);
        return $a === null ? null : [
            'id' => (int) $a['id'], 'prenom' => (string) $a['prenom'], 'nom' => (string) $a['nom'], 'email' => (string) $a['email'],
            'est_principal' => (bool) $a['est_principal'], 'actif' => (bool) $a['actif'], 'a_un_mot_de_passe' => (bool) $a['a_un_mot_de_passe'],
        ];
    }

    /** @param array<string, mixed> $saisie @return array{ok: bool, erreur?: string, erreurs?: array<string, string>} */
    public function modifierMonCompte(int $id, array $saisie, string $ip = ''): array
    {
        if ($this->monCompte($id) === null) {
            return ['ok' => false, 'erreur' => 'compte_introuvable'];
        }
        $donnees = $this->identiteValidee($saisie);
        if (isset($donnees['erreurs'])) {
            return ['ok' => false, 'erreur' => 'validation', 'erreurs' => $donnees['erreurs']];
        }
        if ($this->emailExiste($donnees['email'], $id)) {
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        try {
            $this->db->transaction(function () use ($id, $donnees, $ip): void {
                $this->db->modifier('admins', ['prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'email' => $donnees['email']], ['id' => $id]);
                $this->journal()->noter($id, 'modifier_mon_compte_admin', 'admin', $id, $donnees, $ip);
            });
        } catch (PDOException $e) {
            if (!$this->estDoublon($e)) {
                throw $e;
            }
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        return ['ok' => true];
    }

    /** @return array{ok: bool, erreur?: string, erreurs?: array<string, string>} */
    public function changerMotDePasse(int $id, string $actuel, string $nouveau, string $confirmation, string $ip = ''): array
    {
        $compte = $this->db->un('SELECT mot_de_passe_hash, mot_de_passe_a_changer FROM admins WHERE id = :id AND actif = 1', ['id' => $id]);
        if ($compte === null) {
            return ['ok' => false, 'erreur' => 'compte_introuvable'];
        }
        $v = Validator::verifier(['mot_de_passe' => $nouveau], ['mot_de_passe' => 'requis|mot_de_passe']);
        if ($v->echoue()) {
            return ['ok' => false, 'erreur' => 'validation', 'erreurs' => $v->erreurs()];
        }
        if (!hash_equals($nouveau, $confirmation)) {
            return ['ok' => false, 'erreur' => 'confirmation'];
        }
        $force = (bool) $compte['mot_de_passe_a_changer'];
        $aUnMotDePasse = is_string($compte['mot_de_passe_hash']) && $compte['mot_de_passe_hash'] !== '';
        if (!$force && $aUnMotDePasse && !password_verify($actuel, $compte['mot_de_passe_hash'])) {
            return ['ok' => false, 'erreur' => 'mot_de_passe_actuel'];
        }
        if ($aUnMotDePasse && password_verify($nouveau, $compte['mot_de_passe_hash'])) {
            return ['ok' => false, 'erreur' => 'mot_de_passe_identique'];
        }
        return $this->db->transaction(function () use ($id, $nouveau, $ip): array {
            $this->db->modifier('admins', [
                'mot_de_passe_hash' => password_hash($nouveau, PASSWORD_DEFAULT), 'mot_de_passe_a_changer' => 0,
            ], ['id' => $id]);
            $this->journal()->noter($id, 'changer_mot_de_passe_admin', 'admin', $id, ['mot_de_passe_modifie' => true], $ip);
            return ['ok' => true];
        });
    }

    /** Initialise le compte propriétaire une seule fois. Le mot de passe temporaire n’est jamais stocké en clair. */
    public function initialiserPrincipal(string $prenom, string $nom, string $email, string $ip = ''): array
    {
        $donnees = $this->identiteValidee(['prenom' => $prenom, 'nom' => $nom, 'email' => $email]);
        if (isset($donnees['erreurs'])) {
            return ['ok' => false, 'erreur' => 'validation', 'erreurs' => $donnees['erreurs']];
        }
        if ($this->emailMecanoExiste($donnees['email'])) {
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        $temporaire = self::motDePasseTemporaire();
        try {
            $resultat = $this->db->transaction(function () use ($donnees, $temporaire, $ip): array {
                $principal = $this->db->un('SELECT id, email FROM admins WHERE est_principal = 1 LIMIT 1 FOR UPDATE');
                if ($principal !== null) {
                    return ['ok' => (mb_strtolower((string) $principal['email']) === $donnees['email']), 'deja_initialise' => true];
                }
                $existant = $this->db->un('SELECT id FROM admins WHERE email = :email FOR UPDATE', ['email' => $donnees['email']]);
                $hash = password_hash($temporaire, PASSWORD_DEFAULT);
                if ($existant === null) {
                    $id = $this->db->inserer('admins', $donnees + [
                        'mot_de_passe_hash' => $hash, 'actif' => 1, 'est_principal' => 1, 'mot_de_passe_a_changer' => 1,
                    ]);
                } else {
                    $id = (int) $existant['id'];
                    $this->db->modifier('admins', $donnees + [
                        'mot_de_passe_hash' => $hash, 'actif' => 1, 'est_principal' => 1, 'mot_de_passe_a_changer' => 1,
                    ], ['id' => $id]);
                }
                $this->journal()->noter($id, 'initialiser_admin_principal', 'admin', $id, ['email' => $donnees['email']], $ip);
                return ['ok' => true, 'id' => $id, 'mot_de_passe_temporaire' => $temporaire];
            });
        } catch (PDOException $e) {
            if (!$this->estDoublon($e)) {
                throw $e;
            }
            return ['ok' => false, 'erreur' => 'email_utilise'];
        }
        return $resultat;
    }

    /** @return array{prenom: string, nom: string, email: string}|array{erreurs: array<string, string>} */
    private function identiteValidee(array $saisie): array
    {
        $v = Validator::verifier($saisie, [
            'prenom' => 'requis|max:60', 'nom' => 'requis|max:60', 'email' => 'requis|email|max:190',
        ]);
        if ($v->echoue()) {
            return ['erreurs' => $v->erreurs()];
        }
        $d = $v->donnees();
        return ['prenom' => trim((string) $d['prenom']), 'nom' => trim((string) $d['nom']), 'email' => mb_strtolower(trim((string) $d['email']))];
    }

    private function emailExiste(string $email, ?int $saufId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM admins WHERE email = :email';
        $params = ['email' => $email];
        if ($saufId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $saufId;
        }
        return (int) $this->db->valeur($sql, $params) > 0 || $this->emailMecanoExiste($email);
    }

    private function emailMecanoExiste(string $email): bool
    {
        return (int) $this->db->valeur('SELECT COUNT(*) FROM mecaniciens WHERE email = :email', ['email' => $email]) > 0;
    }

    private function estDoublon(PDOException $e): bool
    {
        return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
    }

    private function estPrincipal(int $id): bool
    {
        return (bool) $this->db->valeur('SELECT est_principal FROM admins WHERE id = :id AND actif = 1', ['id' => $id]);
    }

    private function journal(): JournalAdminService
    {
        return new JournalAdminService($this->db);
    }

    private static function motDePasseTemporaire(): string
    {
        return bin2hex(random_bytes(16)) . '9!';
    }
}
