<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * La file des dossiers de l'administration (A0, A1) : qui attend, depuis quand, avec quelles pièces (RG-DO3 à RG-DO6).
 * Le délai de traitement annoncé au mécano est de 48 h ; au-delà de 36 h le dossier passe en alerte.
 */
final class DossierService
{
    public const PAR_PAGE = 8;
    /** Onglets de la file, dans l'ordre de Figma (167:139) : statut du dossier → libellé. */
    public const ONGLETS = ['en_attente' => 'En attente', 'a_reprendre' => 'À corriger', 'valide' => 'Validés', 'refuse' => 'Refusés', 'suspendu' => 'Suspendus'];
    /** Les six pastilles de pièces, dans l'ordre : cinq obligatoires, puis l'atelier (facultatif). */
    public const PIECES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait', 'atelier'];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** « DOS-2026-0412 » : l'année de l'envoi et le numéro interne, sur 4 chiffres. */
    public static function numero(int $id, ?string $envoyeLe): string
    {
        return sprintf('DOS-%s-%04d', $envoyeLe !== null ? substr($envoyeLe, 0, 4) : gmdate('Y'), $id);
    }

    /** @return array<string, int> nombre de dossiers par statut */
    public function decompte(): array
    {
        $r = array_fill_keys(array_keys(self::ONGLETS), 0);
        foreach ($this->db->tous('SELECT statut_dossier AS s, COUNT(*) AS n FROM mecaniciens WHERE supprime_le IS NULL GROUP BY statut_dossier') as $l) {
            if (isset($r[$l['s']])) {
                $r[$l['s']] = (int) $l['n'];
            }
        }
        return $r;
    }

    /**
     * Une page de la file. `ordre` : « desc » = celui qui attend depuis le plus longtemps d'abord (par défaut), « asc » = le plus récent d'abord.
     *
     * @return array{lignes: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function file(string $statut, string $recherche = '', int $page = 1, string $ordre = 'desc', ?\DateTimeImmutable $maintenant = null): array
    {
        $statut = isset(self::ONGLETS[$statut]) ? $statut : 'en_attente';
        $conditions = ['m.statut_dossier = :statut', 'm.supprime_le IS NULL'];
        $params = ['statut' => $statut];
        $recherche = trim($recherche);
        if ($recherche !== '') {
            if (preg_match('/^DOS-\d{4}-(\d+)$/i', $recherche, $m) === 1) {
                $conditions[] = 'm.id = :numero';
                $params['numero'] = (int) $m[1];
            } else {
                $chiffres = preg_replace('/\D/', '', $recherche) ?? '';
                $ou = ['CONCAT(m.prenom, \' \', m.nom) LIKE :nom1', 'CONCAT(m.nom, \' \', m.prenom) LIKE :nom2'];
                $params['nom1'] = $params['nom2'] = '%' . addcslashes($recherche, '%_\\') . '%';
                if (strlen($chiffres) >= 4) {
                    $ou[] = 'm.telephone LIKE :tel';
                    $params['tel'] = '%' . $chiffres . '%';
                }
                $conditions[] = '(' . implode(' OR ', $ou) . ')';
            }
        }
        $ou = implode(' AND ', $conditions);
        $total = (int) $this->db->valeur("SELECT COUNT(*) FROM mecaniciens m WHERE $ou", $params);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page = min(max(1, $page), $pages);
        $sens = $ordre === 'asc' ? 'DESC' : 'ASC'; // attendre longtemps = envoyé tôt
        $lignes = $this->db->tous(
            "SELECT m.id, m.public_id, m.prenom, m.nom, m.telephone, m.dossier_envoye_le, m.identite_verifiee_le, m.statut_dossier, c.nom AS commune
               FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id LEFT JOIN communes c ON c.id = q.commune_id
              WHERE $ou ORDER BY m.dossier_envoye_le $sens, m.id $sens LIMIT " . self::PAR_PAGE . ' OFFSET ' . (($page - 1) * self::PAR_PAGE),
            $params
        );
        return ['lignes' => $this->enrichir($lignes, $maintenant), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Les dossiers qui attendent depuis le plus longtemps (A0 : « Les 5 plus anciens »). @return list<array<string, mixed>> */
    public function plusAnciens(int $n = 5, ?\DateTimeImmutable $maintenant = null): array
    {
        $lignes = $this->db->tous(
            'SELECT m.id, m.public_id, m.prenom, m.nom, m.telephone, m.dossier_envoye_le, m.identite_verifiee_le, m.statut_dossier, c.nom AS commune
               FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id LEFT JOIN communes c ON c.id = q.commune_id
              WHERE m.statut_dossier = \'en_attente\' AND m.supprime_le IS NULL ORDER BY m.dossier_envoye_le ASC, m.id ASC LIMIT ' . (int) $n
        );
        return $this->enrichir($lignes, $maintenant);
    }

