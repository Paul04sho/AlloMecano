<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Ce que le mécanicien voit en ouvrant son espace (M2, M2a, M2b) : qui il est, où en est son dossier, ce qui s'est passé aujourd'hui, ses derniers avis.
 * Rien ici ne montre un numéro de client sans qu'il l'ait laissé lui-même (RG-C7) ; la distance se calcule tant que la position du client existe (RG-P1).
 */
final class TableauMecanoService
{
    public const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    public const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const NOMBRES = [0 => 'Aucun', 'Un', 'Deux', 'Trois', 'Quatre', 'Cinq', 'Six', 'Sept', 'Huit', 'Neuf', 'Dix'];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** « 21 octobre » (sans l'année). */
    public static function jourMois(\DateTimeInterface $d): string
    {
        return ((int) $d->format('j') === 1 ? '1er' : $d->format('j')) . ' ' . self::MOIS[(int) $d->format('n')];
    }

    /**
     * L'en-tête et l'état du mécano connecté.
     *
     * @return ?array<string, mixed>
     */
    public function profil(int $id): ?array
    {
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.slug, m.prenom, m.nom, m.statut_dossier, m.etat, m.dispo_debut, m.position_maj_le, m.rayon_km, m.note_moyenne, m.nb_avis, m.dossier_envoye_le, m.telephone, m.motif_refus,
                    qa.nom AS quartier_atelier, qd.nom AS quartier_dispo,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM mecaniciens m LEFT JOIN quartiers qa ON qa.id = m.atelier_quartier_id LEFT JOIN quartiers qd ON qd.id = m.dispo_quartier_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE m.id = :i AND m.supprime_le IS NULL',
            ['i' => $id]
        );
        if ($m === null) {
            return null;
        }
        $specs = array_column($this->db->tous('SELECT s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id = :m ORDER BY s.ordre', ['m' => $id]), 'libelle');
        $metier = $specs === [] ? '' : $specs[0] . (isset($specs[1]) ? ' et ' . mb_strtolower($specs[1], 'UTF-8') : '');
        $utc = new \DateTimeZone('UTC');
        $date = static fn (?string $d): ?\DateTimeImmutable => $d !== null ? new \DateTimeImmutable($d, $utc) : null;
        $portraitPublic = PhotoService::portrait($m);
        return [
            'id' => (int) $m['id'], 'public_id' => (string) $m['public_id'], 'slug' => $m['slug'] !== null ? (string) $m['slug'] : null, 'prenom' => (string) $m['prenom'], 'nom' => (string) $m['nom'],
            'statut' => (string) $m['statut_dossier'], 'etat' => (string) $m['etat'], 'metier' => $metier, 'quartier' => (string) ($m['quartier_atelier'] ?? ''), 'quartier_dispo' => $m['quartier_dispo'] !== null ? (string) $m['quartier_dispo'] : null,
            'en_tete' => implode(' · ', array_filter([$metier, (string) ($m['quartier_atelier'] ?? '')])), 'rayon' => (int) $m['rayon_km'],
            'depuis' => $date($m['dispo_debut']), 'position_maj_le' => $date($m['position_maj_le']), 'envoye_le' => $date($m['dossier_envoye_le']),
            'note' => $m['note_moyenne'] !== null ? (float) $m['note_moyenne'] : null, 'nb_avis' => (int) $m['nb_avis'], 'telephone' => (string) $m['telephone'],
            'motif_refus' => $m['motif_refus'] !== null ? (string) $m['motif_refus'] : null,
            'photo' => $portraitPublic,
            'portrait_piece' => PieceDossierService::depuisBase()->cheminDe($id, 'portrait') !== null,
        ];
    }

