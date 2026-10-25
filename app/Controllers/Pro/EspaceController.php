<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\DemandesMecanoService;
use App\Services\DisponibiliteService;
use App\Services\InscriptionService;
use App\Services\MecanoService;
use App\Services\Parametres;
use App\Services\TableauMecanoService;

/**
 * L'espace du mécano à l'ouverture : M2 tableau de bord (disponible ou non), M2a dossier en vérification, M2b pièce à reprendre ou compte suspendu.
 * Ce que l'on voit dépend du dossier (RG-DO8) : seul un dossier validé peut se rendre disponible (RG-D1), une suspension verrouille tout (RG-D6).
 */
final class EspaceController
{
    private const CSS = ['css/pages/pro.css', 'css/pages/pro-tableau.css'];
    private const JS = ['js/pages/pro-tableau.js'];

    /** GET /pro */
    public function page(Request $requete): Response
    {
        $id = (int) Session::get('mecano_id');
        $service = TableauMecanoService::depuisBase();
        $profil = $service->profil($id);
        if ($profil === null) {
            Session::oublier('mecano_id');
            return Response::redirection('/connexion');
        }
        $commun = ['css' => self::CSS, 'js' => self::JS, 'profil' => $profil, 'avatar' => $this->avatar($profil)];
        switch ($profil['statut']) {
            case 'brouillon':
                return Response::redirection($this->etapeSuivante($id)); // RG-DO1 : on reprend là où on s'était arrêté
            case 'en_attente':
                return $this->rendre('pro/dossier-attente', $commun + [
                    'titre' => 'Votre dossier', 'sansOnglets' => true,
                    'suivi' => $service->suivi($profil), 'etats' => InscriptionService::depuisBase()->etatPieces($id)['etats'],
                ]);
            case 'a_reprendre':
                $v = $service->verdicts($id);
                return $this->rendre('pro/dossier-reprendre', $commun + [
                    'titre' => 'Votre dossier à reprendre', 'sansOnglets' => true, 'sansBarre' => false,
                    'numero' => InscriptionService::numeroAffiche($id), 'verdicts' => $v,
                    'portrait' => $this->vignette($id, $v['a_reprendre']),
                ]);
            case 'suspendu':
                $s = $service->suspension($id);
                return $this->rendre('pro/compte-suspendu', $commun + [
                    'titre' => 'Compte suspendu', 'sansOnglets' => true, 'suspension' => $s,
                    'whatsapp' => $this->lienSupport($profil),
                ]);
            case 'refuse':
                return $this->rendre('pro/dossier-refuse', $commun + ['titre' => 'Dossier refusé', 'sansOnglets' => true, 'numero' => InscriptionService::numeroAffiche($id), 'whatsapp' => $this->lienSupport($profil)]);
        }
        // Validé : « Dernière étape » (M1f) à la première visite, sauf si on a choisi de la faire plus tard (flèche retour)
        if ($requete->query('apres') !== null) {
            Session::set('appels_apres', true);
        }
        if (Session::get('appels_apres') !== true && MecanoService::depuisBase()->doitActiverLesAppels($id)) {
            return Response::redirection('/pro/appels/activer');
        }
        // Tableau de bord (M2). Une session de plus de 12 h s'arrête avant l'affichage (RG-D3).
        DisponibiliteService::depuisBase()->expirer(null, $id);
        $profil = $service->profil($id) ?? $profil;
        $jour = $service->jour($id);
        return $this->rendre('pro/tableau', [
            'titre' => 'Statut', 'css' => self::CSS, 'js' => self::JS, 'onglet' => 'statut', 'demandes' => DemandesMecanoService::depuisBase()->aRappeler($id), 'sonnerie' => true,
            'profil' => $profil, 'avatar' => $this->avatar($profil), 'jour' => $jour, 'bornes' => $this->bornes($jour, $profil['etat'] !== 'indisponible'),
            'liste' => $service->demandes($id), 'avis' => $service->dernierAvis($id),
            'zone' => TableauMecanoService::zone($profil), 'position' => TableauMecanoService::lignePosition($profil),
        ]);
    }

    /**
     * POST /pro/api/disponibilite {etat, lat, lon, precision} : l'interrupteur (RG-D1, RG-D7). « disponible » exige une position.
     */
    public function disponibilite(Request $requete): Response
    {
        $id = (int) Session::get('mecano_id');
        $champs = $requete->json() ?: $requete->tout();
        $service = DisponibiliteService::depuisBase();
        $etat = (string) ($champs['etat'] ?? '');
        if ($etat === 'disponible') {
            $r = $service->rendreDisponible($id, $champs);
        } elseif ($etat === 'indisponible') {
            $r = $service->rendreIndisponible($id);
        } elseif ($etat === 'libre') { // RG-D4 : « Je suis libre »
            $r = $service->liberer($id);
        } else {
            throw new HttpException(422, 'Choisissez disponible ou indisponible.');
        }
        return $this->reponseEtat($id, $r);
    }

    /** POST /pro/api/position {lat, lon, precision} : « Mettre à jour ma position » (RG-D2). */
    public function position(Request $requete): Response
    {
        $id = (int) Session::get('mecano_id');
        return $this->reponseEtat($id, DisponibiliteService::depuisBase()->majPosition($id, $requete->json() ?: $requete->tout()));
    }

