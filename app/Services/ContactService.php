<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;

/**
 * Contacts client → mécano par appel direct ou WhatsApp (RG-A1, RG-A10 à RG-A12) et « Avez-vous pu le joindre ? » (RG-I4, RG-I5).
 * Chaque appui crée le contact AVANT d'ouvrir l'appel ; le numéro n'est donné qu'à ce moment-là.
 */
final class ContactService
{
    public const ISSUES = ['arrive', 'pas_entendus', 'pas_reponse'];

    /** Rubrique de la ligne étendue selon le rang (84:1478 : « SECOND PLUS PROCHE ») */
    private const RUBRIQUES = [1 => 'Le plus proche', 2 => 'Second plus proche', 3 => 'Troisième plus proche', 4 => 'Quatrième plus proche', 5 => 'Cinquième plus proche', 6 => 'Sixième plus proche', 7 => 'Septième plus proche', 8 => 'Huitième plus proche', 9 => 'Neuvième plus proche', 10 => 'Dixième plus proche'];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Crée le contact (RG-A1) et donne la cible à ouvrir : « tel: » ou le lien WhatsApp avec le message prêt (RG-A11).
     *
     * @param array{recherche: ?string, lieu: ?string, repere: ?string, vehicule: ?string, panne: ?string, lat: ?float, lon: ?float} $contexte
     * @param ?string $precedent public_id du contact raté qui mène à celui-ci (RG-I5)
     * @param bool $webrtc le navigateur sait faire un appel intégré (détecté par le JS)
     * @return array{public_id: string, canal: string, cible: string, prenom: string, mecano: string, statut: string, memo: ?array{nom: string, telephone: string, portrait: ?string}}
     * @throws ContactException
     */
    public function creer(int $client, string $mecano, string $canal, bool $position, array $contexte, ?string $precedent = null, bool $webrtc = false): array
    {
        if (!in_array($canal, ['app', 'tel', 'whatsapp'], true)) {
            throw new ContactException('canal', 'Canal inconnu.');
        }
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.prenom, m.nom, m.telephone, m.whatsapp, m.etat, m.position_maj_le,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM mecaniciens m LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE m.public_id = :p AND m.statut_dossier = \'valide\' AND m.supprime_le IS NULL',
            ['p' => $mecano]
        );
        if ($m === null || !$this->eligible($m)) {
            throw new ContactException('indisponible', (string) ($m['prenom'] ?? 'Ce mécano') . ' n’est plus disponible.');
        }
        $this->verifierLimites($client, (int) $m['id']);

        // RG-A2 : l'appel intégré est le bouton principal si le navigateur le permet ET si le mécano peut le recevoir ; sinon « Appeler » ouvre l'appel direct
        $appel = new AppelService($this->db);
        if ($canal === 'app' && (Parametres::int('appel.integre_actif', 0) !== 1 || !$webrtc || !$appel->mecanoJoignable((int) $m['id']))) {
            $canal = 'tel';
        }
        if ($canal === 'app' && $appel->appelDuClient($client) !== null) {
            throw new ContactException('appel_en_cours', 'Vous avez déjà un appel en cours.'); // RG-A4
        }
        $occupe = $canal === 'app' && $appel->mecanoOccupe((int) $m['id']); // RG-A3 : « occupé » tout de suite, on propose le suivant

        $recherche = $contexte['recherche'] !== null ? $this->db->un(
            'SELECT r.id, rr.rang FROM recherches r
               LEFT JOIN resultats_recherche rr ON rr.recherche_id = r.id AND rr.mecanicien_id = :m
              WHERE r.public_id = :r AND r.client_id = :c',
            ['m' => (int) $m['id'], 'r' => $contexte['recherche'], 'c' => $client]
        ) : null;
        $avant = $precedent !== null ? $this->db->un(
            'SELECT id FROM contacts WHERE public_id = :p AND client_id = :c AND mecanicien_id = :m',
            ['p' => $precedent, 'c' => $client, 'm' => (int) $m['id']]
        ) : null;
        // Un « suivant » (RG-I5) garde le lien vers le contact raté, même si ce n'est pas le même mécano
        $avant ??= $precedent !== null ? $this->db->un('SELECT id FROM contacts WHERE public_id = :p AND client_id = :c', ['p' => $precedent, 'c' => $client]) : null;

