<?php
/**
 * Démo de l'administration : des dossiers à examiner (avec leurs pièces de démonstration, jamais de vraies pièces d'identité)
 * et un mois de mises en relation, pour que le tableau de bord et la file des dossiers aient du contenu.
 * Chargé par scripts/installer-base.php --demo ; $pdo et l'autoload sont déjà là. Aléatoire fixe : la démo est la même à chaque fois.
 */
declare(strict_types=1);

use App\Core\Ulid;

/** @var PDO $pdo */
$racine = dirname(__DIR__, 2);
mt_srand(2026);

// ---------- Pièces de démonstration : cartes grises « SPÉCIMEN » et visages des maquettes ----------
$dossierPieces = $racine . '/storage/pieces/demo';
if (!is_dir($dossierPieces)) {
    mkdir($dossierPieces, 0775, true);
}
$specimen = static function (string $fichier, string $ligne1, string $ligne2) use ($dossierPieces): void {
    $img = imagecreatetruecolor(720, 454);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 230, 232, 227));
    $encre = (int) imagecolorallocate($img, 86, 91, 100);
    imagerectangle($img, 0, 0, 719, 453, $encre);
    for ($i = 0; $i < 7; $i++) {
        imagefilledrectangle($img, 250, 120 + $i * 36, 250 + 280 - ($i % 3) * 60, 134 + $i * 36, $encre);
    }
    imagefilledrectangle($img, 36, 90, 206, 300, (int) imagecolorallocate($img, 180, 184, 178));
    imagestring($img, 5, 36, 30, $ligne1, $encre);
    imagestring($img, 4, 36, 380, $ligne2, $encre);
    imagestring($img, 5, 250, 400, 'SPECIMEN - PIECE DE DEMONSTRATION', (int) imagecolorallocate($img, 21, 23, 28));
    imagejpeg($img, $dossierPieces . '/' . $fichier, 82);
};
foreach (['cni_recto' => ['CARTE NATIONALE D\'IDENTITE', 'RECTO'], 'cni_verso' => ['CARTE NATIONALE D\'IDENTITE', 'VERSO'], 'permis' => ['PERMIS DE CONDUIRE', ''], 'diplome' => ['ATTESTATION DE METIER', ''], 'atelier' => ['ATELIER', 'PHOTO']] as $type => [$a, $b]) {
    $specimen("specimen-$type.jpg", $a, $b);
}
foreach ([2, 3, 4, 5] as $n) {
    copy($racine . "/public/assets/img/accueil/img-0$n-604.webp", "$dossierPieces/visage-$n.webp");
}

// ---------- Dossiers : [prénom, nom, commune, spécialités, heures d'attente, statut, atelier ?, pièce à reprendre] ----------
$dossiers = [
    ['Moussa', 'Diabaté', 'marcory', ['carrosserie', 'mecanique_generale'], 31, 'en_attente', false],
    ['Ibrahim', 'Ouattara', 'yopougon', ['electricite_auto', 'batterie'], 27, 'en_attente', true],
    ['Fatou', 'Bamba', 'treichville', ['pneus', 'batterie'], 22, 'en_attente', false],
    ['Jean-Marc', 'Aka', 'cocody', ['moteur', 'remorquage'], 15, 'en_attente', false],
    ['Yao', 'N’Guessan', 'abobo', ['mecanique_generale', 'moto'], 9, 'en_attente', true],
    ['Brice', 'Gnagne', 'port-bouet', ['freins', 'boite_vitesses'], 6, 'en_attente', false],
    ['Salif', 'Coulibaly', 'adjame', ['refroidissement'], 4, 'en_attente', true],
    ['Didier', 'Kouamé', 'koumassi', ['carrosserie'], 2, 'en_attente', false],
    ['Aya', 'Konan', 'yopougon', ['moto'], 1, 'en_attente', false],
    ['Mamadou', 'Sylla', 'abobo', ['batterie', 'electricite_auto'], 1, 'en_attente', true],
    ['Clarisse', 'Tano', 'cocody', ['mecanique_generale'], 3, 'en_attente', false],
    ['Éric', 'Gbané', 'plateau', ['pneus'], 5, 'en_attente', false],
    ['Hervé', 'Konan', 'cocody', ['demarreur_alternateur'], 52, 'a_reprendre', false, 'permis'],
    ['Ange', 'Loukou', 'marcory', ['freins'], 40, 'a_reprendre', false, 'cni_verso'],
    ['Salimata', 'Diallo', 'abobo', ['moto', 'pneus'], 30, 'a_reprendre', true, 'diplome'],
    ['Gérard', 'Zadi', 'yopougon', ['mecanique_generale'], 200, 'refuse', false],
];
$motifs = ['permis' => 'La photo est floue : le numéro du permis n’est pas lisible.', 'cni_verso' => 'Le verso est coupé : on ne voit pas la date de validité.', 'diplome' => 'Le document n’est pas un diplôme ni une attestation de métier.'];