    /**
     * « Aujourd'hui · mercredi 21 octobre » et les trois bornes. Indisponible, on est masqué : les clients ne voient personne.
     *
     * @return array{jour: string, appels: int, vues: int, note: ?float, nb_avis: int}
     */
    public function jour(int $id, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $debut = $maintenant->setTime(0, 0)->format('Y-m-d H:i:s');
        $m = $this->db->un('SELECT note_moyenne, nb_avis FROM mecaniciens WHERE id = :i', ['i' => $id]);
        return [
            'jour' => self::JOURS[(int) $maintenant->format('w')] . ' ' . self::jourMois($maintenant),
            'appels' => (int) $this->db->valeur('SELECT COUNT(*) FROM contacts WHERE mecanicien_id = :m AND cree_le >= :d', ['m' => $id, 'd' => $debut]),
            'vues' => (int) $this->db->valeur('SELECT COUNT(*) FROM vues_fiche WHERE mecanicien_id = :m AND jour = :j', ['m' => $id, 'j' => $maintenant->format('Y-m-d')]),
            'note' => $m['note_moyenne'] !== null ? (float) $m['note_moyenne'] : null, 'nb_avis' => (int) ($m['nb_avis'] ?? 0),
        ];
    }

    /**
     * Les dernières demandes : celles d'aujourd'hui, sinon celles d'hier (M2 indisponible). Chaque ligne : l'heure, le client (son prénom s'il l'a donné), la panne et le véhicule,
     * la distance tant que sa position existe, et de quoi le rappeler seulement s'il a laissé son numéro.
     *
     * @return array{quand: string, total: int, titre: string, lignes: list<array<string, mixed>>}
     */
    public function demandes(int $id, ?\DateTimeImmutable $maintenant = null, int $limite = 3): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $aujourdhui = $maintenant->setTime(0, 0);
        foreach (['aujourdhui' => [$aujourdhui, $aujourdhui->modify('+1 day')], 'hier' => [$aujourdhui->modify('-1 day'), $aujourdhui]] as $quand => [$du, $au]) {
            $total = (int) $this->db->valeur('SELECT COUNT(*) FROM contacts WHERE mecanicien_id = :m AND cree_le >= :d AND cree_le < :f', ['m' => $id, 'd' => $du->format('Y-m-d H:i:s'), 'f' => $au->format('Y-m-d H:i:s')]);
            if ($total === 0) {
                continue;
            }
            $lignes = $this->db->tous(
                'SELECT k.public_id, k.cree_le, k.resume, k.lat, k.lon, c.prenom, c.telephone, m.dispo_lat, m.dispo_lon, m.atelier_lat, m.atelier_lon
                   FROM contacts k JOIN mecaniciens m ON m.id = k.mecanicien_id LEFT JOIN clients c ON c.id = k.client_id
                  WHERE k.mecanicien_id = :m AND k.cree_le >= :d AND k.cree_le < :f ORDER BY k.cree_le DESC LIMIT ' . $limite,
                ['m' => $id, 'd' => $du->format('Y-m-d H:i:s'), 'f' => $au->format('Y-m-d H:i:s')]
            );
            $pannes = array_column($this->db->tous('SELECT code, libelle FROM pannes'), 'code', 'libelle');
            return [
                'quand' => $quand, 'total' => $total,
                'titre' => $quand === 'hier' ? self::NOMBRES[min($total, 10)] . ' client' . ($total > 1 ? 's' : '') . ' ' . ($total > 1 ? 'vous ont appelé' : 'vous a appelé') . ' hier' : $total . ' client' . ($total > 1 ? 's' : '') . ' ' . ($total > 1 ? 'vous ont appelé' : 'vous a appelé'),
                'lignes' => array_map(fn (array $l): array => $this->ligneDemande($l, $pannes), $lignes),
            ];
        }
        return ['quand' => 'aucune', 'total' => 0, 'titre' => '', 'lignes' => []];
    }

    /**
     * @param array<string, mixed> $l
     * @param array<string, string> $pannes libellé → code
     * @return array<string, mixed>
     */
    public function ligneDemande(array $l, array $pannes): array
    {
        $morceaux = explode(' · ', (string) $l['resume']);
        $vehicule = $morceaux[0] ?? '';
        $panne = $morceaux[1] ?? '';
        $lat = $l['dispo_lat'] ?? $l['atelier_lat'];
        $lon = $l['dispo_lon'] ?? $l['atelier_lon'];
        return [
            'contact' => (string) $l['public_id'], 'heure' => Format::heure(new \DateTimeImmutable((string) $l['cree_le'], new \DateTimeZone('UTC'))),
            'client' => ($l['prenom'] ?? '') !== '' && $l['prenom'] !== null ? (string) $l['prenom'] : 'Un client',
            'panne' => $panne, 'code_panne' => $pannes[$panne] ?? null, 'vehicule' => $vehicule, 'resume' => trim($panne . ' · ' . $vehicule, ' ·'),
            'distance' => $l['lat'] !== null && $lat !== null ? Format::distance(GeoService::distanceM((float) $l['lat'], (float) $l['lon'], (float) $lat, (float) $lon)) : null,
            'telephone' => ($l['telephone'] ?? null) !== null ? Format::telephoneE164((string) $l['telephone']) : null,
        ];
    }

    /**
     * Son dernier avis publié, de quoi l'afficher comme les clients le lisent (RG-AV5).
     *
     * @return ?array{note: int, commentaire: ?string, prenom: string, panne: string, depuis: string, reponse: ?string}
     */
    public function dernierAvis(int $id, ?\DateTimeImmutable $maintenant = null): ?array
    {
        $a = $this->db->un('SELECT note, commentaire, prenom_affiche, panne_libelle, cree_le, reponse_mecano FROM avis WHERE mecanicien_id = :m AND statut = \'publie\' ORDER BY cree_le DESC, id DESC LIMIT 1', ['m' => $id]);
        if ($a === null) {
            return null;
        }
        return ['note' => (int) $a['note'], 'commentaire' => $a['commentaire'], 'prenom' => ($a['prenom_affiche'] ?? '') !== '' && $a['prenom_affiche'] !== null ? (string) $a['prenom_affiche'] : 'Un client',
            'panne' => (string) $a['panne_libelle'], 'depuis' => Format::depuis((string) $a['cree_le'], $maintenant), 'reponse' => $a['reponse_mecano']];
    }

    /**
     * M2a · Le fil de suivi : dossier envoyé, en vérification (dès qu'une personne de l'équipe l'ouvre), validé. La réponse est annoncée sous 48 h (RG-DO3).
     *
     * @param array<string, mixed> $profil
     * @return array{numero: string, envoye_le: \DateTimeImmutable, ouvert_le: ?\DateTimeImmutable, reponse_avant: \DateTimeImmutable, pieces: int}
     */
    public function suivi(array $profil): array
    {
        $envoye = $profil['envoye_le'] ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ouvert = $this->db->valeur(
            'SELECT MIN(cree_le) FROM journal_admin WHERE cible_type = \'mecanicien\' AND cible_id = :i AND cree_le >= :e',
            ['i' => $profil['id'], 'e' => $envoye->format('Y-m-d H:i:s')]
        );
        return [
            'numero' => InscriptionService::numeroAffiche($profil['id']), 'envoye_le' => $envoye, 'ouvert_le' => $ouvert !== null ? new \DateTimeImmutable((string) $ouvert, new \DateTimeZone('UTC')) : null,
            'reponse_avant' => $envoye->modify('+' . Parametres::int('dossier.delai_annonce_h', 48) . ' hours'),
            'pieces' => count(array_filter((new PieceDossierService($this->db))->courantes($profil['id']), static fn (array $_, string $t): bool => $t !== 'atelier', ARRAY_FILTER_USE_BOTH)),
        ];
    }

    /**
     * M2b · Le verdict de chaque pièce : conforme, ou à reprendre avec le motif de l'équipe cité (RG-DO4).
     *
     * @return array{pieces: list<array{type: string, etat: string, motif: ?string}>, a_reprendre: list<string>, verifiees_le: ?\DateTimeImmutable}
     */
    public function verdicts(int $id): array
    {
        $courantes = (new PieceDossierService($this->db))->courantes($id);
        $pieces = [];
        $reprendre = [];
        $dernier = null;
        foreach (['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait'] as $type) {
            $c = $courantes[$type] ?? null;
            $etat = $c === null ? 'manquante' : ($c['statut'] === 'a_reprendre' ? 'a_reprendre' : ($c['statut'] === 'validee' ? 'conforme' : 'envoyee'));
            $pieces[] = ['type' => $type, 'etat' => $etat, 'motif' => $c['motif'] ?? null];
            if ($etat === 'a_reprendre') {
                $reprendre[] = $type;
            }
        }
        $le = $this->db->valeur('SELECT MAX(examinee_le) FROM pieces_dossier WHERE mecanicien_id = :m AND examinee_le IS NOT NULL', ['m' => $id]);
        return ['pieces' => $pieces, 'a_reprendre' => $reprendre, 'verifiees_le' => $le !== null ? new \DateTimeImmutable((string) $le, new \DateTimeZone('UTC')) : null];
    }

    /**
     * M2b · La suspension en cours : depuis quand, pour combien de temps, pourquoi (RG-D6).
     *
     * @return ?array{depuis: \DateTimeImmutable, fin: ?\DateTimeImmutable, motif: string}
     */
    public function suspension(int $id): ?array
    {
        $s = $this->db->un('SELECT debut, fin_prevue, motif FROM suspensions WHERE mecanicien_id = :m AND levee_le IS NULL ORDER BY id DESC LIMIT 1', ['m' => $id]);
        if ($s === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        return ['depuis' => new \DateTimeImmutable((string) $s['debut'], $utc), 'fin' => $s['fin_prevue'] !== null ? new \DateTimeImmutable((string) $s['fin_prevue'], $utc) : null, 'motif' => (string) $s['motif']];
    }

    /** « 10 km · Riviera 3 » : ce que voient les clients tant qu'on est disponible (RG-R4 : depuis la position de disponibilité, dans le rayon). */
    public static function zone(array $profil): string
    {
        return $profil['rayon'] . Format::INSECABLE . 'km' . (($profil['quartier_dispo'] ?? $profil['quartier']) !== '' ? ' · ' . ($profil['quartier_dispo'] ?? $profil['quartier']) : '');
    }

    /**
     * La ligne sous l'interrupteur (M2) : où l'on est vu quand on est disponible ; sinon la dernière position connue, qui sera refaite à la prochaine mise en ligne (RG-D1, RG-D2).
     *
     * @param array<string, mixed> $profil
     */
    public static function lignePosition(array $profil, ?\DateTimeImmutable $maintenant = null): string
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $maj = $profil['position_maj_le'];
        $quartier = (string) ($profil['quartier_dispo'] ?? '');
        if ($maj === null || $quartier === '') {
            return 'Aucune position pour l’instant. Elle sera enregistrée quand vous vous rendrez disponible.';
        }
        if ($profil['etat'] !== 'indisponible') {
            return 'Position' . Format::INSECABLE . ': ' . $quartier . ' · mise à jour à ' . Format::heure($maj);
        }
        $jours = (int) $maintenant->setTime(0, 0)->diff($maj->setTime(0, 0))->days;
        $quand = $jours === 0 ? 'aujourd’hui à ' : ($jours === 1 ? 'hier à ' : 'le ' . self::jourMois($maj) . ' à ');
        return 'Dernière position' . Format::INSECABLE . ': ' . $quartier . ' · ' . $quand . Format::heure($maj) . '. Elle sera mise à jour quand vous vous rendrez disponible.';
    }

    /** « 7 jours », « 24 h », ou « jusqu'à nouvel ordre » : la durée d'une suspension, en toutes lettres. */
    public static function dureeSuspension(\DateTimeImmutable $debut, ?\DateTimeImmutable $fin): string
    {
        if ($fin === null) {
            return 'jusqu’à nouvel ordre';
        }
        $h = (int) round(($fin->getTimestamp() - $debut->getTimestamp()) / 3600);
        return $h % 24 === 0 && $h >= 48 ? intdiv($h, 24) . ' jours' : $h . Format::INSECABLE . 'h';
    }
}