    /**
     * La jauge d'attente (Figma 164:99) : « ATTENTE 31 H / 48 », huit segments (un par tranche de 6 h, au moins un), en alerte au-delà de 36 h.
     *
     * @return array{heures: int, delai: int, segments: int, total: int, alerte: bool}
     */
    public static function jauge(?string $envoyeLe, ?\DateTimeImmutable $maintenant = null): array
    {
        $utc = new \DateTimeZone('UTC');
        $maintenant ??= new \DateTimeImmutable('now', $utc);
        $delai = Parametres::int('dossier.delai_h', 48);
        $heures = $envoyeLe !== null ? max(0, (int) floor(($maintenant->getTimestamp() - (new \DateTimeImmutable($envoyeLe, $utc))->getTimestamp()) / 3600)) : 0;
        $total = 8;
        return ['heures' => $heures, 'delai' => $delai, 'total' => $total, 'segments' => min($total, max(1, intdiv($heures, intdiv($delai, $total)))), 'alerte' => $heures > Parametres::int('dossier.alerte_h', 36)];
    }

    /**
     * @param list<array<string, mixed>> $lignes
     * @return list<array<string, mixed>>
     */
    private function enrichir(array $lignes, ?\DateTimeImmutable $maintenant): array
    {
        if ($lignes === []) {
            return [];
        }
        $ids = array_map(static fn (array $l): int => (int) $l['id'], $lignes);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $specs = [];
        foreach ($this->db->tous("SELECT ms.mecanicien_id AS m, s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id IN ($in) ORDER BY s.ordre", $ids) as $s) {
            $specs[(int) $s['m']][] = (string) $s['libelle'];
        }
        $pieces = [];
        foreach ($this->db->tous("SELECT mecanicien_id AS m, type_piece AS t, statut FROM pieces_dossier WHERE mecanicien_id IN ($in) AND statut <> 'remplacee' AND fichier_supprime_le IS NULL ORDER BY id", $ids) as $p) {
            $pieces[(int) $p['m']][$p['t']] = (string) $p['statut']; // la plus récente écrase
        }
        $utc = new \DateTimeZone('UTC');
        return array_map(function (array $l) use ($specs, $pieces, $maintenant, $utc): array {
            $id = (int) $l['id'];
            $etats = [];
            foreach (self::PIECES as $type) {
                $s = $pieces[$id][$type] ?? null;
                $etats[$type] = $s === null ? ($type === 'atelier' ? 'facultative' : 'manquante') : ($s === 'a_reprendre' ? 'a_reprendre' : 'envoyee');
            }
            $envoye = $l['dossier_envoye_le'] !== null ? new \DateTimeImmutable((string) $l['dossier_envoye_le'], $utc) : null;
            return [
                'id' => $id, 'public_id' => (string) $l['public_id'], 'nom_complet' => $l['prenom'] . ' ' . $l['nom'], 'numero' => self::numero($id, $l['dossier_envoye_le']),
                'commune' => (string) ($l['commune'] ?? ''), 'specialites' => $specs[$id] ?? [], 'envoye_le' => $envoye, 'statut' => (string) $l['statut_dossier'],
                'verifie_le' => $l['identite_verifiee_le'], 'jauge' => self::jauge($l['dossier_envoye_le'], $maintenant), 'pieces' => $etats,
                'portrait' => $etats['portrait'] !== 'manquante', 'complet' => !in_array('manquante', $etats, true),
            ];
        }, $lignes);
    }
}
