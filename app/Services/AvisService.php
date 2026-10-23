<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Validator;

/**
 * Avis d'un client sur un mécano (RG-AV1 à RG-AV7) : seulement après un vrai contact, un par contact, modifiable 24 h,
 * publié tout de suite, note et nombre d'avis recalculés à chaque ajout (la fiche affiche « Nouveau » sous 3 avis).
 */
final class AvisService
{
    public const COMMENTAIRE_MAX = 600;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** @return list<array{id: int, code: string, libelle: string, polarite: string}> */
    public function etiquettes(): array
    {
        return array_map(static fn (array $e): array => ['id' => (int) $e['id'], 'code' => (string) $e['code'], 'libelle' => (string) $e['libelle'], 'polarite' => (string) $e['polarite']],
            $this->db->tous('SELECT id, code, libelle, polarite FROM etiquettes_avis ORDER BY ordre'));
    }

    /**
     * Le contact sur lequel le client peut donner son avis, ou la raison du refus (RG-AV1) :
     * appel intégré décroché depuis plus de 30 s, OU « Il arrive » du client, OU « J'y vais » du mécano, dans les 7 jours.
     *
     * @return array{contact?: array<string, mixed>, refus?: string}
     */
    public function droit(int $client, string $contactPublicId): array
    {
        $c = $this->db->un(
            'SELECT c.id, c.public_id, c.mecanicien_id, c.canal, c.statut, c.duree_s, c.issue_client, c.issue_mecano, c.resume, c.quartier_libelle, c.cree_le,
                    m.prenom, m.nom, m.slug, m.public_id AS mecano, ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE c.public_id = :p AND c.client_id = :c',
            ['p' => $contactPublicId, 'c' => $client]
        );
        if ($c === null) {
            return ['refus' => 'inconnu'];
        }
        $appelLong = $c['canal'] === 'app' && $c['statut'] === 'termine' && (int) $c['duree_s'] > Parametres::int('avis.duree_min_s', 30);
        if (!$appelLong && $c['issue_client'] !== 'arrive' && $c['issue_mecano'] !== 'j_y_vais') {
            return ['refus' => 'pas_de_droit'];
        }
        $limite = new \DateTimeImmutable('-' . Parametres::int('avis.delai_j', 7) . ' days', new \DateTimeZone('UTC'));
        $existant = $this->avisDuContact((int) $c['id']);
        if (new \DateTimeImmutable((string) $c['cree_le'], new \DateTimeZone('UTC')) < $limite && $existant === null) {
            return ['refus' => 'delai'];
        }
        return ['contact' => [
            'id' => (int) $c['id'], 'public_id' => (string) $c['public_id'], 'mecano' => (string) $c['mecano'], 'slug' => (string) $c['slug'], 'prenom' => (string) $c['prenom'],
            'nom_complet' => $c['prenom'] . ' ' . $c['nom'], 'portrait' => PhotoService::portrait($c), 'cree_le' => (string) $c['cree_le'], 'resume' => (string) $c['resume'],
            'lieu' => $c['quartier_libelle'], 'avis' => $existant,
        ]];
    }

    /**
     * L'avis déjà donné sur ce contact, avec ses étiquettes ; modifiable 24 h (RG-AV2).
     *
     * @return ?array{id: int, note: int, commentaire: ?string, prenom: ?string, etiquettes: list<int>, modifiable: bool, cree_le: string, panne: string}
     */
    public function avisDuContact(int $contactId): ?array
    {
        $a = $this->db->un('SELECT id, note, commentaire, prenom_affiche, panne_libelle, cree_le FROM avis WHERE contact_id = :c', ['c' => $contactId]);
        if ($a === null) {
            return null;
        }
        $fin = (new \DateTimeImmutable((string) $a['cree_le'], new \DateTimeZone('UTC')))->modify('+' . Parametres::int('avis.modifiable_h', 24) . ' hours');
        return [
            'id' => (int) $a['id'], 'note' => (int) $a['note'], 'commentaire' => $a['commentaire'], 'prenom' => $a['prenom_affiche'], 'panne' => (string) $a['panne_libelle'],
            'etiquettes' => array_map('intval', array_column($this->db->tous('SELECT etiquette_id FROM avis_etiquettes WHERE avis_id = :a', ['a' => $a['id']]), 'etiquette_id')),
            'modifiable' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')) < $fin, 'cree_le' => (string) $a['cree_le'],
        ];
    }

