<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * La disponibilité du mécanicien (M2) : l'interrupteur, la position, la session de 12 h.
 * RG-D1 se rendre disponible exige un dossier validé et une position (GPS récent) qui lance la session ; RG-D2 la position se met à jour d'un geste ;
 * RG-D3 au bout de 12 h la session s'arrête toute seule (notification et e-mail) ; RG-D6 une suspension verrouille l'interrupteur ;
 * RG-D7 chaque changement d'état ouvre une nouvelle période de l'historique, avec sa raison.
 */
final class DisponibiliteService
{
    public function __construct(private readonly Database $db, private readonly ?MailService $mail = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * RG-D1 : « Me rendre disponible ». La position envoyée par le navigateur devient la base de la recherche (RG-R4).
     *
     * @param array<string, mixed> $position lat, lon, precision
     * @return array{ok: true, etat: string, depuis: string, quartier: ?string, maj: string}|array{ok: false, erreur: string}
     */
    public function rendreDisponible(int $id, array $position, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT statut_dossier, etat FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        if ($m === null) {
            return ['ok' => false, 'erreur' => 'Compte introuvable.'];
        }
        if ($m['statut_dossier'] !== 'valide') { // RG-D1, RG-D6 : seulement un dossier validé, jamais un compte suspendu
            return ['ok' => false, 'erreur' => $m['statut_dossier'] === 'suspendu' ? 'Votre compte est suspendu : vous ne pouvez pas vous rendre disponible.' : 'Vous pourrez vous rendre disponible dès que votre dossier sera validé.'];
        }
        $p = PositionService::valider($position);
        if ($p === 'hors_abidjan') {
            return ['ok' => false, 'erreur' => 'Cette position n’est pas à Abidjan. Rapprochez-vous de votre atelier, puis réessayez.'];
        }
        if (is_string($p)) {
            return ['ok' => false, 'erreur' => 'Votre position n’est pas lisible. Activez la localisation, puis réessayez.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->changer($id, 'disponible', 'manuel', $p, $maintenant);
        return $this->etat($id, $maintenant) + ['ok' => true];
    }

    /** « Passer indisponible » : la position reste celle d'hier soir, elle sera refaite à la prochaine mise en ligne. @return array{ok: true, etat: string, depuis: string, quartier: ?string, maj: string} */
    public function rendreIndisponible(int $id, string $raison = 'manuel', ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->changer($id, 'indisponible', $raison, null, $maintenant);
        return $this->etat($id, $maintenant) + ['ok' => true];
    }

    /**
     * RG-D2 : « Mettre à jour ma position » (seulement quand on est disponible ou en intervention). @param array<string, mixed> $position
     *
     * @return array{ok: true, etat: string, depuis: string, quartier: ?string, maj: string}|array{ok: false, erreur: string}
     */
    public function majPosition(int $id, array $position, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT etat, statut_dossier FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        if ($m === null || $m['statut_dossier'] !== 'valide' || $m['etat'] === 'indisponible') {
            return ['ok' => false, 'erreur' => 'Rendez-vous disponible pour mettre à jour votre position.'];
        }
        $p = PositionService::valider($position);
        if (!is_array($p)) {
            return ['ok' => false, 'erreur' => $p === 'hors_abidjan' ? 'Cette position n’est pas à Abidjan. Rapprochez-vous de votre atelier, puis réessayez.' : 'Votre position n’est pas lisible. Activez la localisation, puis réessayez.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $q = PositionService::depuisBase()->quartierLePlusProche($p['lat'], $p['lon']);
        $this->db->modifier('mecaniciens', ['dispo_lat' => $p['lat'], 'dispo_lon' => $p['lon'], 'dispo_precision_m' => $p['precision_m'], 'dispo_quartier_id' => $this->quartierId($q), 'position_maj_le' => $maintenant->format('Y-m-d H:i:s')], ['id' => $id]);
        return $this->etat($id, $maintenant) + ['ok' => true];
    }

    /**
     * RG-D3 : les sessions de plus de 12 h s'arrêtent, avec une notification et un e-mail. Lancé chaque heure par la purge et à chaque ouverture du tableau de bord.
     *
     * @return int mécanos passés indisponibles
     */
    public function expirer(?\DateTimeImmutable $maintenant = null, ?int $seulement = null): int
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $limite = $maintenant->modify('-' . Parametres::int('dispo.duree_max_h', 12) . ' hours')->format('Y-m-d H:i:s');
        $n = 0;
        $filtre = $seulement !== null ? ' AND id = :m' : '';
        $params = $seulement !== null ? ['m' => $seulement] : [];
        foreach ($this->db->tous(
            'SELECT id, prenom, email, etat FROM mecaniciens WHERE etat IN (\'disponible\', \'en_intervention\') AND supprime_le IS NULL AND dispo_debut IS NOT NULL AND dispo_debut <= :l' . $filtre,
            ['l' => $limite] + $params
        ) as $m) {
            if ($m['etat'] === 'en_intervention') {
                $this->finIntervention((int) $m['id'], 'auto', $maintenant);
            }
            $this->changer((int) $m['id'], 'indisponible', 'auto_12h', null, $maintenant);
            $this->prevenir((int) $m['id'], $m['email'], 'Vous êtes passé indisponible', 'Bonjour ' . $m['prenom'] . ', votre session de disponibilité a duré ' . Parametres::int('dispo.duree_max_h', 12) . ' h : vous êtes passé indisponible. Rendez-vous disponible quand vous voulez.');
            $n++;
        }
        // RG-D4 : « en intervention » se termine tout seul après 2 h
        $fin = $maintenant->modify('-' . max(1, Parametres::int('intervention.fin_auto_h', 2)) . ' hours')->format('Y-m-d H:i:s');
        foreach ($this->db->tous(
            'SELECT id, prenom, email FROM mecaniciens WHERE etat = \'en_intervention\' AND supprime_le IS NULL AND etat_depuis <= :f' . $filtre,
            ['f' => $fin] + $params
        ) as $m) {
            $this->finIntervention((int) $m['id'], 'auto', $maintenant);
            $this->changer((int) $m['id'], 'disponible', 'auto_fin_intervention', null, $maintenant);
            $this->prevenir((int) $m['id'], $m['email'], 'Vous êtes de nouveau disponible', 'Bonjour ' . $m['prenom'] . ', votre intervention a duré ' . Parametres::int('intervention.fin_auto_h', 2) . ' h : vous êtes de nouveau disponible pour les clients.', 'fin_intervention_auto');
            $n++;
        }
        return $n;
    }

    /**
     * RG-D4 : « J'y vais » le fait passer en intervention : il disparaît des résultats. Sa session de disponibilité continue (RG-D3).
     *
     * @return bool vrai s'il était disponible
     */
    public function entrerEnIntervention(int $id, ?\DateTimeImmutable $maintenant = null): bool
    {
        if ($this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]) !== 'disponible') {
            return false;
        }
        $this->changer($id, 'en_intervention', 'intervention', null, $maintenant ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        return true;
    }

    /**
     * RG-D4 : « Je suis libre ». Il redevient disponible, ou indisponible si sa session dépasse déjà 12 h.
     *
     * @return array{ok: true, etat: string, depuis: string, quartier: ?string, maj: string}|array{ok: false, erreur: string}
     */
    public function liberer(int $id, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT etat, dispo_debut FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        if ($m === null || $m['etat'] !== 'en_intervention') {
            return ['ok' => false, 'erreur' => 'Vous n’êtes pas en intervention.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $trop = $m['dispo_debut'] !== null && new \DateTimeImmutable((string) $m['dispo_debut'], new \DateTimeZone('UTC')) <= $maintenant->modify('-' . Parametres::int('dispo.duree_max_h', 12) . ' hours');
        $this->finIntervention($id, 'mecanicien', $maintenant);
        $this->changer($id, $trop ? 'indisponible' : 'disponible', $trop ? 'auto_12h' : 'manuel', null, $maintenant);
        return $this->etat($id, $maintenant) + ['ok' => true];
    }

    /** La fin de l'intervention est notée sur le contact qui l'avait déclenchée : qui y a mis fin (mécanicien, client ou délai). */
    private function finIntervention(int $id, string $par, \DateTimeImmutable $maintenant): void
    {
        $this->db->executer(
            'UPDATE contacts SET intervention_fin_le = :f, intervention_fin_par = :p WHERE mecanicien_id = :m AND issue_mecano = \'j_y_vais\' AND intervention_fin_le IS NULL',
            ['f' => $maintenant->format('Y-m-d H:i:s'), 'p' => $par, 'm' => $id]
        );
    }

    /**
     * L'état affiché sur M2 : depuis quand, où, position mise à jour à quelle heure.
     *
     * @return array{etat: string, depuis: string, quartier: ?string, maj: string}
     */
    public function etat(int $id, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT m.etat, m.etat_depuis, m.dispo_debut, m.position_maj_le, q.nom AS quartier FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.dispo_quartier_id WHERE m.id = :i', ['i' => $id]);
        $utc = new \DateTimeZone('UTC');
        return [
            'etat' => (string) ($m['etat'] ?? 'indisponible'),
            'depuis' => $m['dispo_debut'] !== null ? Format::heure(new \DateTimeImmutable((string) $m['dispo_debut'], $utc)) : '',
            'quartier' => $m['quartier'] !== null ? (string) $m['quartier'] : null,
            'maj' => $m['position_maj_le'] !== null ? Format::heure(new \DateTimeImmutable((string) $m['position_maj_le'], $utc)) : '',
        ];
    }

    /**
     * RG-D7 : ferme la période en cours, en ouvre une autre avec sa raison, et change l'état du mécano. Une nouvelle session (disponible) repart de zéro.
     *
     * @param ?array{lat: float, lon: float, precision_m: ?int} $position
     */
    public function changer(int $id, string $etat, string $raison, ?array $position, \DateTimeImmutable $maintenant): void
    {
        $quand = $maintenant->format('Y-m-d H:i:s');
        $this->db->transaction(function () use ($id, $etat, $raison, $position, $quand): void {
            $maj = ['etat' => $etat, 'etat_depuis' => $quand];
            $quartier = null;
            if ($position !== null) {
                $q = PositionService::depuisBase()->quartierLePlusProche($position['lat'], $position['lon']);
                $quartier = $this->quartierId($q);
                $maj += ['dispo_lat' => $position['lat'], 'dispo_lon' => $position['lon'], 'dispo_precision_m' => $position['precision_m'], 'dispo_quartier_id' => $quartier, 'position_maj_le' => $quand];
            }
            if ($etat === 'disponible' && $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $id]) !== 'en_intervention') {
                $maj['dispo_debut'] = $quand; // une nouvelle session de 12 h ; revenir d'une intervention ne la relance pas (RG-D3, RG-D4)
            }
            $this->db->executer('UPDATE historique_disponibilite SET fin = :f WHERE mecanicien_id = :m AND fin IS NULL', ['f' => $quand, 'm' => $id]);
            $this->db->inserer('historique_disponibilite', ['mecanicien_id' => $id, 'etat' => $etat, 'raison' => $raison, 'quartier_id' => $quartier, 'debut' => $quand]);
            $this->db->modifier('mecaniciens', $maj, ['id' => $id]);
        });
    }

    /** @param ?array<string, mixed> $quartier */
    private function quartierId(?array $quartier): mixed
    {
        return $quartier !== null ? $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => $quartier['slug']]) : null;
    }

    /** Une notification, et un e-mail s'il y a une adresse (RG-D3). */
    private function prevenir(int $mecano, mixed $email, string $titre, string $texte, string $type = 'indispo_auto'): void
    {
        $envoye = is_string($email) && $email !== '' && ($this->mail ?? MailService::depuisEnv())->envoyer($email, $titre, $texte . "\n\nallomécano");
        $this->db->inserer('notifications', ['mecanicien_id' => $mecano, 'type' => $type, 'canal' => is_string($email) && $email !== '' ? 'email' : 'push', 'statut' => $envoye ? 'envoyee' : 'en_attente',
            'charge' => json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => '/pro'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'cree_le' => gmdate('Y-m-d H:i:s'), 'envoyee_le' => $envoye ? gmdate('Y-m-d H:i:s') : null]);
    }
}