        $publicId = Ulid::generer();
        $this->db->inserer('contacts', [
            'public_id' => $publicId, 'recherche_id' => $recherche['id'] ?? null, 'client_id' => $client, 'mecanicien_id' => (int) $m['id'],
            'canal' => $canal, 'rang' => $recherche['rang'] ?? null, 'contact_precedent_id' => $avant['id'] ?? null, 'statut' => $occupe ? 'occupe' : 'initie',
            'termine_le' => $occupe ? gmdate('Y-m-d H:i:s') : null, 'fin_par' => $occupe ? 'mecanicien' : null,
            'resume' => mb_substr(implode(' · ', array_filter([$contexte['vehicule'], $contexte['panne']])) ?: 'Dépannage', 0, 120),
            'quartier_libelle' => $contexte['lieu'] !== null ? mb_substr($contexte['lieu'], 0, 80) : null,
            'position_partagee' => $position ? 1 : 0,
            'lat' => $position ? $contexte['lat'] : null, 'lon' => $position ? $contexte['lon'] : null,
            'repere' => $position && $contexte['repere'] !== null ? mb_substr($contexte['repere'], 0, 160) : null,
        ]);

        return [
            'public_id' => $publicId, 'canal' => $canal, 'prenom' => (string) $m['prenom'], 'mecano' => (string) $m['public_id'], 'statut' => $occupe ? 'occupe' : 'initie',
            // De quoi retrouver le mécano sans réseau (X7) : seulement pour un appel direct ou WhatsApp, où le numéro est révélé de toute façon (RG-A1)
            'memo' => $canal === 'app' ? null : ['nom' => $m['prenom'] . ' ' . $m['nom'], 'telephone' => Format::telephoneE164((string) $m['telephone']), 'portrait' => PhotoService::portrait($m)['src'] ?? null],
            'cible' => $canal === 'app' ? '/appel/' . $publicId : ($canal === 'whatsapp' ? $this->lienWhatsApp($m, $contexte, $position, false) : 'tel:' . Format::telephoneE164((string) $m['telephone'])),
        ];
    }

    /**
     * Numéro révélé après la création du contact (D1 · 159:858) : jamais avant (RG-A1).
     *
     * @param array{recherche: ?string, lieu: ?string, repere: ?string, vehicule: ?string, panne: ?string, lat: ?float, lon: ?float} $contexte
     * @return ?array{prenom: string, telephone: string, lienWhatsApp: string}
     */
    public function reveler(string $contactPublicId, int $client, array $contexte): ?array
    {
        $c = $this->db->un(
            'SELECT c.position_partagee, m.prenom, m.telephone, m.whatsapp FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id
              WHERE c.public_id = :p AND c.client_id = :c',
            ['p' => $contactPublicId, 'c' => $client]
        );
        if ($c === null) {
            return null;
        }
        return [
            'prenom' => (string) $c['prenom'], 'telephone' => Format::telephoneE164((string) $c['telephone']),
            'lienWhatsApp' => $this->lienWhatsApp($c, $contexte, (bool) $c['position_partagee'], true),
        ];
    }

    /** @param array<string, mixed> $m */
    private function eligible(array $m): bool
    {
        if ($m['etat'] !== 'disponible' || $m['position_maj_le'] === null) {
            return false;
        }
        $limite = new \DateTimeImmutable('-' . Parametres::int('dispo.duree_max_h', 12) . ' hours', new \DateTimeZone('UTC'));
        return new \DateTimeImmutable((string) $m['position_maj_le'], new \DateTimeZone('UTC')) >= $limite;
    }

    /** RG-A10 : 5 contacts par client en 10 min, 3 par heure vers le même mécano. */
    private function verifierLimites(int $client, int $mecano): void
    {
        $recent = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM contacts WHERE client_id = :c AND cree_le >= UTC_TIMESTAMP() - INTERVAL 10 MINUTE',
            ['c' => $client]
        );
        $memeMecano = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM contacts WHERE client_id = :c AND mecanicien_id = :m AND cree_le >= UTC_TIMESTAMP() - INTERVAL 1 HOUR',
            ['c' => $client, 'm' => $mecano]
        );
        if ($recent >= Parametres::int('antiabus.contacts_client_10min', 5) || $memeMecano >= Parametres::int('antiabus.contacts_meca_h', 3)) {
            throw new ContactException('limite', 'Trop d’appels en peu de temps. Réessayez dans quelques minutes.');
        }
    }

    /**
     * Lien WhatsApp avec le message prêt ; la position n'y figure que si l'interrupteur est actif (RG-A11).
     *
     * @param array<string, mixed> $m prenom, telephone, whatsapp
     * @param array<string, mixed> $contexte
     */
    private function lienWhatsApp(array $m, array $contexte, bool $position, bool $web): string
    {
        $message = FicheService::message((string) $m['prenom'], $contexte, $position ? $contexte['lat'] : null, $position ? $contexte['lon'] : null);
        $texte = $message['sans'] . ($message['position'] !== null ? ' ' . $message['position'] : '');
        $numero = ltrim(Format::telephoneE164((string) (($m['whatsapp'] ?? '') !== '' ? $m['whatsapp'] : $m['telephone'])), '+');
        return $web
            ? 'https://web.whatsapp.com/send?phone=' . $numero . '&text=' . rawurlencode($texte)
            : 'https://wa.me/' . $numero . '?text=' . rawurlencode($texte);
    }

    // ---------- Retour d'appel (C6) ----------

    /**
     * Le contact d'un client avec le mécano (portrait, prénom…), pour les écrans C6.
     *
     * @return ?array<string, mixed>
     */
    public function lire(string $publicId, int $client): ?array
    {
        $c = $this->db->un(
            'SELECT c.id, c.public_id, c.canal, c.statut, c.cree_le, c.rang, c.issue_client, c.issue_mecano, c.position_partagee, c.resume, c.quartier_libelle, c.repere,
                    c.sonne_le, c.decroche_le, c.termine_le, c.duree_s, c.fin_par, rr.distance_m, qm.nom AS quartier_mecano,
                    r.public_id AS recherche, m.public_id AS mecano, m.slug, m.prenom, m.nom, m.metier, m.identite_verifiee_le,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM contacts c
               JOIN mecaniciens m ON m.id = c.mecanicien_id
               LEFT JOIN recherches r ON r.id = c.recherche_id
               LEFT JOIN quartiers qm ON qm.id = m.dispo_quartier_id
               LEFT JOIN resultats_recherche rr ON rr.recherche_id = c.recherche_id AND rr.mecanicien_id = c.mecanicien_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE c.public_id = :p AND c.client_id = :c',
            ['p' => $publicId, 'c' => $client]
        );
        if ($c === null) {
            return null;
        }
        return [
            'id' => (int) $c['id'], 'public_id' => (string) $c['public_id'], 'canal' => (string) $c['canal'], 'rang' => $c['rang'] !== null ? (int) $c['rang'] : null,
            'heure' => Format::heure((string) $c['cree_le']), 'issue' => $c['issue_client'], 'recherche' => $c['recherche'],
            'mecano' => (string) $c['mecano'], 'slug' => (string) $c['slug'], 'prenom' => (string) $c['prenom'], 'nom_complet' => $c['prenom'] . ' ' . $c['nom'],
            'portrait' => PhotoService::portrait($c), 'lieu' => $c['quartier_libelle'], 'position_partagee' => (bool) $c['position_partagee'],
            'cree_le' => (string) $c['cree_le'], 'statut' => (string) $c['statut'], 'issue_mecano' => $c['issue_mecano'], 'metier' => (string) $c['metier'],
            'resume' => (string) $c['resume'], 'repere' => $c['position_partagee'] ? $c['repere'] : null, 'duree_s' => $c['duree_s'] !== null ? (int) $c['duree_s'] : null,
            'termine_le' => $c['termine_le'], 'fin_par' => $c['fin_par'], 'quartier_mecano' => $c['quartier_mecano'], 'distance_m' => $c['distance_m'] !== null ? (int) $c['distance_m'] : null,
        ];
    }

    /** Le numéro du mécano, donné seulement à celui qui a un contact avec lui (RG-A1) : « Appeler Koffi sur son numéro » (C5c, RG-A7). */
    public function numero(string $contactPublicId, int $client): ?string
    {
        $t = $this->db->valeur(
            'SELECT m.telephone FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id WHERE c.public_id = :p AND c.client_id = :c',
            ['p' => $contactPublicId, 'c' => $client]
        );
        return $t ? Format::telephoneE164((string) $t) : null;
    }

    /** RG-A12 : temps passé hors d'allomécano (un indice, jamais une preuve). */
    public function enregistrerAbsence(string $publicId, int $client, int $secondes): bool
    {
        return $this->db->modifier('contacts', ['absence_s' => max(0, min($secondes, 65535))], ['public_id' => $publicId, 'client_id' => $client]) > 0;
    }

    /**
     * La réponse du client (RG-I4). Une seule réponse : la première compte.
     *
     * @return bool vrai si la réponse a été enregistrée maintenant
     */
    public function repondre(string $publicId, int $client, string $issue): bool
    {
        if (!in_array($issue, self::ISSUES, true)) {
            throw new ContactException('issue', 'Réponse inconnue.');
        }
        $n = $this->db->executer(
            'UPDATE contacts SET issue_client = :i, issue_client_le = UTC_TIMESTAMP() WHERE public_id = :p AND client_id = :c AND issue_client IS NULL',
            ['i' => $issue, 'p' => $publicId, 'c' => $client]
        )->rowCount();
        return $n > 0;
    }

    /**
     * Le mécano suivant de la liste figée : pas encore contacté dans cette recherche et toujours éligible (RG-I5).
     *
     * @return ?array<string, mixed> ligne de la liste figée (rang, distance, spécialités, portrait…) + « rubrique »
     */
    public function suivant(string $contactPublicId, int $client): ?array
    {
        $contact = $this->lire($contactPublicId, $client);
        if ($contact === null || $contact['recherche'] === null) {
            return null;
        }
        $recherche = RechercheService::depuisBase()->lire($contact['recherche'], $client);
        if ($recherche === null) {
            return null;
        }
        $contactes = array_column($this->db->tous(
            'SELECT m.public_id FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id
               JOIN recherches r ON r.id = c.recherche_id WHERE r.public_id = :r AND c.client_id = :c',
            ['r' => $contact['recherche'], 'c' => $client]
        ), 'public_id');
        foreach ($recherche['resultats'] as $ligne) {
            if (in_array($ligne['public_id'], $contactes, true)) {
                continue;
            }
            $m = $this->db->un('SELECT etat, position_maj_le FROM mecaniciens WHERE public_id = :p AND statut_dossier = \'valide\' AND supprime_le IS NULL', ['p' => $ligne['public_id']]);
            if ($m !== null && $this->eligible($m)) {
                return $ligne + ['rubrique' => self::RUBRIQUES[$ligne['rang']] ?? 'Mécano suivant', 'recherche' => $recherche['recherche']];
            }
        }
        return null;
    }

    /**
     * Contact à reprendre au bandeau de la visite suivante : appel direct ou WhatsApp sans réponse, depuis moins de 24 h (RG-I4).
     *
     * @return ?array{public_id: string, prenom: string}
     */
    public function enAttenteDeReponse(int $client): ?array
    {
        $c = $this->db->un(
            'SELECT c.public_id, m.prenom FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id
              WHERE c.client_id = :c AND c.canal IN (\'tel\', \'whatsapp\') AND c.issue_client IS NULL
                AND c.cree_le >= UTC_TIMESTAMP() - INTERVAL ' . max(1, Parametres::int('retour.bandeau_h', 24)) . ' HOUR
              ORDER BY c.cree_le DESC LIMIT 1',
            ['c' => $client]
        );
        return $c === null ? null : ['public_id' => (string) $c['public_id'], 'prenom' => (string) $c['prenom']];
    }
}
