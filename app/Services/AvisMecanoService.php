<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * « Avis » du mécano (M3 · 118:1060) : sa note, la répartition des avis publiés, les plus récents, sa réponse publique (RG-AV8) et le signalement d'un avis abusif.
 * Un avis masqué par l'équipe n'est plus listé ni compté (RG-AV4, RG-AV6). Un avis s'identifie par le contact qui l'a permis : jamais par son numéro interne (RG-C8).
 */
final class AvisMecanoService
{
    public const REPONSE_MAX = 600;
    public const VISIBLES = 3;
    public const PAR_PAGE = 50;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * @return array{note: ?float, nb: int, depuis: ?string, repartition: array<int, int>, avis: list<array<string, mixed>>, reste: int}
     */
    public function page(int $mecanoId, bool $tous = false, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT prenom, identite_verifiee_le FROM mecaniciens WHERE id = :i', ['i' => $mecanoId]);
        $repartition = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($this->db->tous('SELECT note, COUNT(*) AS n FROM avis WHERE mecanicien_id = :m AND statut = \'publie\' GROUP BY note', ['m' => $mecanoId]) as $r) {
            $repartition[(int) $r['note']] = (int) $r['n'];
        }
        $nb = array_sum($repartition);
        $note = $nb > 0 ? round(array_sum(array_map(static fn (int $etoiles, int $n): int => $etoiles * $n, array_keys($repartition), $repartition)) / $nb, 1) : null;
        $avis = $this->db->tous(
            'SELECT a.note, a.commentaire, a.prenom_affiche, a.panne_libelle, a.cree_le, a.reponse_mecano, a.reponse_le, c.public_id AS contact
               FROM avis a JOIN contacts c ON c.id = a.contact_id
              WHERE a.mecanicien_id = :m AND a.statut = \'publie\' ORDER BY a.cree_le DESC, a.id DESC LIMIT ' . ($tous ? self::PAR_PAGE : self::VISIBLES),
            ['m' => $mecanoId]
        );
        $depuis = $m['identite_verifiee_le'] !== null ? new \DateTimeImmutable((string) $m['identite_verifiee_le'], new \DateTimeZone('UTC')) : null;
        return [
            'note' => $note, 'nb' => $nb, 'repartition' => $repartition, 'prenom' => (string) $m['prenom'],
            'depuis' => $depuis !== null ? TableauMecanoService::jourMois($depuis) . ' ' . $depuis->format('Y') : null,
            'avis' => array_map(fn (array $a): array => [
                'contact' => (string) $a['contact'], 'note' => (int) $a['note'], 'commentaire' => $a['commentaire'],
                'prenom' => ($a['prenom_affiche'] ?? '') !== '' && $a['prenom_affiche'] !== null ? (string) $a['prenom_affiche'] : 'Un client', // RG-AV5
                'panne' => (string) $a['panne_libelle'], 'depuis' => Format::depuis((string) $a['cree_le'], $maintenant), 'reponse' => $a['reponse_mecano'],
            ], $avis),
            'reste' => max(0, $nb - count($avis)),
        ];
    }

    /**
     * RG-AV8 : une réponse publique par avis, modifiable. Seulement à un avis publié du mécano.
     *
     * @return array{ok: true}|array{ok: false, erreur: string}
     */
    public function repondre(int $mecanoId, string $contactPublicId, string $texte, ?\DateTimeImmutable $maintenant = null): array
    {
        $texte = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texte) ?? '');
        if ($texte === '') {
            return ['ok' => false, 'erreur' => 'Écrivez votre réponse.'];
        }
        if (mb_strlen($texte, 'UTF-8') > self::REPONSE_MAX) {
            return ['ok' => false, 'erreur' => 'Votre réponse est trop longue : ' . self::REPONSE_MAX . ' caractères au plus.'];
        }
        $id = $this->db->valeur(
            'SELECT a.id FROM avis a JOIN contacts c ON c.id = a.contact_id WHERE c.public_id = :c AND a.mecanicien_id = :m AND a.statut = \'publie\'',
            ['c' => $contactPublicId, 'm' => $mecanoId]
        );
        if ($id === null) {
            return ['ok' => false, 'erreur' => 'Cet avis n’existe plus.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->db->modifier('avis', ['reponse_mecano' => $texte, 'reponse_le' => $maintenant->format('Y-m-d H:i:s')], ['id' => (int) $id]);
        return ['ok' => true];
    }

    /**
     * « Signaler un avis abusif » : un message à l'équipe, qui regarde l'avis et peut le masquer (RG-AV4). Un même avis ne se signale qu'une fois par jour.
     *
     * @return array{ok: true, deja: bool}|array{ok: false, erreur: string}
     */
    public function signaler(int $mecanoId, string $contactPublicId, string $ip = '', ?\DateTimeImmutable $maintenant = null): array
    {
        $a = $this->db->un(
            'SELECT a.note, a.commentaire, a.prenom_affiche, m.prenom, m.nom, m.telephone, m.email
               FROM avis a JOIN contacts c ON c.id = a.contact_id JOIN mecaniciens m ON m.id = a.mecanicien_id
              WHERE c.public_id = :c AND a.mecanicien_id = :m AND a.statut = \'publie\'',
            ['c' => $contactPublicId, 'm' => $mecanoId]
        );
        if ($a === null) {
            return ['ok' => false, 'erreur' => 'Cet avis n’existe plus.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $repere = 'Avis ' . $contactPublicId;
        if ((int) $this->db->valeur('SELECT COUNT(*) FROM messages_contact WHERE profil = \'mecano\' AND message LIKE :r AND cree_le >= :d', ['r' => $repere . '%', 'd' => $maintenant->modify('-24 hours')->format('Y-m-d H:i:s')]) > 0) {
            return ['ok' => true, 'deja' => true];
        }
        $message = $repere . ' · avis abusif signalé par ' . $a['prenom'] . ' ' . $a['nom'] . ' (' . $a['note'] . ' sur 5)'
            . ($a['commentaire'] !== null && $a['commentaire'] !== '' ? ' : « ' . mb_substr((string) $a['commentaire'], 0, 600) . ' »' : '.');
        $this->db->inserer('messages_contact', [
            'profil' => 'mecano', 'nom' => mb_substr($a['prenom'] . ' ' . $a['nom'], 0, 80), 'telephone' => $a['telephone'], 'email' => $a['email'],
            'message' => mb_substr($message, 0, 2000), 'ip' => $ip !== '' ? (inet_pton($ip) ?: null) : null, 'cree_le' => $maintenant->format('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'deja' => false];
    }
}