    /**
     * Valide la saisie (note 1 à 5, étiquettes connues, commentaire de 600 caractères au plus, prénom et téléphone pour vérifier le vrai appel).
     *
     * @param array<string, mixed> $saisie note, etiquettes[], commentaire, prenom, telephone
     * @return array{erreurs: array<string, string>, propre: array{note: int, etiquettes: list<int>, commentaire: ?string, prenom: string, telephone: string}}
     */
    public function valider(array $saisie): array
    {
        $v = Validator::verifier([
            'note' => $saisie['note'] ?? null, 'commentaire' => trim((string) ($saisie['commentaire'] ?? '')),
            'prenom' => trim((string) ($saisie['prenom'] ?? '')), 'telephone' => trim((string) ($saisie['telephone'] ?? '')),
        ], [
            'note' => 'requis|dans:1,2,3,4,5', 'commentaire' => 'max:' . self::COMMENTAIRE_MAX,
            'prenom' => 'requis|max:40', 'telephone' => 'requis|telephone_ci',
        ]);
        $erreurs = $v->erreurs();
        if (isset($erreurs['note'])) {
            $erreurs['note'] = 'Touchez une étoile pour donner votre note.';
        }
        $connues = array_column($this->etiquettes(), 'id');
        $choisies = array_values(array_unique(array_filter(array_map('intval', (array) ($saisie['etiquettes'] ?? [])), static fn (int $i): bool => $i > 0)));
        $choisies = array_values(array_intersect($choisies, $connues)); // une étiquette inconnue est simplement ignorée
        $commentaire = trim((string) ($saisie['commentaire'] ?? ''));
        return ['erreurs' => $erreurs, 'propre' => [
            'note' => (int) ($saisie['note'] ?? 0), 'etiquettes' => $choisies, 'commentaire' => $commentaire !== '' ? $commentaire : null,
            'prenom' => trim((string) ($saisie['prenom'] ?? '')), 'telephone' => trim((string) ($saisie['telephone'] ?? '')),
        ]];
    }

    /**
     * Publie (ou modifie, dans les 24 h) l'avis, puis recalcule la note du mécano (RG-AV4, RG-AV6).
     *
     * @param array{note: int, etiquettes: list<int>, commentaire: ?string, prenom: string, telephone: string} $propre
     * @return array{erreur?: string, avis?: array<string, mixed>}
     */
    public function publier(int $client, string $contactPublicId, array $propre): array
    {
        $droit = $this->droit($client, $contactPublicId);
        if (!isset($droit['contact'])) {
            return ['erreur' => $droit['refus']];
        }
        $c = $droit['contact'];
        $existant = $c['avis'];
        if ($existant !== null && !$existant['modifiable']) {
            return ['erreur' => 'verrouille']; // RG-AV2
        }
        $panne = $this->panne($c['resume']);
        $this->db->transaction(function (Database $db) use ($client, $c, $existant, $propre, $panne): void {
            $champs = ['note' => $propre['note'], 'commentaire' => $propre['commentaire'], 'prenom_affiche' => $propre['prenom']];
            if ($existant === null) {
                $avisId = $db->inserer('avis', $champs + ['contact_id' => $c['id'], 'mecanicien_id' => $this->mecanoId($c['mecano']), 'client_id' => $client, 'panne_libelle' => $panne, 'statut' => 'publie']);
            } else {
                $avisId = $existant['id'];
                $db->modifier('avis', $champs, ['id' => $avisId]);
                $db->executer('DELETE FROM avis_etiquettes WHERE avis_id = :a', ['a' => $avisId]);
            }
            foreach ($propre['etiquettes'] as $e) {
                $db->inserer('avis_etiquettes', ['avis_id' => $avisId, 'etiquette_id' => $e]);
            }
            // Prénom et téléphone servent à vérifier le vrai appel ; le téléphone n'est jamais affiché (RG-AV5)
            ClientService::depuisBase()->enregistrerContact($client, $propre['prenom'], Format::telephoneE164($propre['telephone']));
            $this->recalculer($this->mecanoId($c['mecano']));
        });
        return ['avis' => $this->avisDuContact($c['id']) + ['mecano' => $c['mecano'], 'prenom_mecano' => $c['prenom'], 'portrait' => $c['portrait'], 'lieu' => $c['lieu']]];
    }