$quartier = $pdo->prepare('SELECT q.id FROM quartiers q JOIN communes c ON c.id = q.commune_id WHERE c.slug = ? ORDER BY q.id LIMIT 1');
$specialite = $pdo->prepare('SELECT id FROM specialites WHERE code = ?');
$mdp = (string) $pdo->query('SELECT @mdp')->fetchColumn();
$insMeca = $pdo->prepare(
    'INSERT INTO mecaniciens (public_id,prenom,nom,date_naissance,telephone,email,metier,annees_experience,atelier_quartier_id,rayon_km,statut_dossier,dossier_envoye_le,motif_refus,mot_de_passe_hash,cgu_version,cgu_acceptees_le,etat)
     VALUES (?,?,?,?,?,?,?,?,?,10,?,?,?,?,\'1.0\',UTC_TIMESTAMP(),\'indisponible\')'
);
$insSpec = $pdo->prepare('INSERT INTO mecanicien_specialites (mecanicien_id,specialite_id) VALUES (?,?)');
$insPiece = $pdo->prepare(
    'INSERT INTO pieces_dossier (mecanicien_id,type_piece,fichier,mime,taille_octets,empreinte_sha256,largeur,hauteur,statut,motif,cree_le) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$libelles = ['carrosserie' => 'Tôlerie', 'mecanique_generale' => 'Mécanique générale', 'electricite_auto' => 'Électricité auto', 'pneus' => 'Pneus', 'moteur' => 'Diesel', 'freins' => 'Freins',
    'refroidissement' => 'Climatisation auto', 'moto' => 'Moto', 'batterie' => 'Batteries', 'remorquage' => 'Remorquage', 'boite_vitesses' => 'Embrayage', 'demarreur_alternateur' => 'Démarreur'];
foreach ($dossiers as $i => $d) {
    [$prenom, $famille, $commune, $specs, $heures, $statut, $atelier] = $d;
    $quartier->execute([$commune]);
    $q = $quartier->fetchColumn() ?: null;
    $envoye = gmdate('Y-m-d H:i:s', time() - (int) ($heures * 3600));
    $insMeca->execute([Ulid::generer(), $prenom, $famille, sprintf('%d-%02d-%02d', 1980 + $i * 2 % 20, 1 + $i % 12, 5 + $i), sprintf('+22505%08d', 10000 + $i), null, $libelles[$specs[0]], 3 + $i % 9, $q, $statut, $envoye,
        $statut === 'refuse' ? 'Les pièces ne correspondent pas à la personne sur la photo.' : null, $mdp]);
    $id = (int) $pdo->lastInsertId();
    foreach ($specs as $code) {
        $specialite->execute([$code]);
        $insSpec->execute([$id, (int) $specialite->fetchColumn()]);
    }
    $types = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait'] + ($atelier ? [5 => 'atelier'] : []);
    foreach ($types as $type) {
        $visage = [2, 3, 4, 5][$i % 4];
        $fichier = $type === 'portrait' ? "demo/visage-$visage.webp" : "demo/specimen-$type.jpg";
        $chemin = "$dossierPieces/" . basename($fichier);
        $reprendre = isset($d[7]) && $d[7] === $type;
        $insPiece->execute([$id, $type, $fichier, $type === 'portrait' ? 'image/webp' : 'image/jpeg', filesize($chemin), hash_file('sha256', $chemin), $type === 'portrait' ? 604 : 720, $type === 'portrait' ? 756 : 454,
            $reprendre ? 'a_reprendre' : ($statut === 'a_reprendre' ? 'validee' : 'envoyee'), $reprendre ? $motifs[$type] : null, $envoye]);
    }
}
echo 'Dossiers de démo : ' . count($dossiers) . "\n";

// ---------- Un mois de mises en relation (histogramme et joignabilité du tableau de bord) ----------
$meca = $pdo->query("SELECT id FROM mecaniciens WHERE statut_dossier = 'valide'")->fetchAll(PDO::FETCH_COLUMN);
$client = (int) $pdo->query("SELECT id FROM clients WHERE jeton_hash = SHA2('client-de-demonstration',256)")->fetchColumn();
$lignes = [];
for ($jour = 29; $jour >= 0; $jour--) {
    $n = $jour === 0 ? 38 : (int) (38 + 14 * sin($jour * 0.9) + mt_rand(0, 14) - ($jour % 7 === 0 ? 14 : 0));
    for ($k = 0; $k < $n; $k++) {
        $quand = gmdate('Y-m-d H:i:s', time() - $jour * 86400 - mt_rand(0, $jour === 0 ? 3600 * 6 : 86399));
        $r = mt_rand(1, 100);
        $issue = $r <= 55 ? 'arrive' : ($r <= 71 ? 'pas_entendus' : ($r <= 98 ? 'pas_reponse' : null));
        $lignes[] = [Ulid::generer(), $client, $meca[array_rand($meca)], ['tel', 'whatsapp', 'tel'][mt_rand(0, 2)], 'termine', $quand, 'Voiture · Batterie', $issue, $issue !== null ? $quand : null];
    }
}
$ins = $pdo->prepare('INSERT INTO contacts (public_id,client_id,mecanicien_id,canal,statut,cree_le,resume,issue_client,issue_client_le) VALUES (?,?,?,?,?,?,?,?,?)');
$pdo->beginTransaction();
foreach ($lignes as $l) {
    $ins->execute($l);
}
$pdo->commit();
echo 'Mises en relation de démo : ' . count($lignes) . "\n";

// ---------- Dernière activité des mécanos validés et quelques clients qui ont laissé leur téléphone (A3) ----------
$validés = $pdo->query("SELECT id FROM mecaniciens WHERE statut_dossier = 'valide' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$delais = [12 * 60, 60 * 60, 3 * 3600, 20 * 3600, 5 * 3600, 31 * 3600, 3 * 86400, 9 * 86400];
$majVu = $pdo->prepare('UPDATE mecaniciens SET vu_le = ? WHERE id = ?');
foreach ($validés as $i => $idMeca) {
    $majVu->execute([gmdate('Y-m-d H:i:s', time() - $delais[$i % count($delais)]), $idMeca]);
}
$prenoms = ['Aminata', 'Kouadio', 'Mariam', 'Yves', 'Fanta', 'Serge', 'Clarisse', 'Boris', 'Nadège', 'Ismaël', 'Rose', 'Eugène'];
$insClient = $pdo->prepare('INSERT INTO clients (jeton_hash, prenom, telephone, vu_le) VALUES (?, ?, ?, ?)');
foreach ($prenoms as $i => $p) {
    $insClient->execute([hash('sha256', 'client-demo-admin-' . $i), $p, sprintf('+22507%08d', 30000000 + $i * 913457), gmdate('Y-m-d H:i:s', time() - ($i + 1) * 5400)]);
}
echo "Clients de démo avec téléphone : " . count($prenoms) . "\n";
// Des clients sans prénom ni téléphone (« Un client »), puis les mises en relation réparties entre tous, avec leur panne, leur quartier et leur distance (M3 · Demandes)
for ($i = 0; $i < 4; $i++) {
    $insClient->execute([hash('sha256', 'client-demo-anonyme-' . $i), null, null, gmdate('Y-m-d H:i:s', time() - ($i + 2) * 7200)]);
}
$tousClients = array_map('intval', $pdo->query("SELECT id FROM clients WHERE jeton_hash <> SHA2('client-de-demonstration',256) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
$pannes = ['Voiture · Batterie', 'Moto · Pneu crevé', 'Voiture · Ne démarre pas', 'Voiture · Surchauffe', 'Voiture · Freins', 'Moto · Batterie'];
$lieux = ['Riviera 2', 'Riviera 4', 'Riviera 3', 'Angré', 'Bingerville', 'Cocody'];
$majContact = $pdo->prepare('UPDATE contacts SET client_id = ?, resume = ?, quartier_libelle = ?, distance_m = ? WHERE id = ?');
$pdo->beginTransaction();
foreach ($pdo->query("SELECT id FROM contacts WHERE client_id = (SELECT id FROM clients WHERE jeton_hash = SHA2('client-de-demonstration',256))")->fetchAll(PDO::FETCH_COLUMN) as $idContact) {
    $majContact->execute([$tousClients[$idContact % count($tousClients)], $pannes[$idContact % count($pannes)], $lieux[($idContact * 7) % count($lieux)], 300 + ($idContact * 137) % 5800, $idContact]);
}
$pdo->commit();
// Un historique de dossier pour les mécanos validés (A3 · tiroir) : envoyé, puis validé par la première admin
$pdo->exec("UPDATE mecaniciens SET dossier_envoye_le = TIMESTAMP(COALESCE(identite_verifiee_le, DATE(cree_le)) - INTERVAL 1 DAY, '08:47:00') WHERE statut_dossier = 'valide' AND dossier_envoye_le IS NULL");
$pdo->exec("INSERT INTO historique_dossier (mecanicien_id, de_statut, vers_statut, admin_id, motif, cree_le)
    SELECT id, 'en_attente', 'valide', (SELECT MIN(id) FROM admins), NULL, TIMESTAMP(COALESCE(identite_verifiee_le, DATE(cree_le)), '11:02:00') FROM mecaniciens WHERE statut_dossier = 'valide'");

// ---------- A4 · Signalements : trois ouverts (critique, important, normal) et cinq classés ce mois-ci ----------
$admin1 = (int) $pdo->query('SELECT MIN(id) FROM admins')->fetchColumn();
$idClient = static fn (int $i): int => (int) $pdo->query("SELECT id FROM clients WHERE jeton_hash = '" . hash('sha256', 'client-demo-admin-' . $i) . "'")->fetchColumn();
$idMeca = static fn (string $prenom): int => (int) $pdo->query('SELECT id FROM mecaniciens WHERE prenom = ' . $pdo->quote($prenom) . " AND statut_dossier = 'valide' ORDER BY id LIMIT 1")->fetchColumn();
$insContact = $pdo->prepare('INSERT INTO contacts (public_id,client_id,mecanicien_id,canal,statut,cree_le,decroche_le,termine_le,duree_s,resume,quartier_libelle,position_partagee,lat,lon) VALUES (?,?,?,?,\'termine\',?,?,?,?,?,?,1,?,?)');
$insSignalement = $pdo->prepare('INSERT INTO signalements (public_id,mecanicien_id,contact_id,client_id,motif,description,statut,traite_par_admin_id,traite_le,decision,cree_le) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
$signaler = static function (string $mecano, int $client, string $canal, int $duree, string $lieu, ?array $position, string $motif, string $texte, int $contactAvant, int $signaleIl, ?string $decision, ?int $classeApres) use ($pdo, $insContact, $insSignalement, $idMeca, $idClient, $admin1): void {
    $m = $idMeca($mecano);
    $c = $idClient($client);
    $cree = gmdate('Y-m-d H:i:s', time() - $signaleIl);
    $appel = gmdate('Y-m-d H:i:s', time() - $signaleIl - $contactAvant);
    $insContact->execute([Ulid::generer(), $c, $m, $canal, $appel, $appel, gmdate('Y-m-d H:i:s', strtotime($appel . ' UTC') + $duree), $canal === 'app' ? $duree : null, 'Voiture · Batterie', $lieu, $position[0] ?? null, $position[1] ?? null]);
    $contact = (int) $pdo->lastInsertId();
    $insSignalement->execute([Ulid::generer(), $m, $contact, $c, $motif, $texte, $decision === null ? 'nouveau' : 'sans_suite', $decision === null ? null : $admin1,
        $classeApres === null ? null : gmdate('Y-m-d H:i:s', time() - $signaleIl + $classeApres), $decision, $cree]);
};
$signaler('Koffi', 0, 'app', 240, 'Riviera 2', [5.3763, -3.949], 'comportement', 'On s’était mis d’accord sur 25 000 FCFA pour changer la batterie. Sur place, il a demandé 45 000. J’ai refusé et je suis partie. Il m’a rappelée trois fois et m’a dit qu’il savait où je travaille.', 8760, 9600, null, null);
$signaler('Ibrahim', 1, 'tel', 0, 'Port-Bouët', null, 'prix_abusif', 'Annoncé 15 000 FCFA au téléphone, 38 000 demandés sur place pour une simple batterie.', 10800, 18000, null, null);
$ier = time() - strtotime(gmdate('Y-m-d 19:12:00', time() - 86400) . ' UTC');
$signaler('Moussa', 5, 'whatsapp', 0, 'Marcory Biétry', null, 'autre', 'La fiche l’affichait à côté de chez moi, il était en réalité à l’autre bout de la ville.', 3600, $ier, null, null);
$classes = [
    ['Awa', 2, 'prix_abusif', 'Le prix annoncé était 12 000 FCFA, elle a demandé 20 000 FCFA.', 'Écart expliqué par une pièce changée en plus : facture montrée au client. Sans suite.', 86400 * 1 + 7200],
    ['Seydou', 3, 'pas_venu', 'Il devait venir à 15 h, personne n’est arrivé.', 'Mécanicien injoignable ce jour-là (panne de réseau), reprogrammé. Sans suite.', 86400 * 3 + 5400],
    ['Jean-Marc', 4, 'autre', 'Sa position affichée n’était pas la bonne.', 'Position vérifiée : elle était à jour au moment de l’appel. Sans suite.', 86400 * 5 + 3600],
    ['Ange', 8, 'comportement', 'Impoli et pressé au téléphone.', 'Rappel des règles fait par téléphone au mécanicien. Sans suite.', 86400 * 8 + 9000],
    ['Esther', 7, 'securite', 'Il a demandé 5 000 FCFA avant de venir.', 'Entretien avec le mécanicien : malentendu sur les frais de déplacement. Sans suite.', 86400 * 11 + 4000],
];
foreach ($classes as $k => [$prenom, $client, $motif, $texte, $decision, $il]) {
    $signaler($prenom, $client, 'tel', 0, 'Cocody', null, $motif, $texte, 5400, $il, $decision, 3600 * (2 + $k));
}
echo 'Signalements de démo : ' . (3 + count($classes)) . "\n";

// ---------- A5 · Entonnoir : des recherches avant chaque contact, des repropositions, des passages automatiques à indisponible, des délais de décision ----------
$panne = (int) $pdo->query("SELECT id FROM pannes WHERE code = 'batterie'")->fetchColumn();
$contacts30 = $pdo->query("SELECT id, client_id, mecanicien_id, cree_le, issue_client FROM contacts WHERE cree_le >= UTC_TIMESTAMP() - INTERVAL 30 DAY AND canal <> 'app' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$insRecherche = $pdo->prepare('INSERT INTO recherches (public_id,client_id,type_vehicule,panne_id,source_position,rayon_km,nb_resultats,cree_le) VALUES (?,?,\'voiture\',?,\'gps\',5,?,?)');
$insReprise = $pdo->prepare('INSERT INTO contacts (public_id,client_id,mecanicien_id,canal,contact_precedent_id,statut,cree_le,resume) VALUES (?,?,?,?,?,\'termine\',?,\'Voiture · Batterie\')');
$pdo->beginTransaction();
$recherches = 0;
$reprises = 0;
foreach ($contacts30 as $c) {
    $t = strtotime($c['cree_le'] . ' UTC');
    $n = 1 + (mt_rand(0, 99) < 85 ? 1 : 0) + (mt_rand(0, 99) < 45 ? 1 : 0);
    for ($k = 0; $k < $n; $k++) {
        $insRecherche->execute([Ulid::generer(), $c['client_id'], $panne, mt_rand(2, 8), gmdate('Y-m-d H:i:s', $t - 120 - $k * mt_rand(300, 21600))]);
        $recherches++;
    }
    if ($c['issue_client'] === 'pas_reponse' && mt_rand(0, 99) < 29) { // le client a appelé le mécano proposé ensuite
        $autre = $meca[array_rand($meca)];
        $insReprise->execute([Ulid::generer(), $c['client_id'], (int) $autre === (int) $c["mecanicien_id"] ? $meca[0] : $autre, 'tel', $c['id'], gmdate('Y-m-d H:i:s', $t + 180)]);
        $reprises++;
    }
}
$pdo->commit();
echo "Recherches de démo : $recherches · repropositions : $reprises\n";

// Mécanos passés indisponibles après 3 appels sans réponse (RG-D5), la plupart de retour dans la journée
$insDispo = $pdo->prepare('INSERT INTO historique_disponibilite (mecanicien_id,etat,raison,debut,fin) VALUES (?,?,?,?,?)');
foreach ([[2, 2], [4, 2], [6, 3], [9, 1], [13, 20], [20, 30]] as $i => [$depuis, $retour]) {
    $mecano = $validés[$i % count($validés)];
    $debut = time() - $depuis * 86400 - 3600 * (5 + $i);
    $insDispo->execute([$mecano, 'indisponible', 'auto_injoignable', gmdate('Y-m-d H:i:s', $debut), gmdate('Y-m-d H:i:s', $debut + $retour * 3600)]);
    if ($retour <= 3) {
        $insDispo->execute([$mecano, 'disponible', 'manuel', gmdate('Y-m-d H:i:s', $debut + $retour * 3600), gmdate('Y-m-d H:i:s', $debut + ($retour + 6) * 3600)]);
    }
}
// Décisions de l'équipe sur les dossiers à reprendre ou refusés : le délai se lit entre l'envoi et la décision (A5)
$decisions = ['Hervé' => ['a_reprendre', 17], 'Ange' => ['a_reprendre', 24], 'Salimata' => ['a_reprendre', 12], 'Gérard' => ['refuse', 41]];
$insHist = $pdo->prepare('INSERT INTO historique_dossier (mecanicien_id, de_statut, vers_statut, admin_id, motif, cree_le) SELECT id, \'en_attente\', ?, ?, motif_refus, DATE_ADD(dossier_envoye_le, INTERVAL ? HOUR) FROM mecaniciens WHERE prenom = ? AND statut_dossier = ?');
foreach ($decisions as $prenom => [$statut, $heures]) {
    $insHist->execute([$statut, $admin1, $heures, $prenom, $statut]);
}
echo "Passages automatiques et décisions de démo ajoutés\n";
