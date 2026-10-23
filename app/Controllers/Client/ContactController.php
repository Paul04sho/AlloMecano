<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ContactException;
use App\Services\ContactService;
use App\Services\FicheService;

/**
 * Contact direct et WhatsApp (RG-A1, RG-A10 à RG-A12) et retour d'appel « Avez-vous pu le joindre ? » (C6 · 82:1322, 84:1394, 86:1488).
 * Le contact est créé AVANT que le numéro soit donné ; le client reçoit ensuite « tel: » ou le lien WhatsApp.
 */
final class ContactController
{
    /** POST /contact : sans JS, le formulaire crée le contact puis part vers « tel: » ou WhatsApp. */
    public function creerFormulaire(Request $requete): Response
    {
        try {
            $r = $this->creer($requete);
        } catch (ContactException $e) {
            Session::flash('message', $e->getMessage());
            return Response::redirection($this->retour($requete));
        }
        return Response::redirection($r['cible']);
    }

    /** POST /api/contacts : même chose pour le JS ; `reveler` rend le numéro, le QR code et WhatsApp Web (D1 · 159:858). */
    public function creerApi(Request $requete): Response
    {
        try {
            $r = $this->creer($requete);
        } catch (ContactException $e) {
            return Response::json(['message' => $e->getMessage(), 'code' => $e->code_refus], $e->code_refus === 'limite' ? 429 : 409);
        }
        $reponse = ['id' => $r['public_id'], 'canal' => $r['canal'], 'cible' => $r['cible'], 'memo' => $r['memo']];
        if ($requete->input('reveler') === '1') {
            $revele = ContactService::depuisBase()->reveler($r['public_id'], $this->client(), $this->contexte($requete));
            $reponse['html'] = $revele !== null ? View::rendre('composants/contact-revele', $revele) : '';
        }
        return Response::json($reponse);
    }

    /** GET /contact/{id} : « Avez-vous pu le joindre ? » en pleine page (lien du bandeau, sans JS). */
    public function page(Request $requete): Response
    {
        [$contact, $etape] = $this->etape((string) $requete->param('id'));
        return Response::html(View::rendre('client/contact', $etape + [
            'titre' => 'Avez-vous pu joindre ' . $contact['prenom'] . ' ?', 'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-retour',
            'css' => ['css/pages/fiche.css', 'css/pages/contact.css'], 'js' => ['js/pages/contact.js'],
        ], 'layouts/client'));
    }

    /** GET /api/contacts/{id}/retour : la feuille par-dessus la page où le client revient. */
    public function retourApi(Request $requete): Response
    {
        [$contact, $etape] = $this->etape((string) $requete->param('id'));
        return Response::html(View::rendre('composants/retour-appel', ['mode' => 'feuille', 'contact' => $contact['public_id'], 'contenu' => View::rendre('client/contact-etape', $etape)]));
    }

    /** POST /api/contacts/{id}/absence : temps passé hors d'allomécano (RG-A12, un indice). */
    public function absence(Request $requete): Response
    {
        $ok = ContactService::depuisBase()->enregistrerAbsence((string) $requete->param('id'), $this->client(), (int) $requete->input('secondes', 0));
        return Response::json(['ok' => $ok], $ok ? 200 : 404);
    }

    /** POST /api/contacts/{id}/issue (JS) et POST /contact/{id}/issue (sans JS) : la réponse du client, puis l'étape suivante. */
    public function issue(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        try {
            ContactService::depuisBase()->repondre($id, $this->client(), (string) $requete->input('issue', ''));
        } catch (ContactException $e) {
            return $requete->veutJson() ? Response::json(['message' => $e->getMessage()], 422) : Response::redirection('/contact/' . rawurlencode($id));
        }
        if (!$requete->veutJson()) {
            return Response::redirection('/contact/' . rawurlencode($id));
        }
        [, $etape] = $this->etape($id);
        return Response::json(['html' => View::rendre('client/contact-etape', $etape), 'etape' => $etape['etape']]);
    }

    // ---------- interne ----------

    /** @return array{public_id: string, canal: string, cible: string, prenom: string, mecano: string} */
    private function creer(Request $requete): array
    {
        $mecano = (string) $requete->input('mecano', '');
        if (preg_match('/^[0-9A-Z]{26}$/', $mecano) !== 1) {
            throw new ContactException('mecano', 'Ce mécano est introuvable.');
        }
        $precedent = (string) $requete->input('precedent', '');
        return ContactService::depuisBase()->creer(
            $this->client(), $mecano, (string) $requete->input('canal', ''), (string) $requete->input('position', '1') !== '0',
            $this->contexte($requete), preg_match('/^[0-9A-Z]{26}$/', $precedent) === 1 ? $precedent : null, (string) $requete->input('webrtc', '0') === '1'
        );
    }

    /** @return array{recherche: ?string, lieu: ?string, repere: ?string, vehicule: ?string, panne: ?string, lat: ?float, lon: ?float} */
    private function contexte(Request $requete): array
    {
        $r = (string) $requete->input('recherche', '');
        $c = FicheController::contexte(preg_match('/^[0-9A-Z]{26}$/', $r) === 1 ? $r : null);
        unset($c['liste']);
        return $c;
    }

    private function client(): int
    {
        $client = Session::get('client_id');
        if (!is_int($client)) {
            throw HttpException::introuvable();
        }
        return $client;
    }

    /** Retour après un refus sans JS : la page d'où le client vient, sinon l'accueil. */
    private function retour(Request $requete): string
    {
        $mecano = (string) $requete->input('mecano', '');
        $fiche = preg_match('/^[0-9A-Z]{26}$/', $mecano) === 1
            ? \App\Core\Database::instance()->valeur('SELECT slug FROM mecaniciens WHERE public_id = :p', ['p' => $mecano]) : null;
        $r = (string) $requete->input('recherche', '');
        return $fiche ? '/mecano/' . rawurlencode((string) $fiche) . (preg_match('/^[0-9A-Z]{26}$/', $r) === 1 ? '?r=' . $r : '') : '/';
    }

    /**
     * L'étape à montrer selon la réponse déjà donnée : la question, le mécano suivant (RG-I5) ou « il arrive ».
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function etape(string $publicId): array
    {
        $service = ContactService::depuisBase();
        $client = $this->client();
        $contact = $service->lire($publicId, $client);
        if ($contact === null) {
            throw HttpException::introuvable();
        }
        // RG-I2 : sans réponse du client, celle du mécano (« J'y vais » / « Pas pour moi ») décide de l'étape
        $issue = $contact['issue'] ?? match ($contact['issue_mecano']) {
            'j_y_vais' => 'arrive',
            'pas_pour_moi' => 'pas_pour_moi',
            default => null,
        };
        $etape = match ($issue) {
            null => 'question',
            'arrive' => 'arrive',
            default => 'suivant',
        };
        $donnees = ['etape' => $etape, 'contact' => $contact, 'heure' => Format::heure(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))];
        if ($etape === 'suivant') {
            $donnees['suivant'] = $service->suivant($publicId, $client);
            $donnees['pasEntendus'] = $issue === 'pas_entendus';
            $donnees['pasPourMoi'] = $issue === 'pas_pour_moi';
        }
        if ($etape === 'arrive') {
            $ctx = FicheController::contexte($contact['recherche']);
            $lat = $contact['position_partagee'] ? $ctx['lat'] : null;
            $lon = $contact['position_partagee'] ? $ctx['lon'] : null;
            $donnees['message'] = FicheService::message($contact['prenom'], $ctx, $lat, $lon);
        }
        return [$contact, $donnees];
    }
}
