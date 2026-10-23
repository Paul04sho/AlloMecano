<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Core\Validator;

/**
 * Signalement d'un mécano par un client (RG-S1 à RG-S3) : seulement après un contact avec lui, une fois par contact, sans que le mécano
 * sache qui l'a signalé ; lu sous 24 h par un admin ; photo facultative de 300 Ko au plus, sans EXIF, rangée hors du dossier public.
 */
final class SignalementService
{
    public const DESCRIPTION_MAX = 1000;

    /** Motifs dans l'ordre de C7, avec le libellé exact de Figma (210:1797 à 210:1802). */
    public const MOTIFS = [
        'identite' => 'Ce n’est pas la personne de la photo',
        'securite' => 'Il a demandé de l’argent avant de venir',
        'comportement' => 'Comportement agressif ou menaçant',
        'prix_abusif' => 'Prix très différent de ce qui était annoncé',
        'pas_venu' => 'Il n’est jamais venu',
        'autre' => 'Autre',
    ];

    public function __construct(private readonly Database $db, private readonly ?string $dossier = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Le contact que le client signale (RG-S1) : celui du lien, sinon son dernier contact avec ce mécano pas encore signalé.
     *
     * @return array{cible?: array<string, mixed>, refus?: string} refus : inconnu | pas_de_contact | deja
     */
    public function cible(int $client, string $slug, ?string $contactPublicId): array
    {
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.slug, m.prenom, m.nom, ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM mecaniciens m LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE m.slug = :s',
            ['s' => $slug]
        );
        if ($m === null) {
            return ['refus' => 'inconnu'];
        }
        $contacts = $this->db->tous(
            'SELECT c.id, c.public_id, (SELECT COUNT(*) FROM signalements s WHERE s.contact_id = c.id) AS signale
               FROM contacts c WHERE c.client_id = :c AND c.mecanicien_id = :m ORDER BY c.id DESC',
            ['c' => $client, 'm' => $m['id']]
        );
        if ($contacts === []) {
            return ['refus' => 'pas_de_contact'];
        }
        $choisi = null;
        foreach ($contacts as $c) { // le contact du lien d'abord, s'il est encore libre
            if ($contactPublicId !== null && $c['public_id'] === $contactPublicId && (int) $c['signale'] === 0) {
                $choisi = $c;
                break;
            }
        }
        foreach ($contacts as $c) { // sinon le dernier contact libre
            if ($choisi === null && (int) $c['signale'] === 0) {
                $choisi = $c;
            }
        }
        if ($choisi === null) {
            return ['refus' => 'deja']; // RG-S1 : une fois par contact
        }
        return ['cible' => [
            'contact_id' => (int) $choisi['id'], 'contact' => (string) $choisi['public_id'], 'mecanicien_id' => (int) $m['id'], 'slug' => (string) $m['slug'],
            'prenom' => (string) $m['prenom'], 'nom_complet' => $m['prenom'] . ' ' . $m['nom'], 'portrait' => PhotoService::portrait($m),
        ]];
    }

    /**
     * @param array<string, mixed> $saisie motif, description, prenom, telephone
     * @return array{erreurs: array<string, string>, propre: array{motif: string, description: ?string, prenom: string, telephone: string}}
     */
    public function valider(array $saisie): array
    {
        $motif = (string) ($saisie['motif'] ?? '');
        $description = trim((string) ($saisie['description'] ?? ''));
        $v = Validator::verifier([
            'motif' => $motif, 'description' => $description, 'prenom' => trim((string) ($saisie['prenom'] ?? '')), 'telephone' => trim((string) ($saisie['telephone'] ?? '')),
        ], [
            'motif' => 'requis|dans:' . implode(',', array_keys(self::MOTIFS)), 'description' => 'max:' . self::DESCRIPTION_MAX,
            'prenom' => 'requis|max:40', 'telephone' => 'requis|telephone_ci',
        ]);
        $erreurs = $v->erreurs();
        if (isset($erreurs['motif'])) {
            $erreurs['motif'] = 'Choisissez ce qui ne va pas.';
        }
        if ($motif === 'autre' && $description === '' && !isset($erreurs['description'])) {
            $erreurs['description'] = 'Dites-nous ce qui s’est passé.'; // « Obligatoire seulement si vous choisissez « Autre » »
        }
        return ['erreurs' => $erreurs, 'propre' => [
            'motif' => $motif, 'description' => $description !== '' ? $description : null,
            'prenom' => trim((string) ($saisie['prenom'] ?? '')), 'telephone' => trim((string) ($saisie['telephone'] ?? '')),
        ]];
    }

