<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Le tableau de bord de l'administration (A0) et les compteurs du rail : ce qui attend l'équipe (dossiers, signalements),
 * ce qui est disponible maintenant, et si les clients arrivent à joindre leurs mécanos (RG-I8 côté client).
 */
final class AdminService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** @return ?array{id: int, prenom: string, nom: string, nom_complet: string, filigrane: string, est_principal: bool, mot_de_passe_a_changer: bool} filigrane : « A. Bamba » (RG-P6) */
    public function admin(int $id): ?array
    {
        $a = $this->db->un('SELECT id, prenom, nom, est_principal, mot_de_passe_a_changer FROM admins WHERE id = :i AND actif = 1', ['i' => $id]);
        if ($a === null) {
            return null;
        }
        return ['id' => (int) $a['id'], 'prenom' => (string) $a['prenom'], 'nom' => (string) $a['nom'], 'nom_complet' => $a['prenom'] . ' ' . $a['nom'],
            'filigrane' => mb_substr((string) $a['prenom'], 0, 1) . '. ' . $a['nom'], 'est_principal' => (bool) $a['est_principal'], 'mot_de_passe_a_changer' => (bool) $a['mot_de_passe_a_changer']];
    }

    /** @return array{dossiers: int, signalements: int} les deux pastilles du rail */
    public function compteurs(): array
    {
        return [
            'dossiers' => (int) $this->db->valeur('SELECT COUNT(*) FROM mecaniciens WHERE statut_dossier = \'en_attente\' AND supprime_le IS NULL'),
            'signalements' => (int) $this->db->valeur('SELECT COUNT(*) FROM signalements WHERE statut IN (\'nouveau\', \'en_cours\')'),
        ];
    }

    /**
     * Un signalement ouvert est « critique » quand son motif met quelqu'un en danger : sécurité, identité, comportement (RG-S2, gravités de A4).
     *
     * @return array{ouverts: int, critiques: int}
     */
    public function signalementsOuverts(?\DateTimeImmutable $maintenant = null): array
    {
        $d = SignalementAdminService::depuisBase()->decompte($maintenant);
        return ['ouverts' => $d['ouverts'], 'critiques' => $d['critiques']];
    }

    /**
     * Les quatre indicateurs d'A0.
     *
     * @return array{attente: int, plus_ancien_h: ?int, signalements: array{ouverts: int, critiques: int}, disponibles: int, joignables_pct: ?int}
     */
    public function indicateurs(?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $d = $this->db->un('SELECT COUNT(*) AS n, MIN(dossier_envoye_le) AS plus_ancien FROM mecaniciens WHERE statut_dossier = \'en_attente\' AND supprime_le IS NULL');
        $plusAncien = $d['plus_ancien'] !== null ? (int) floor(($maintenant->getTimestamp() - (new \DateTimeImmutable((string) $d['plus_ancien'], new \DateTimeZone('UTC')))->getTimestamp()) / 3600) : null;
        // « Disponibles maintenant » : mêmes critères que la recherche (RG-R3)
        $dispos = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM mecaniciens WHERE statut_dossier = \'valide\' AND etat = \'disponible\' AND supprime_le IS NULL AND position_maj_le >= :depuis',
            ['depuis' => $maintenant->modify('-' . Parametres::int('dispo.duree_max_h', 12) . ' hours')->format('Y-m-d H:i:s')]
        );
        // « Clients qui ont pu joindre leur mécano (7 j) » : parmi les clients qui ont répondu à « Avez-vous pu le joindre ? », ceux qui n'ont pas dit « Non, pas de réponse »
        $j = $this->db->un(
            'SELECT COUNT(*) AS reponses, SUM(issue_client IN (\'arrive\', \'pas_entendus\')) AS joints FROM contacts WHERE issue_client IS NOT NULL AND cree_le >= :depuis',
            ['depuis' => $maintenant->modify('-7 days')->format('Y-m-d H:i:s')]
        );
        $pct = (int) ($j['reponses'] ?? 0) > 0 ? (int) round(100 * (int) $j['joints'] / (int) $j['reponses']) : null;
        return ['attente' => (int) $d['n'], 'plus_ancien_h' => $plusAncien, 'signalements' => $this->signalementsOuverts($maintenant), 'disponibles' => $dispos, 'joignables_pct' => $pct];
    }

    /**
     * Mises en relation par jour sur 30 jours (le dernier jour est celui d'aujourd'hui, encore en cours).
     *
     * @return array{jours: list<array{jour: string, nombre: int}>, total: int, aujourdhui: int, depuis: \DateTimeImmutable}
     */
    public function misesEnRelation(?\DateTimeImmutable $maintenant = null): array
    {
        $utc = new \DateTimeZone('UTC');
        $maintenant ??= new \DateTimeImmutable('now', $utc);
        $debut = $maintenant->setTime(0, 0)->modify('-29 days');
        $lignes = $this->db->tous('SELECT DATE(cree_le) AS jour, COUNT(*) AS n FROM contacts WHERE cree_le >= :d GROUP BY DATE(cree_le)', ['d' => $debut->format('Y-m-d H:i:s')]);
        $parJour = array_column($lignes, 'n', 'jour');
        $jours = [];
        for ($i = 0; $i < 30; $i++) {
            $j = $debut->modify("+$i days")->format('Y-m-d');
            $jours[] = ['jour' => $j, 'nombre' => (int) ($parJour[$j] ?? 0)];
        }
        return ['jours' => $jours, 'total' => array_sum(array_column($jours, 'nombre')), 'aujourdhui' => $jours[29]['nombre'], 'depuis' => $debut];
    }

    /** La photo « Photo de vous » du dossier (la plus récente), pour la vignette de la file : pas une pièce d'identité. @return ?array{fichier: string, mime: string} */
    public function portraitDossier(string $publicId): ?array
    {
        $p = $this->db->un(
            'SELECT p.fichier, p.mime FROM pieces_dossier p JOIN mecaniciens m ON m.id = p.mecanicien_id
              WHERE m.public_id = :p AND p.type_piece = \'portrait\' AND p.statut <> \'remplacee\' AND p.fichier IS NOT NULL AND p.fichier_supprime_le IS NULL ORDER BY p.id DESC LIMIT 1',
            ['p' => $publicId]
        );
        return $p === null ? null : ['fichier' => (string) $p['fichier'], 'mime' => (string) $p['mime']];
    }
}
