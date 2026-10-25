<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * « Demandes » du mécano (M3 · 116:967) : les clients qui l'ont contacté ces 7 derniers jours, par jour, avec la panne, le quartier, la distance
 * et le retour du client (« joint » ou « non joint »). Le numéro d'un client n'existe que s'il l'a laissé (RG-C7) ; après 48 h il est masqué (RG-P1).
 */
final class DemandesMecanoService
{
    public const JOURS = 7;
    public const MASQUE_APRES_H = 48;
    /** Un contact non joint se rappelle pendant 24 h : c'est la pastille de l'onglet « Demandes » (RG-I7) */
    public const A_RAPPELER_H = 24;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * @return array{maj: string, total: int, titre: string, jours: list<array{libelle: string, masque: bool, lignes: list<array<string, mixed>>}>}
     */
    public function liste(int $mecanoId, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $contacts = $this->db->tous(
            'SELECT c.public_id, c.canal, c.statut, c.cree_le, c.resume, c.quartier_libelle, c.distance_m, c.issue_client, cl.prenom, cl.telephone
               FROM contacts c LEFT JOIN clients cl ON cl.id = c.client_id
              WHERE c.mecanicien_id = :m AND c.cree_le >= :d ORDER BY c.cree_le DESC, c.id DESC LIMIT 200',
            ['m' => $mecanoId, 'd' => $maintenant->modify('-' . self::JOURS . ' days')->format('Y-m-d H:i:s')]
        );
        $pannes = array_column($this->db->tous('SELECT code, libelle FROM pannes'), 'code', 'libelle');
        $debutJour = $maintenant->setTime(0, 0);
        $groupes = [];
        $principal = false;
        foreach ($contacts as $c) {
            $quand = new \DateTimeImmutable((string) $c['cree_le'], new \DateTimeZone('UTC'));
            $ligne = $this->ligne($c, $quand, $maintenant, $pannes);
            if (!$principal && $ligne['retour'] === 'non_joint' && $ligne['rappel'] !== null && $quand > $maintenant->modify('-' . self::A_RAPPELER_H . ' hours')) {
                $ligne['principal'] = $principal = true; // un seul bouton jaune : le dernier client qu'on n'a pas pu joindre
            }
            $jour = $quand->setTime(0, 0);
            $cle = $jour->format('Y-m-d');
            $groupes[$cle] ??= ['jour' => $jour, 'lignes' => []];
            $groupes[$cle]['lignes'][] = $ligne;
        }
        $jours = [];
        foreach ($groupes as $g) {
            $n = count($g['lignes']);
            $ecart = (int) $debutJour->diff($g['jour'])->days;
            $masque = $n > 0 && count(array_filter($g['lignes'], static fn (array $l): bool => $l['masque'] !== null)) === $n;
            $nom = $ecart === 0 ? 'Aujourd’hui' : ($ecart === 1 ? 'Hier · ' . $this->jourComplet($g['jour']) : $this->jourComplet($g['jour']));
            $jours[] = ['libelle' => $nom . ' · ' . $n . ($masque ? ' · numéros masqués' : ''), 'masque' => $masque, 'lignes' => $g['lignes']];
        }
        $total = count($contacts);
        return [
            'maj' => Format::heure($maintenant), 'total' => $total, 'jours' => $jours,
            'titre' => $total === 0 ? 'Aucun client ne vous a encore contacté' : $total . ' client' . ($total > 1 ? 's vous ont contacté' : ' vous a contacté'),
        ];
    }

    /**
     * La pastille de l'onglet « Demandes » : les clients des 24 dernières heures qu'on n'a pas pu joindre (le client l'a dit, ou l'appel est resté sans réponse).
     */
    public function aRappeler(int $mecanoId, ?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return (int) $this->db->valeur(
            'SELECT COUNT(*) FROM contacts WHERE mecanicien_id = :m AND cree_le >= :d
                AND (issue_client IN (\'pas_entendus\', \'pas_reponse\') OR (canal = \'app\' AND statut IN (\'manque\', \'occupe\', \'echoue\', \'annule\') AND issue_client IS NULL))',
            ['m' => $mecanoId, 'd' => $maintenant->modify('-' . self::A_RAPPELER_H . ' hours')->format('Y-m-d H:i:s')]
        );
    }

    /**
     * @param array<string, mixed> $c
     * @param array<string, string> $pannes libellé → code
     * @return array<string, mixed>
     */
    private function ligne(array $c, \DateTimeImmutable $quand, \DateTimeImmutable $maintenant, array $pannes): array
    {
        $morceaux = explode(' · ', (string) $c['resume']);
        $vehicule = $morceaux[0] ?? '';
        $panne = $morceaux[1] ?? '';
        $masque = $quand <= $maintenant->modify('-' . self::MASQUE_APRES_H . ' hours');
        $telephone = ($c['telephone'] ?? null) !== null && $c['telephone'] !== '' ? (string) $c['telephone'] : null;
        $retour = match (true) {
            $c['issue_client'] === 'arrive' => 'joint',
            in_array($c['issue_client'], ['pas_entendus', 'pas_reponse'], true) => 'non_joint',
            $c['canal'] === 'app' && in_array($c['statut'], ['manque', 'occupe', 'echoue', 'annule'], true) => 'non_joint',
            $c['canal'] === 'app' && $c['statut'] === 'termine' => 'joint',
            default => 'sans_retour',
        };
        return [
            'contact' => (string) $c['public_id'], 'heure' => Format::heure($quand), 'client' => ($c['prenom'] ?? '') !== '' && $c['prenom'] !== null ? (string) $c['prenom'] : 'Un client',
            'resume' => trim($panne . ' · ' . $vehicule, ' ·'), 'code_panne' => $pannes[$panne] ?? null,
            'canal' => $c['canal'] === 'whatsapp' ? 'WhatsApp' : 'Appel',
            'lieu' => $c['quartier_libelle'] !== null ? (string) $c['quartier_libelle'] : null, 'distance' => $c['distance_m'] !== null ? Format::distance((int) $c['distance_m']) : null,
            'retour' => $retour, 'principal' => false,
            // On ne rappelle qu'un client qui a laissé son numéro, et plus quand il est masqué
            'rappel' => $telephone !== null && !$masque ? Format::telephoneE164($telephone) : null,
            'masque' => $telephone !== null && $masque ? self::masquer($telephone) : null,
        ];
    }

    /** « 07 12 34 34 56 » → « 07 •• •• 34 56 » : le début et la fin suffisent à le reconnaître sans le donner. */
    public static function masquer(string $telephone): string
    {
        $groupes = explode(' ', Format::telephoneLocal($telephone));
        if (count($groupes) !== 5) {
            return '•• •• •• •• ••';
        }
        return $groupes[0] . ' •• •• ' . $groupes[3] . ' ' . $groupes[4];
    }

    /** « mardi 20 octobre » */
    private function jourComplet(\DateTimeImmutable $jour): string
    {
        return TableauMecanoService::JOURS[(int) $jour->format('w')] . ' ' . TableauMecanoService::jourMois($jour);
    }
}