    /** GET /pro/apercu : « Voir ma fiche comme un client ». Un dossier validé voit sa vraie fiche ; avant, un aperçu de sa ligne. */
    public function apercu(Request $requete): Response
    {
        $id = (int) Session::get('mecano_id');
        $profil = TableauMecanoService::depuisBase()->profil($id);
        if ($profil === null) {
            throw HttpException::introuvable();
        }
        if ($profil['statut'] === 'valide' && $profil['slug'] !== null) {
            return Response::redirection('/mecano/' . rawurlencode($profil['slug']));
        }
        $b = InscriptionService::depuisBase()->brouillon($id);
        return $this->rendre('pro/apercu', [
            'titre' => 'Ma fiche comme un client', 'css' => self::CSS, 'js' => self::JS, 'sansOnglets' => true, 'profil' => $profil, 'avatar' => $this->avatar($profil), 'b' => $b,
            'specialites' => array_column(InscriptionService::depuisBase()->specialites(), 'libelle', 'code'),
        ]);
    }

    // ---------- Aides ----------

    /** @param array<string, mixed> $donnees */
    private function rendre(string $vue, array $donnees): Response
    {
        return Response::html(View::rendre($vue, $donnees, 'layouts/pro'));
    }

    /**
     * La réponse JSON de l'interrupteur : le nouvel état et les phrases à afficher, pour que le JavaScript mette l'écran à jour sans le recharger.
     *
     * @param array<string, mixed> $r
     */
    private function reponseEtat(int $id, array $r): Response
    {
        if (!$r['ok']) {
            return Response::json(['ok' => false, 'erreur' => $r['erreur']], 422);
        }
        $service = TableauMecanoService::depuisBase();
        $profil = $service->profil($id);
        $jour = $service->jour($id);
        $zone = TableauMecanoService::zone($profil);
        $bornes = $this->bornes($jour, $profil['etat'] !== 'indisponible');
        return Response::json([
            'ok' => true, 'etat' => $profil['etat'], 'depuis' => $r['depuis'], 'zone' => $zone, 'position' => TableauMecanoService::lignePosition($profil), 'bornes' => $bornes,
            'html' => View::rendre('composants/interrupteur-dispo', ['etat' => $profil['etat'] === 'en_intervention' ? 'intervention' : $profil['etat'], 'depuis' => $r['depuis'], 'zone' => $zone]),
            'bornes_html' => View::rendre('composants/bornes-jour', ['bornes' => $bornes]),
        ]);
    }

    /**
     * Les trois bornes du jour (M2) : appels, vues, note. Sans appel, « depuis ce matin » ; sans vue et masqué, « vous êtes masqué ».
     *
     * @param array{appels: int, vues: int, note: ?float, nb_avis: int} $jour
     * @return list<array{valeur: string, unite: string, libelle: string}>
     */
    private function bornes(array $jour, bool $visible): array
    {
        $a = $jour['appels'];
        $v = $jour['vues'];
        return [
            ['valeur' => (string) $a, 'unite' => $a > 1 ? 'Appels' : 'Appel', 'libelle' => $a === 0 ? 'depuis ce matin' : ($a > 1 ? 'vous ont appelé' : 'vous a appelé')],
            ['valeur' => (string) $v, 'unite' => $v > 1 ? 'Vues' : 'Vue', 'libelle' => $v === 0 && !$visible ? 'vous êtes masqué' : ($v > 1 ? 'ont vu votre fiche' : ($v === 1 ? 'a vu votre fiche' : 'sur votre fiche'))],
            ['valeur' => $jour['note'] !== null ? Format::nombre($jour['note'], 1) : '–', 'unite' => 'sur 5', 'libelle' => $jour['nb_avis'] > 1 ? $jour['nb_avis'] . ' avis' : ($jour['nb_avis'] === 1 ? '1 avis' : 'pas encore d’avis')],
        ];
    }

    /** @param array<string, mixed> $profil @return ?array{src: string, cadrage?: string} */
    private function avatar(array $profil): ?array
    {
        if ($profil['photo'] !== null) { // la photo publique, validée
            return ['src' => $profil['photo']['src'], 'cadrage' => $profil['photo']['cadrage']];
        }
        return $profil['portrait_piece'] ? ['src' => '/pro/mes-pieces/portrait'] : null; // avant validation : sa propre photo, à lui seul
    }

    /** La photo de vous, floutée par le CSS, quand elle est à reprendre (M2b). @param list<string> $aReprendre */
    private function vignette(int $id, array $aReprendre): ?string
    {
        return in_array('portrait', $aReprendre, true) ? '/pro/mes-pieces/portrait' : null;
    }

    /** Le lien WhatsApp du support, avec un message prêt (RG-D6, RG-DO5). @param array<string, mixed> $profil */
    private function lienSupport(array $profil): string
    {
        $numero = preg_replace('/\D+/', '', Parametres::texte('contact.whatsapp', '+2250707000000')) ?? '';
        $texte = 'Bonjour, je suis ' . $profil['prenom'] . ' ' . $profil['nom'] . ' (dossier n° ' . InscriptionService::numeroAffiche($profil['id']) . '). Je voudrais vous expliquer ce qui s’est passé.';
        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($texte);
    }

    /** Où reprendre un brouillon : le métier, les pièces, puis l'envoi. */
    private function etapeSuivante(int $id): string
    {
        $service = InscriptionService::depuisBase();
        $b = $service->brouillon($id);
        if ($b === null || $b['specialites'] === [] || $b['vehicules'] === null || $b['quartier'] === null) {
            return '/pro/inscription/metier';
        }
        return $service->etatPieces($id)['manquantes'] !== [] ? '/pro/inscription/pieces' : '/pro/inscription/envoi';
    }
}
