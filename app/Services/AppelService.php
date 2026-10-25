<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Appel intégré (RG-A2 à RG-A8, RG-I1, RG-I2, RG-D4, RG-D5) : l'état de l'appel dans `contacts`, les messages de signalisation WebRTC dans
 * `signalisation`. Les délais (30 s de sonnerie) se constatent quand quelqu'un interroge : pas de tâche planifiée.
 *
 * Statuts : initie → sonne → decroche → termine ; ou manque (30 s), occupe (mécano pris ou refus), echoue (micro, réseau), annule (client).
 */
final class AppelService
{
    /** Messages que chaque côté a le droit d'envoyer */
    private const TYPES = [
        'client' => ['offre', 'candidat', 'raccroche'],
        'mecanicien' => ['sonne', 'reponse', 'candidat', 'refus', 'raccroche'],
    ];
    private const CHARGE_MAX = 60000;
    /** Un appel décroché que plus personne n'interroge est clos au bout de ce délai (réseau perdu des deux côtés) */
    private const DUREE_MAX_S = 4 * 3600;

    public function __construct(private readonly Database $db, private readonly ?PushService $push = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance(), PushService::depuisBase());
    }

    // ---------- Qui peut appeler qui (RG-A2, RG-A3, RG-A4) ----------

    /** RG-A2 : un abonnement push actif, ou l'app ouverte depuis moins de 24 h. */
    public function mecanoJoignable(int $mecanoId): bool
    {
        $abonne = (int) $this->db->valeur('SELECT COUNT(*) FROM abonnements_push WHERE mecanicien_id = :m AND revoque_le IS NULL', ['m' => $mecanoId]) > 0;
        $vu = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM mecaniciens WHERE id = :m AND vu_le >= UTC_TIMESTAMP() - INTERVAL ' . max(1, Parametres::int('appel.vu_recent_h', 24)) . ' HOUR',
            ['m' => $mecanoId]
        );
        return $abonne || $vu > 0;
    }

    /** RG-A3 : le mécano est-il déjà en appel intégré ? */
    public function mecanoOccupe(int $mecanoId): bool
    {
        $this->balayer();
        return (int) $this->db->valeur('SELECT COUNT(*) FROM contacts WHERE mecanicien_id = :m AND canal = \'app\' AND statut IN (\'initie\', \'sonne\', \'decroche\')', ['m' => $mecanoId]) > 0;
    }

    /** RG-A4 : l'appel intégré en cours de ce client, s'il y en a un. */
    public function appelDuClient(int $client): ?string
    {
        $this->balayer();
        $p = $this->db->valeur('SELECT public_id FROM contacts WHERE client_id = :c AND canal = \'app\' AND statut IN (\'initie\', \'sonne\', \'decroche\') LIMIT 1', ['c' => $client]);
        return $p ? (string) $p : null;
    }

    // ---------- Vie de l'appel ----------

    /**
     * Où en est l'appel, vu du client : constate les délais (RG-A5), relance le push toutes les 5 s et mesure le temps écoulé.
     *
     * @return ?array{statut: string, sonne_depuis_s: int, en_ligne_depuis_s: ?int, duree_s: ?int, issue_mecano: ?string, fin: bool, fin_par: ?string}
     */
    public function etat(string $publicId, int $client): ?array
    {
        $c = $this->charger($publicId, 'client', $client);
        if ($c === null) {
            return null;
        }
        $this->balayer();
        $c = $this->charger($publicId, 'client', $client) ?? $c;
        if (in_array($c['statut'], ['initie', 'sonne'], true)) {
            $this->relancerPush($c);
        }
        $maintenant = microtime(true);
        $cree = $this->temps((string) $c['cree_le']);
        $decroche = $c['decroche_le'] !== null ? $this->temps((string) $c['decroche_le']) : null;
        return [
            'statut' => (string) $c['statut'],
            'sonne_depuis_s' => (int) max(0, floor($maintenant - $cree)),
            'en_ligne_depuis_s' => $decroche !== null && $c['statut'] === 'decroche' ? (int) max(0, floor($maintenant - $decroche)) : null,
            'duree_s' => $c['duree_s'] !== null ? (int) $c['duree_s'] : null,
            'issue_mecano' => $c['issue_mecano'],
            'fin' => !in_array($c['statut'], ['initie', 'sonne', 'decroche'], true),
            'fin_par' => $c['fin_par'],
        ];
    }

    /**
     * Un message de signalisation. `$qui` : id du client, ou public_id du mécano (outil de développement en attendant l'écran M5).
     *
     * @param ?string $charge JSON (SDP ou candidat ICE)
     * @return bool vrai si le message a été pris en compte
     */
    public function signaler(string $publicId, string $cote, int|string $qui, string $type, ?string $charge): bool
    {
        if (!in_array($type, self::TYPES[$cote] ?? [], true) || ($charge !== null && (strlen($charge) > self::CHARGE_MAX || json_decode($charge) === null))) {
            return false;
        }
        $c = $this->charger($publicId, $cote, $qui);
        if ($c === null) {
            return false;
        }
        $this->balayer();
        $c = $this->charger($publicId, $cote, $qui) ?? $c;
        $actif = in_array($c['statut'], ['initie', 'sonne', 'decroche'], true);
        if ($type === 'raccroche') {
            return $this->raccrocher($c, $cote);
        }
        if (!$actif) {
            return false;
        }
        $this->db->inserer('signalisation', ['contact_id' => (int) $c['id'], 'emetteur' => $cote, 'type' => $type, 'charge' => $charge]);
        switch ($type) {
            case 'offre': // le client appelle : le mécano est prévenu (RG-A5)
                $this->db->executer('UPDATE contacts SET statut = \'sonne\', sonne_le = COALESCE(sonne_le, UTC_TIMESTAMP(3)) WHERE id = :i AND statut IN (\'initie\', \'sonne\')', ['i' => $c['id']]);
                $this->prevenir($c);
                break;
            case 'reponse': // RG-A6 : décroché
                $this->db->executer('UPDATE contacts SET statut = \'decroche\', decroche_le = UTC_TIMESTAMP(3) WHERE id = :i AND statut IN (\'initie\', \'sonne\')', ['i' => $c['id']]);
                break;
            case 'refus': // le mécano décline : même issue qu'un mécano pris (RG-I1)
                $this->terminer($c, 'occupe', 'mecanicien');
                break;
        }
        return true;
    }

    /**
     * Messages de l'autre côté depuis le dernier reçu, dans l'ordre.
     *
     * @return list<array{id: int, type: string, charge: mixed}>
     */
    public function signaux(string $publicId, string $cote, int|string $qui, int $apres): array
    {
        $c = $this->charger($publicId, $cote, $qui);
        if ($c === null) {
            return [];
        }
        $autre = $cote === 'client' ? 'mecanicien' : 'client';
        return array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'type' => (string) $s['type'], 'charge' => $s['charge'] !== null ? json_decode((string) $s['charge'], true) : null], $this->db->tous(
            'SELECT id, type, charge FROM signalisation WHERE contact_id = :c AND emetteur = :e AND id > :a ORDER BY id LIMIT 60',
            ['c' => (int) $c['id'], 'e' => $autre, 'a' => max(0, $apres)]
        ));
    }

    /** Micro refusé ou audio impossible après 15 s : l'appel est « échoué » et on propose le numéro (RG-A7). */
    public function echec(string $publicId, int $client, string $raison): bool
    {
        $c = $this->charger($publicId, 'client', $client);
        if ($c === null || !in_array($raison, ['micro_refuse', 'reseau'], true) || !in_array($c['statut'], ['initie', 'sonne', 'decroche'], true)) {
            return false;
        }
        $this->terminer($c, 'echoue', $raison);
        return true;
    }

    /**
     * Le client rouvre la page d'un appel qui avait commencé : la connexion audio est perdue, on la clôt (rien ne se reprend).
     * Un appel qui n'a pas encore démarré (« initie ») est laissé tel quel.
     */
    public function interrompre(string $publicId, int $client): void
    {
        $c = $this->charger($publicId, 'client', $client);
        if ($c === null) {
            return;
        }
        if ($c['statut'] === 'decroche') {
            $this->terminer($c, 'termine', 'reseau');
        } elseif ($c['statut'] === 'sonne') {
            $this->terminer($c, 'annule', 'reseau');
        }
    }

    /** RG-I2 : « J'y vais » ou « Pas pour moi », dès que le mécano a raccroché. « J'y vais » le passe en intervention (RG-D4). */
    public function repondreMecano(string $publicId, string $mecanoPublicId, string $reponse): bool
    {
        if (!in_array($reponse, ['j_y_vais', 'pas_pour_moi'], true)) {
            return false;
        }
        $c = $this->charger($publicId, 'mecanicien', $mecanoPublicId);
        if ($c === null || !in_array($c['statut'], ['decroche', 'termine'], true) || $c['issue_mecano'] !== null) {
            return false;
        }
        $this->db->transaction(function (Database $db) use ($c, $reponse): void {
            $db->executer('UPDATE contacts SET issue_mecano = :r, issue_mecano_le = UTC_TIMESTAMP() WHERE id = :i', ['r' => $reponse, 'i' => $c['id']]);
            if ($reponse === 'j_y_vais') { // RG-D4, RG-D7 : une période « en intervention » s'ouvre dans l'historique
                (new DisponibiliteService($db))->entrerEnIntervention((int) $c['mecanicien_id']);
            }
        });
        return true;
    }

    /**
     * Appels qui sonnent chez ce mécano (écran d'appel entrant) ; chaque interrogation prouve que son app est ouverte (RG-A2).
     *
     * @return list<array{contact: string, resume: string, lieu: ?string, repere: ?string, depuis_s: int}>
     */
    public function entrants(string $mecanoPublicId): array
    {
        $id = $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $mecanoPublicId]);
        if (!$id) {
            return [];
        }
        $this->db->executer('UPDATE mecaniciens SET vu_le = UTC_TIMESTAMP() WHERE id = :m', ['m' => $id]);
        $this->balayer();
        return array_map(fn (array $c): array => [
            'contact' => (string) $c['public_id'], 'resume' => (string) $c['resume'], 'lieu' => $c['quartier_libelle'],
            'repere' => $c['position_partagee'] ? $c['repere'] : null, 'depuis_s' => (int) max(0, floor(microtime(true) - $this->temps((string) $c['cree_le']))),
        ], $this->db->tous(
            'SELECT public_id, resume, quartier_libelle, repere, position_partagee, cree_le FROM contacts
              WHERE mecanicien_id = :m AND canal = \'app\' AND statut IN (\'initie\', \'sonne\') ORDER BY cree_le',
            ['m' => $id]
        ));
    }

    /**
     * Tout ce que le mécano voit d'un appel intégré (M5) : qui, où, quelle panne, ce que le client a dit, et où en est l'appel.
     * La position du client n'existe plus au-delà de 24 h (RG-P1) ; le numéro du client n'apparaît jamais (RG-C7).
     *
     * @return ?array<string, mixed>
     */
    public function detailMecano(string $publicId, int $mecanoId): ?array
    {
        $this->balayer();
        $c = $this->db->un(
            'SELECT c.id, c.public_id, c.statut, c.fin_par, c.resume, c.quartier_libelle, c.repere, c.position_partagee, c.lat, c.lon, c.cree_le, c.decroche_le, c.termine_le, c.duree_s, c.issue_mecano,
                    r.precisions, r.precision_m, cl.prenom, m.dispo_lat, m.dispo_lon, m.atelier_lat, m.atelier_lon
               FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id LEFT JOIN recherches r ON r.id = c.recherche_id LEFT JOIN clients cl ON cl.id = c.client_id
              WHERE c.public_id = :p AND c.canal = \'app\' AND c.mecanicien_id = :m',
            ['p' => $publicId, 'm' => $mecanoId]
        );
        if ($c === null) {
            return null;
        }
        $morceaux = explode(' · ', (string) $c['resume']);
        $position = $c['position_partagee'] && $c['lat'] !== null ? ['lat' => (float) $c['lat'], 'lon' => (float) $c['lon']] : null;
        $base = $c['dispo_lat'] ?? $c['atelier_lat'];
        $utc = new \DateTimeZone('UTC');
        return [
            'contact' => (string) $c['public_id'], 'statut' => (string) $c['statut'], 'fin_par' => $c['fin_par'], 'vehicule' => $morceaux[0] ?? '', 'panne' => $morceaux[1] ?? '', 'resume' => (string) $c['resume'],
            'code_panne' => isset($morceaux[1]) ? $this->db->valeur('SELECT code FROM pannes WHERE libelle = :l', ['l' => $morceaux[1]]) : null,
            'lieu' => $c['quartier_libelle'], 'repere' => $c['position_partagee'] ? $c['repere'] : null, 'precisions' => $c['precisions'], 'prenom' => ($c['prenom'] ?? '') !== '' ? (string) $c['prenom'] : null,
            'position' => $position, 'precision_m' => $c['precision_m'] !== null ? (int) $c['precision_m'] : null,
            'distance_m' => $position !== null && $base !== null ? GeoService::distanceM($position['lat'], $position['lon'], (float) $base, (float) ($c['dispo_lon'] ?? $c['atelier_lon'])) : null,
            'depuis_s' => (int) max(0, floor(microtime(true) - $this->temps((string) $c['cree_le']))),
            'termine_le' => $c['termine_le'] !== null ? new \DateTimeImmutable((string) $c['termine_le'], $utc) : null,
            'duree_s' => $c['duree_s'] !== null ? (int) $c['duree_s'] : null, 'issue_mecano' => $c['issue_mecano'],
        ];
    }

    /** Messages de signalisation effacés 1 h après la fin de l'appel (RG-A8, RG-P3). */
    public function purgerSignalisation(): int
    {
        return $this->db->executer(
            'DELETE s FROM signalisation s JOIN contacts c ON c.id = s.contact_id
              WHERE c.statut NOT IN (\'initie\', \'sonne\', \'decroche\') AND c.termine_le < UTC_TIMESTAMP() - INTERVAL ' . max(1, Parametres::int('purge.signalisation_h', 1)) . ' HOUR'
        )->rowCount();
    }

    // ---------- interne ----------

    /** @return ?array<string, mixed> le contact d'appel intégré, s'il appartient bien à celui qui le demande */
    private function charger(string $publicId, string $cote, int|string $qui): ?array
    {
        if ($cote === 'client') {
            return $this->db->un('SELECT * FROM contacts WHERE public_id = :p AND canal = \'app\' AND client_id = :q', ['p' => $publicId, 'q' => (int) $qui]);
        }
        return $this->db->un(
            'SELECT c.* FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id WHERE c.public_id = :p AND c.canal = \'app\' AND m.public_id = :q',
            ['p' => $publicId, 'q' => (string) $qui]
        );
    }

    /** @param array<string, mixed> $c */
    private function raccrocher(array $c, string $cote): bool
    {
        if ($c['statut'] === 'decroche') {
            $this->terminer($c, 'termine', $cote);
            return true;
        }
        if (in_array($c['statut'], ['initie', 'sonne'], true)) {
            $this->terminer($c, $cote === 'client' ? 'annule' : 'occupe', $cote);
            return true;
        }
        return false; // déjà fini : raccrocher deux fois ne change rien
    }

    /** @param array<string, mixed> $c */
    private function terminer(array $c, string $statut, string $finPar): void
    {
        $finPar = $finPar === 'client' ? 'client' : ($finPar === 'mecanicien' ? 'mecanicien' : $finPar);
        $this->db->executer(
            'UPDATE contacts SET statut = :s, termine_le = UTC_TIMESTAMP(3), fin_par = :f,
                    duree_s = IF(decroche_le IS NOT NULL, TIMESTAMPDIFF(SECOND, decroche_le, UTC_TIMESTAMP(3)), NULL)
              WHERE id = :i AND statut IN (\'initie\', \'sonne\', \'decroche\')',
            ['s' => $statut, 'f' => $finPar, 'i' => $c['id']]
        );
        if ($statut === 'manque') {
            $this->verifierInjoignable((int) $c['mecanicien_id']);
        }
    }

    /** RG-A5 : après 30 s sans réponse l'appel est « manqué » ; un appel décroché abandonné est clos. */
    private function balayer(): void
    {
        $sonnerie = max(5, Parametres::int('appel.sonnerie_s', 30));
        $perdus = $this->db->tous(
            'SELECT id, mecanicien_id FROM contacts WHERE canal = \'app\' AND statut IN (\'initie\', \'sonne\') AND cree_le < UTC_TIMESTAMP(3) - INTERVAL ' . $sonnerie . ' SECOND'
        );
        foreach ($perdus as $c) {
            $this->terminer($c, 'manque', 'delai');
        }
        $this->db->executer(
            'UPDATE contacts SET statut = \'termine\', termine_le = UTC_TIMESTAMP(3), fin_par = \'reseau\', duree_s = TIMESTAMPDIFF(SECOND, decroche_le, UTC_TIMESTAMP(3))
              WHERE canal = \'app\' AND statut = \'decroche\' AND decroche_le < UTC_TIMESTAMP(3) - INTERVAL ' . self::DUREE_MAX_S . ' SECOND'
        );
    }

    /** RG-D5 : 3 appels manqués d'affilée en moins de 2 h → indisponible, avec une notification. Un appel décroché remet le compteur à zéro. */
    private function verifierInjoignable(int $mecanoId): void
    {
        $seuil = max(1, Parametres::int('injoignable.seuil', 3));
        $derniers = array_column($this->db->tous(
            'SELECT statut FROM contacts WHERE mecanicien_id = :m AND canal = \'app\' AND statut NOT IN (\'initie\', \'sonne\', \'annule\', \'echoue\')
               AND cree_le >= UTC_TIMESTAMP() - INTERVAL ' . max(1, Parametres::int('injoignable.fenetre_h', 2)) . ' HOUR
             ORDER BY cree_le DESC LIMIT ' . $seuil,
            ['m' => $mecanoId]
        ), 'statut');
        if (count($derniers) < $seuil || array_unique($derniers) !== ['manque']) {
            return;
        }
        if ($this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :m', ['m' => $mecanoId]) === 'disponible') { // RG-D7 : la période de l'historique porte sa raison
            (new DisponibiliteService($this->db))->changer($mecanoId, 'indisponible', 'auto_injoignable', null, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->db->inserer('notifications', ['mecanicien_id' => $mecanoId, 'type' => 'indispo_auto', 'canal' => 'push', 'statut' => 'en_attente',
                'charge' => json_encode(['titre' => 'Vous êtes passé indisponible', 'texte' => $seuil . ' appels manqués d’affilée.', 'url' => '/pro'], JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** @param array<string, mixed> $c */
    private function prevenir(array $c): void
    {
        $charge = $this->chargePush($c);
        $this->push?->appelEntrant((int) $c['mecanicien_id'], $charge);
    }

    /** RG-A5 : la notification est renvoyée toutes les 5 s tant que ça sonne. */
    private function relancerPush(array $c): void
    {
        if ($this->push === null || $c['sonne_le'] === null) {
            return;
        }
        $dernier = $this->push->dernierPush((int) $c['mecanicien_id'], (string) $c['public_id']);
        if ($dernier === null || time() - $dernier->getTimestamp() >= max(1, Parametres::int('appel.relance_push_s', 5))) {
            $this->prevenir($c);
        }
    }

    /** @return array{contact: string, titre: string, texte: string, url: string} */
    private function chargePush(array $c): array
    {
        return ['contact' => (string) $c['public_id'], 'titre' => 'Appel allomécano', 'texte' => (string) $c['resume'] . ($c['quartier_libelle'] ? ' · ' . $c['quartier_libelle'] : ''), 'url' => '/pro/appel/' . $c['public_id']];
    }

    private function temps(string $dateUtc): float
    {
        return (float) (new \DateTimeImmutable($dateUtc, new \DateTimeZone('UTC')))->format('U.u');
    }
}
