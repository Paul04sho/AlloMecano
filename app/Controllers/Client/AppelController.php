<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AppelService;
use App\Services\ContactService;
use App\Services\Parametres;
use App\Services\RelaisService;

/**
 * Appel intégré côté client (C5c · 269:1820 ça sonne, 269:1866 en ligne, 270:1846 ne répond pas, 270:1905 terminé).
 * La page est rendue d'après l'état de l'appel ; le JS fait le WebRTC et interroge `signaux` (1 s pendant la connexion, 3 s pendant l'appel, RG-A8).
 */
final class AppelController
{
    /** GET /appel/{id} */
    public function page(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $client = $this->client();
        $contacts = ContactService::depuisBase();
        $appel = AppelService::depuisBase();
        $contact = $contacts->lire($id, $client);
        if ($contact === null || $contact['canal'] !== 'app') {
            throw HttpException::introuvable();
        }
        $appel->interrompre($id, $client); // rouvrir la page d'un appel commencé : l'audio est perdu
        $contact = $contacts->lire($id, $client) ?? $contact;
        $statut = $contact['statut'];
        $retour = $contact['recherche'] !== null ? '/resultats/' . $contact['recherche'] : '/position';

        if ($statut === 'annule' && $contact['fin_par'] !== 'reseau') {
            return Response::redirection('/mecano/' . rawurlencode($contact['slug']) . ($contact['recherche'] !== null ? '?r=' . $contact['recherche'] : '')); // le client a raccroché avant la réponse
        }
        if ($statut === 'termine' && $contact['issue_mecano'] !== null) {
            return Response::redirection('/contact/' . rawurlencode($id)); // RG-I2 : sa réponse est déjà là, C6 la montre
        }

        $donnees = ['contact' => $contact, 'retour' => $retour, 'statut' => $statut];
        if (in_array($statut, ['initie', 'sonne', 'decroche'], true)) {
            $vue = 'sonne';
            $donnees['sonnerie_s'] = Parametres::int('appel.sonnerie_s', 30);
            $donnees['echec_s'] = Parametres::int('appel.echec_reseau_s', 15);
            $donnees['presentation'] = $this->presentation($contact);
        } elseif ($statut === 'termine' || $statut === 'annule') {
            $vue = 'termine';
            $donnees['duree'] = $contact['duree_s'] !== null ? self::duree($contact['duree_s']) : null;
            $donnees['heure_fin'] = Format::heure((string) $contact['termine_le']);
            $donnees['interrompu'] = $statut === 'annule' || $contact['fin_par'] === 'reseau';
        } else { // manque · occupe · echoue : RG-I1 le suivant tout de suite, RG-A7 le numéro du même mécano
            $vue = 'sans-reponse';
            $donnees['suivant'] = $contacts->suivant($id, $client);
            $donnees['numero'] = $contacts->numero($id, $client);
            $donnees['sonnerie_s'] = Parametres::int('appel.sonnerie_s', 30);
        }
        return Response::html(View::rendre('client/appel-' . $vue, $donnees + [
            'titre' => 'Appel à ' . $contact['nom_complet'], 'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-appel page-appel--' . $vue,
            'css' => ['css/pages/fiche.css', 'css/pages/contact.css', 'css/pages/appel.css'],
            'js' => $vue === 'sonne' ? ['js/pages/appel.js'] : ['js/pages/appel-fin.js'],
        ], 'layouts/client'));
    }

    /** GET /api/appels/{id}/signaux?apres=N : l'état de l'appel et les messages de l'autre côté. */
    public function signaux(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $client = $this->client();
        $appel = AppelService::depuisBase();
        $etat = $appel->etat($id, $client);
        if ($etat === null) {
            return Response::json(['message' => 'Appel introuvable.'], 404);
        }
        $signaux = $appel->signaux($id, 'client', $client, (int) $requete->query('apres', 0));
        return Response::json($etat + ['signaux' => $signaux, 'dernier' => $signaux !== [] ? end($signaux)['id'] : (int) $requete->query('apres', 0)]);
    }

    /** POST /api/appels/{id}/signal {type, charge} */
    public function signal(Request $requete): Response
    {
        $charge = $requete->input('charge');
        $ok = AppelService::depuisBase()->signaler(
            (string) $requete->param('id'), 'client', $this->client(), (string) $requete->input('type', ''),
            $charge === null ? null : json_encode($charge)
        );
        return Response::json(['ok' => $ok], $ok ? 200 : 409);
    }

    /** POST /api/appels/{id}/raccrocher */
    public function raccrocher(Request $requete): Response
    {
        AppelService::depuisBase()->signaler((string) $requete->param('id'), 'client', $this->client(), 'raccroche', null);
        return Response::json(['ok' => true]);
    }

    /** POST /api/appels/{id}/echec {raison} : micro refusé ou audio impossible (RG-A7). */
    public function echec(Request $requete): Response
    {
        $ok = AppelService::depuisBase()->echec((string) $requete->param('id'), $this->client(), (string) $requete->input('raison', ''));
        return Response::json(['ok' => $ok]);
    }

    /** GET /api/appels/ice : serveurs STUN et relais TURN. */
    public function ice(Request $requete): Response
    {
        $this->client();
        return Response::json(['iceServers' => (new RelaisService())->serveurs()]);
    }

    /**
     * « Voiture · Batterie · Riviera 2, près de la pharmacie des Jardins » : ce que le mécano voit (269:1844).
     *
     * @param array<string, mixed> $contact
     */
    private function presentation(array $contact): string
    {
        $lieu = $contact['lieu'] !== null ? ($contact['repere'] !== null ? $contact['lieu'] . ', ' . $contact['repere'] : $contact['lieu']) : null;
        return implode(' · ', array_filter([$contact['resume'], $lieu]));
    }

    /** 130 s → « 2 min 10 » ; 45 s → « 45 s ». */
    public static function duree(int $secondes): string
    {
        return $secondes < 60 ? $secondes . Format::INSECABLE . 's' : intdiv($secondes, 60) . Format::INSECABLE . 'min' . ($secondes % 60 > 0 ? ' ' . ($secondes % 60) : '');
    }

    private function client(): int
    {
        $client = Session::get('client_id');
        if (!is_int($client)) {
            throw HttpException::introuvable();
        }
        return $client;
    }
}