    /** RG-AV6 : moyenne et nombre d'avis publiés, recalculés à chaque ajout ou masquage. */
    public function recalculer(int $mecanoId): void
    {
        $this->db->executer(
            'UPDATE mecaniciens SET nb_avis = (SELECT COUNT(*) FROM avis WHERE mecanicien_id = :m AND statut = \'publie\'),
                    note_moyenne = (SELECT ROUND(AVG(note), 1) FROM avis WHERE mecanicien_id = :m2 AND statut = \'publie\')
              WHERE id = :m3',
            ['m' => $mecanoId, 'm2' => $mecanoId, 'm3' => $mecanoId]
        );
    }

    /**
     * Bandeau « Comment ça s'est passé avec Koffi ? » (RG-AV7) : 2 h après « Il arrive » / « J'y vais » ou la fin de l'appel, pendant 24 h, tant qu'il n'y a pas d'avis.
     *
     * @return ?array{public_id: string, prenom: string}
     */
    public function bandeau(int $client): ?array
    {
        $c = $this->db->un(
            'SELECT c.public_id, c.id, m.prenom FROM contacts c JOIN mecaniciens m ON m.id = c.mecanicien_id
              WHERE c.client_id = :c AND NOT EXISTS (SELECT 1 FROM avis a WHERE a.contact_id = c.id)
                AND (c.issue_mecano = \'j_y_vais\' OR c.issue_client = \'arrive\' OR (c.canal = \'app\' AND c.statut = \'termine\' AND c.duree_s > ' . Parametres::int('avis.duree_min_s', 30) . '))
                AND COALESCE(c.issue_mecano_le, c.issue_client_le, c.termine_le) BETWEEN UTC_TIMESTAMP() - INTERVAL 26 HOUR AND UTC_TIMESTAMP() - INTERVAL 2 HOUR
              ORDER BY c.cree_le DESC LIMIT 1',
            ['c' => $client]
        );
        return $c === null ? null : ['public_id' => (string) $c['public_id'], 'prenom' => (string) $c['prenom']];
    }

    /**
     * Le bandeau à montrer au client : d'abord « Avez-vous pu joindre Koffi ? » (RG-I4), sinon « Comment ça s'est passé ? » (RG-AV7).
     *
     * @return ?array{public_id: string, prenom: string, texte?: string, href?: string, action?: string}
     */
    public static function bandeauClient(int $client): ?array
    {
        $reponse = ContactService::depuisBase()->enAttenteDeReponse($client);
        if ($reponse !== null) {
            return $reponse;
        }
        $avis = self::depuisBase()->bandeau($client);
        return $avis === null ? null : $avis + ['texte' => 'Comment ça s’est passé avec ' . $avis['prenom'] . ' ?', 'href' => '/avis/' . $avis['public_id'], 'action' => 'Donner mon avis'];
    }

    private function mecanoId(string $publicId): int
    {
        return (int) $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $publicId]);
    }

    /** « Voiture · Batterie » → « Batterie » : la panne affichée sous l'avis (RG-AV5). */
    private function panne(string $resume): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('·', $resume))));
        return mb_substr(count($parts) > 1 ? (string) end($parts) : ($parts[0] ?? 'Dépannage'), 0, 60);
    }
}