    /**
     * Enregistre le signalement (RG-S1) ; la photo, si elle est là, est ré-encodée (EXIF retiré) et rangée hors du dossier public (RG-S3).
     *
     * @param array{motif: string, description: ?string, prenom: string, telephone: string} $propre
     * @param ?string $photo chemin du fichier envoyé
     * @return array{public_id?: string, erreur?: string} erreur : inconnu | pas_de_contact | deja | photo
     */
    public function enregistrer(int $client, string $slug, ?string $contactPublicId, array $propre, ?string $photo): array
    {
        $r = $this->cible($client, $slug, $contactPublicId);
        if (!isset($r['cible'])) {
            return ['erreur' => $r['refus']];
        }
        $cible = $r['cible'];
        $public = Ulid::generer();
        $fichier = null;
        if ($photo !== null) {
            $fichier = $this->ranger($photo, $public);
            if ($fichier === null) {
                return ['erreur' => 'photo'];
            }
        }
        $this->db->inserer('signalements', [
            'public_id' => $public, 'mecanicien_id' => $cible['mecanicien_id'], 'contact_id' => $cible['contact_id'], 'client_id' => $client,
            'motif' => $propre['motif'], 'description' => $propre['description'], 'photo_fichier' => $fichier, 'statut' => 'nouveau',
            'cree_le' => gmdate('Y-m-d H:i:s'),
        ]);
        ClientService::depuisBase()->enregistrerContact($client, $propre['prenom'], Format::telephoneE164($propre['telephone']));
        return ['public_id' => $public];
    }

    /**
     * Le signalement du client, pour la page « envoyé » : numéro, heure, frise de suivi (RG-S2). Jamais celui d'un autre client.
     *
     * @return ?array<string, mixed>
     */
    public function envoye(int $client, string $publicId): ?array
    {
        $s = $this->db->un(
            'SELECT s.id, s.public_id, s.motif, s.statut, s.cree_le, s.traite_le, m.prenom, m.slug, c.telephone
               FROM signalements s JOIN mecaniciens m ON m.id = s.mecanicien_id LEFT JOIN clients c ON c.id = s.client_id
              WHERE s.public_id = :p AND s.client_id = :c',
            ['p' => $publicId, 'c' => $client]
        );
        if ($s === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        $cree = new \DateTimeImmutable((string) $s['cree_le'], $utc);
        return [
            'public_id' => (string) $s['public_id'], 'numero' => sprintf('SIG-%s-%04d', $cree->format('Y'), (int) $s['id']),
            'prenom' => (string) $s['prenom'], 'slug' => (string) $s['slug'], 'cree_le' => $cree,
            'limite' => $cree->modify('+' . Parametres::int('signalement.lecture_h', 24) . ' hours'),
            'statut' => (string) $s['statut'], 'traite_le' => $s['traite_le'] !== null ? new \DateTimeImmutable((string) $s['traite_le'], $utc) : null,
            'telephone' => $s['telephone'] !== null ? Format::telephone((string) $s['telephone']) : null,
        ];
    }

    /** Le fichier ré-encodé en JPEG (rien de l'EXIF ne survit), 300 Ko au plus. Nul si ce n'est pas une image. */
    private function ranger(string $source, string $publicId): ?string
    {
        $info = @getimagesize($source);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
        };
        if ($img === false) {
            return null;
        }
        $max = Parametres::int('signalement.photo_max_ko', 300) * 1024;
        $plus = max($info[0], $info[1]);
        $cote = min($plus, 1600);
        $sortie = null;
        for ($tentative = 0; $tentative < 6; $tentative++) {
            $w = (int) max(1, round($info[0] * $cote / $plus));
            $h = (int) max(1, round($info[1] * $cote / $plus));
            $fond = imagecreatetruecolor($w, $h);
            imagefill($fond, 0, 0, (int) imagecolorallocate($fond, 255, 255, 255));
            imagecopyresampled($fond, $img, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            ob_start();
            imagejpeg($fond, null, max(40, 80 - 8 * $tentative));
            $octets = (string) ob_get_clean();
            if (strlen($octets) <= $max) {
                $sortie = $octets;
                break;
            }
            $cote = (int) ($cote * 0.8);
        }
        if ($sortie === null) {
            return null;
        }
        $dossier = $this->dossier ?? dirname(__DIR__, 2) . '/storage/signalements';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $nom = $publicId . '.jpg';
        return file_put_contents($dossier . '/' . $nom, $sortie) !== false ? $nom : null;
    }
}
