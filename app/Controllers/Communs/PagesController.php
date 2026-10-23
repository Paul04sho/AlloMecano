<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\MessageContactService;
use App\Services\Parametres;
use App\Services\TextesLegaux;

/** Pages d'information : X3 À propos, X4 Contact, X5 CGU et Confidentialité (avec leur PDF), X7 Hors ligne. */
final class PagesController
{
    /** GET /a-propos */
    public function aPropos(Request $requete): Response
    {
        return $this->page('communs/a-propos', ['titre' => 'À propos', 'description' => 'allomécano montre le mécano vérifié, disponible et le plus proche. Vous l’appelez directement.',
            'css' => ['css/pages/accueil.css', 'css/pages/info.css'], 'trombinoscope' => AccueilService::trombinoscope()]);
    }

    /** GET /contact */
    public function contact(Request $requete): Response
    {
        return $this->formulaireContact(['profil' => 'client', 'prenom' => (string) Session::get('contact_prenom', ''), 'contact' => '', 'message' => ''], []);
    }

    /** POST /contact (RG-CT1 : 3 messages par heure et par adresse IP) */
    public function envoyerContact(Request $requete): Response
    {
        $service = MessageContactService::depuisBase();
        $saisie = ['profil' => (string) $requete->input('profil', ''), 'prenom' => (string) $requete->input('prenom', ''), 'contact' => (string) $requete->input('contact', ''), 'message' => (string) $requete->input('message', '')];
        $v = $service->valider($saisie);
        if ($v['erreurs'] !== []) {
            return $this->formulaireContact($saisie, $v['erreurs']);
        }
        if (!$service->enregistrer($v['propre'], $requete->ip())['ok']) {
            return $this->formulaireContact($saisie, [], 'Vous avez envoyé trois messages dans l’heure. Réessayez un peu plus tard, ou appelez-nous.'); // texte à valider : non dessiné dans Figma
        }
        Session::flash('message', 'Message envoyé. On vous répond sous 24 h ouvrées.'); // texte à valider : non dessiné dans Figma
        return Response::redirection('/contact');
    }

    /** GET /cgu et /confidentialite */
    public function legal(Request $requete, string $quoi): Response
    {
        $doc = $quoi === 'cgu' ? TextesLegaux::cgu() : TextesLegaux::confidentialite();
        return $this->page('communs/legal', ['titre' => $quoi === 'cgu' ? 'Conditions générales d’utilisation' : 'Confidentialité', 'css' => ['css/pages/info.css'],
            'quoi' => $quoi, 'doc' => $doc, 'poidsPdf' => strlen(TextesLegaux::pdf($quoi))]);
    }

    public function cgu(Request $requete): Response
    {
        return $this->legal($requete, 'cgu');
    }

    public function confidentialite(Request $requete): Response
    {
        return $this->legal($requete, 'confidentialite');
    }

    /** GET /cgu.pdf et /confidentialite.pdf */
    public function pdf(string $quoi): Response
    {
        return (new Response(TextesLegaux::pdf($quoi), 200))
            ->avecEntete('Content-Type', 'application/pdf')
            ->avecEntete('Content-Disposition', 'attachment; filename="allomecano-' . ($quoi === 'cgu' ? 'cgu' : 'confidentialite') . '.pdf"');
    }

    public function cguPdf(Request $requete): Response
    {
        return $this->pdf('cgu');
    }

    public function confidentialitePdf(Request $requete): Response
    {
        return $this->pdf('confidentialite');
    }

    /** GET /hors-ligne : la page que le service worker sert quand le réseau manque */
    public function horsLigne(Request $requete): Response
    {
        return $this->page('communs/hors-ligne', ['titre' => 'Hors ligne', 'sansPied' => true, 'sansEspacePro' => true, 'css' => ['css/pages/info.css'], 'js' => ['js/pages/hors-ligne.js']]);
    }

    /** GET /api/ping : la page hors ligne s'en sert pour savoir si le réseau est revenu */
    public function ping(Request $requete): Response
    {
        return Response::json(['ok' => true])->avecEntete('Cache-Control', 'no-store');
    }

    /** @param array<string, mixed> $saisie @param array<string, string> $erreurs */
    private function formulaireContact(array $saisie, array $erreurs, ?string $limite = null): Response
    {
        return $this->page('communs/contact', ['titre' => 'Contact', 'css' => ['css/pages/info.css'], 'saisie' => $saisie, 'erreurs' => $erreurs,
            'canaux' => ['whatsapp' => Parametres::texte('contact.whatsapp', '+2250707000000'), 'telephone' => Parametres::texte('contact.telephone', '+2250707000000'), 'email' => Parametres::texte('contact.email', 'bonjour@allomecano.ci')],
            'limite' => $limite]);
    }

    /** @param array<string, mixed> $donnees */
    private function page(string $vue, array $donnees): Response
    {
        return Response::html(View::rendre($vue, $donnees, 'layouts/client'));
    }
}
