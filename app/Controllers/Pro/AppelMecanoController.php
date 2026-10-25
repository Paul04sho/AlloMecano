<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Env;
use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AppelService;
use App\Services\MecanoService;
use App\Services\Parametres;
use App\Services\PushService;
use App\Services\RelaisService;
use App\Services\TableauMecanoService;

/**
 * Les appels intégrés côté mécano : M1f « Activer les appels » (installation, notifications, essai de sonnerie) et M5 (appel entrant, en appel, « Vous y allez ? »).
 * La signalisation WebRTC passe par AppelService, comme côté client (RG-A5 à RG-A8) ; ici le mécano n'a accès qu'à ses propres appels (RG-C1).
 */
final class AppelMecanoController
{
    private const CSS_ACTIVER = ['css/pages/pro.css', 'css/pages/pro-appel.css'];
    private const CSS_APPEL = ['css/composants/carte.css', 'vendor/leaflet-1.9.4/leaflet.css', 'css/pages/pro.css', 'css/pages/appel.css', 'css/pages/pro-appel.css'];

    // ---------- M1f · Activer les appels (Figma 272:1803) ----------

    /** GET /pro/appels/activer */
    public function activer(Request $requete): Response
    {
        $id = $this->mecano();
        if (MecanoService::depuisBase()->compte($id)['statut_dossier'] !== 'valide') {
            return Response::redirection('/pro'); // RG-D1 : pas d'appels avant la validation du dossier
        }
        $premiere = MecanoService::depuisBase()->doitActiverLesAppels($id);
        return Response::html(View::rendre('pro/activer-appels', [
            'titre' => 'Activer les appels', 'sansBarre' => true, 'sansOnglets' => true, 'css' => self::CSS_ACTIVER, 'js' => ['js/pages/pro-activer.js'],
            'cle' => PushService::clePublique(), 'premiere' => $premiere, 'retour' => $premiere ? '/pro?apres=1' : '/pro/profil',
        ], 'layouts/pro'));
    }

    /** POST /pro/api/push : ce téléphone devient joignable (RG-A2). */
    public function abonner(Request $requete): Response
    {
        $ok = PushService::depuisBase()->abonner($this->mecano(), $requete->json(), mb_substr((string) $requete->entete('User-Agent'), 0, 120));
        return Response::json(['ok' => $ok], $ok ? 200 : 422);
    }

    /** POST /pro/api/push/etat {endpoint} : ce navigateur est-il déjà abonné ? */
    public function abonne(Request $requete): Response
    {
        $endpoint = $requete->json()['endpoint'] ?? null;
        return Response::json(['abonne' => is_string($endpoint) && PushService::depuisBase()->abonne($this->mecano(), $endpoint)]);
    }

    /** POST /pro/api/push/essai : « Faire sonner mon téléphone ». */
    public function essai(Request $requete): Response
    {
        $envoyes = PushService::depuisBase()->essai($this->mecano());
        return Response::json(['ok' => $envoyes > 0, 'envoyes' => $envoyes], $envoyes > 0 ? 200 : 409);
    }

    /** POST /pro/api/appels/pret : « C'est prêt ». Les trois réglages sont faits : l'espace s'ouvre. */
    public function pret(Request $requete): Response
    {
        $ok = MecanoService::depuisBase()->activerLesAppels($this->mecano());
        return Response::json(['ok' => $ok, 'suite' => '/pro'], $ok ? 200 : 409);
    }

    // ---------- M5 · Appel entrant, en appel, « Vous y allez ? » ----------

    /** GET /pro/appels/entrants : les appels qui sonnent chez moi. Chaque interrogation prouve que mon app est ouverte (RG-A2). */
    public function entrants(Request $requete): Response
    {
        $compte = MecanoService::depuisBase()->compte($this->mecano());
        if ($compte['statut_dossier'] !== 'valide') {
            return Response::json(['entrants' => []]);
        }
        return Response::json(['entrants' => AppelService::depuisBase()->entrants($compte['public_id'])]);
    }

