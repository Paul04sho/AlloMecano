<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Validator;

/** Formulaire Contact (X4, RG-CT1) : un téléphone ou un e-mail pour répondre, 3 messages par heure et par adresse IP, réponse sous 24 h ouvrées. */
final class MessageContactService
{
    public const MESSAGE_MAX = 1000;
    public const PAR_HEURE = 3;

    /** Les trois choix de « Vous êtes » (Figma 140:367). */
    public const PROFILS = ['client' => 'Client', 'mecano' => 'Mécanicien', 'partenaire' => 'Presse ou partenaire'];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * @param array<string, mixed> $saisie profil, prenom, contact, message
     * @return array{erreurs: array<string, string>, propre: array{profil: string, nom: string, telephone: ?string, email: ?string, message: string}}
     */
    public function valider(array $saisie): array
    {
        $contact = trim((string) ($saisie['contact'] ?? ''));
        $estEmail = str_contains($contact, '@');
        $v = Validator::verifier([
            'profil' => (string) ($saisie['profil'] ?? ''), 'prenom' => trim((string) ($saisie['prenom'] ?? '')), 'contact' => $contact,
            'message' => trim((string) ($saisie['message'] ?? '')),
        ], [
            'profil' => 'requis|dans:' . implode(',', array_keys(self::PROFILS)), 'prenom' => 'requis|max:80',
            'contact' => 'requis|' . ($estEmail ? 'email' : 'telephone_ci'), 'message' => 'requis|max:' . self::MESSAGE_MAX,
        ]);
        $erreurs = $v->erreurs();
        return ['erreurs' => $erreurs, 'propre' => [
            'profil' => (string) ($saisie['profil'] ?? ''), 'nom' => trim((string) ($saisie['prenom'] ?? '')),
            'telephone' => !$estEmail && $contact !== '' ? Format::telephoneE164($contact) : null, 'email' => $estEmail ? mb_strtolower($contact) : null,
            'message' => trim((string) ($saisie['message'] ?? '')),
        ]];
    }

    /** Messages déjà envoyés depuis cette adresse IP dans l'heure (RG-CT1). */
    public function envoyesDepuis(string $ip, ?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return (int) $this->db->valeur('SELECT COUNT(*) FROM messages_contact WHERE ip = :ip AND cree_le > :d',
            ['ip' => $this->ip($ip), 'd' => $maintenant->modify('-1 hour')->format('Y-m-d H:i:s')]);
    }

    /**
     * @param array{profil: string, nom: string, telephone: ?string, email: ?string, message: string} $propre
     * @return array{ok: bool, limite?: bool}
     */
    public function enregistrer(array $propre, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($this->envoyesDepuis($ip, $maintenant) >= self::PAR_HEURE) {
            return ['ok' => false, 'limite' => true];
        }
        $this->db->inserer('messages_contact', $propre + ['ip' => $this->ip($ip), 'cree_le' => $maintenant->format('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    private function ip(string $ip): string
    {
        return inet_pton($ip) ?: "\0\0\0\0";
    }
}