    /** GET /pro/appel/{id} */
    public function page(Request $requete): Response
    {
        $mecano = $this->mecano();
        $appel = AppelService::depuisBase();
        $d = $appel->detailMecano((string) $requete->param('id'), $mecano);
        if ($d === null) {
            throw HttpException::introuvable();
        }
        if ($d['statut'] === 'decroche') { // la page se rouvre en pleine conversation : l'audio est perdu, on clôt l'appel
            $appel->signaler($d['contact'], 'mecanicien', MecanoService::depuisBase()->compte($mecano)['public_id'], 'raccroche', null);
            $d = $appel->detailMecano($d['contact'], $mecano) ?? $d;
        }
        $phase = in_array($d['statut'], ['initie', 'sonne'], true) ? 'entrant' : ($d['statut'] === 'termine' && $d['issue_mecano'] === null ? 'suite' : null);
        if ($phase === null) {
            return Response::redirection('/pro'); // manqué, annulé, déjà répondu : rien à faire ici
        }
        $km = $d['distance_m'] !== null ? self::distance($d['distance_m']) : null;
        $lieu = implode(', ', array_filter([$d['lieu'], $d['repere']]));
        return Response::html(View::rendre('pro/appel', [
            'titre' => $phase === 'entrant' ? 'Appel entrant' : 'Vous y allez ?', 'sansBarre' => true, 'sansOnglets' => true, 'classeCorps' => 'page-appel-pro',
            'css' => self::CSS_APPEL, 'js' => ['vendor/leaflet-1.9.4/leaflet.js', 'vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js', 'js/pages/pro-appel.js'],
            'd' => $d, 'phase' => $phase, 'km' => $km, 'lieu' => $lieu, 'sonnerie_s' => Parametres::int('appel.sonnerie_s', 30), 'echec_s' => Parametres::int('appel.echec_reseau_s', 15),
            'fin' => $d['termine_le'] !== null ? Format::heure($d['termine_le']) : null, 'duree' => $d['duree_s'] !== null ? self::duree($d['duree_s']) : null,
            'dev' => !Env::enProduction(), 'itineraire' => $d['position'] !== null ? 'https://www.google.com/maps/dir/?api=1&destination=' . $d['position']['lat'] . ',' . $d['position']['lon'] . '&travelmode=driving' : null,
        ], 'layouts/pro'));
    }

    /** GET /pro/appels/{id}/signaux?apres=N : l'état de l'appel et les messages du client. */
    public function signaux(Request $requete): Response
    {
        $mecano = $this->mecano();
        $d = AppelService::depuisBase()->detailMecano((string) $requete->param('id'), $mecano);
        if ($d === null) {
            return Response::json(['message' => 'Appel introuvable.'], 404);
        }
        $apres = max(0, (int) $requete->query('apres', 0));
        $signaux = AppelService::depuisBase()->signaux($d['contact'], 'mecanicien', MecanoService::depuisBase()->compte($mecano)['public_id'], $apres);
        return Response::json([
            'statut' => $d['statut'], 'fin' => !in_array($d['statut'], ['initie', 'sonne', 'decroche'], true), 'depuis_s' => $d['depuis_s'],
            'signaux' => $signaux, 'dernier' => $signaux !== [] ? end($signaux)['id'] : $apres,
        ]);
    }

    /** POST /pro/appels/{id}/signal {type, charge} : réponse, candidat, refus ou raccroché. */
    public function signal(Request $requete): Response
    {
        $charge = $requete->input('charge');
        $ok = AppelService::depuisBase()->signaler(
            (string) $requete->param('id'), 'mecanicien', MecanoService::depuisBase()->compte($this->mecano())['public_id'], (string) $requete->input('type', ''), $charge === null ? null : json_encode($charge)
        );
        return Response::json(['ok' => $ok], $ok ? 200 : 409);
    }

    /** POST /pro/appels/{id}/issue {reponse} : « J'y vais » ou « Pas pour moi » (RG-I2, RG-D4). */
    public function issue(Request $requete): Response
    {
        $ok = AppelService::depuisBase()->repondreMecano(
            (string) $requete->param('id'), MecanoService::depuisBase()->compte($this->mecano())['public_id'], (string) $requete->input('reponse', '')
        );
        return Response::json(['ok' => $ok, 'suite' => '/pro'], $ok ? 200 : 409);
    }

    /** GET /pro/appels/ice : serveurs STUN et relais TURN (RG-A9). */
    public function ice(Request $requete): Response
    {
        $this->mecano();
        return Response::json(['iceServers' => (new RelaisService())->serveurs()]);
    }

    // ---------- Aides ----------

    private function mecano(): int
    {
        $id = Session::get('mecano_id');
        if (!is_int($id)) {
            throw new HttpException(401, 'Connectez-vous.');
        }
        return $id;
    }

    /** « 1,2 » + « km » : le chiffre en grand et son unité, comme sur M5 (toujours en kilomètres, comme partout ailleurs). @return array{valeur: string, unite: string, texte: string} */
    public static function distance(int $metres): array
    {
        return ['valeur' => Format::km($metres), 'unite' => 'km', 'texte' => Format::distance($metres)];
    }

    /** 130 s → « 2 min 10 » ; 45 s → « 45 s ». */
    public static function duree(int $secondes): string
    {
        return $secondes < 60 ? $secondes . Format::INSECABLE . 's' : intdiv($secondes, 60) . Format::INSECABLE . 'min' . ($secondes % 60 > 0 ? ' ' . ($secondes % 60) : '');
    }
}
