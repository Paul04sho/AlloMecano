# Mouvement · ce qui va au-delà du prototype

`docs/mouvement-et-interactions.html` reste la référence. Cette note liste les mouvements ajoutés en octobre 2026 qui **n'y sont pas dessinés**, pour que l'équipe les valide ou les retire. La mise en page Figma n'est jamais modifiée : seuls l'opacité et les transformations bougent.

Règles communes à tous ces ajouts :
- désactivés en mouvement réduit (`prefers-reduced-motion: reduce`) ;
- sans prise en charge du navigateur, la page s'affiche telle qu'avant ;
- aucune action n'attend la fin d'une animation : « Appeler » est toujours utilisable tout de suite ;
- aucune librairie ; durées, courbes et distances par les jetons de `tokens.css`.

| Où | Quoi | Quand | Valeurs | Fichiers |
|---|---|---|---|---|
| Toutes les pages | Transition entre pages : l'ancienne s'efface, la nouvelle monte de 6 px (au retour, elle descend du haut) | navigation, Chrome Android 126+ | `--t-etat` / `--t-page`, `--ease-sortie` | `layouts/base.php`, `transition-vue.css` |
| Toutes les pages | La barre du haut, la barre d'étapes et le socle restent en place pendant le changement de page | navigation | `--t-entree` | `transition-vue.css` |
| Résultats → fiche | Le portrait du mécano voyage de sa ligne jusqu'en haut de sa fiche | navigation | `--t-lieu`, `--ease-sortie` | `ligne-classement.php`, `portrait-fiche.php`, `vue_portrait()` |
| C1 · récit | La route se trace sous chaque heure et un voyant jaune la parcourt, de 09:41 à 09:58 | défilement | plage `cover 25 %` → `55 %` | `accueil-defilement.css` |
| C1 · récit | Chaque heure se pose, le récit monte | défilement | `--esp-16`, `--esp-24` | `accueil-defilement.css` |
| C1 · vérification | Le fond sombre arrive resserré (92 %) et s'élargit jusqu'aux bords ; portrait et pièce se rapprochent ; chaque contrôle reçoit son poinçon | défilement | `--esp-32`, `--ease-tampon` | `accueil-defilement.css` |
| C1 · trombinoscope | Mobile : chaque visage grandit en entrant dans la bande. Desktop : les colonnes montent en décalé | glissement / défilement | `scale(.88)` → 1 | `accueil-defilement.css` |
| C1 · communes | Les nombres de mécanos disponibles se comptent de 0 à leur valeur | arrivée du tableau | 520 ms, délai 300 ms (comme les bornes de la fiche) | `accueil.js` |
| C1 · héros | Feux de détresse : le bouton principal clignote deux fois à 1 Hz, une seule fois | après l'arrivée de l'accroche | 2 s, délai 800 ms | `accueil.css` |
| C4 · résultats | La liste se pose en même temps que les repères, dans l'ordre des distances | arrivée | `--t-entree`, +70 ms par mécano | `resultats.css`, `resultats.php` |
| C5 · fiche | Le portrait glisse moins vite que la page et s'approche un peu | défilement | 30 %, `scale(1.06)` | `fiche.css` |
| Barre client (mobile) | Se replie en descendant, revient en remontant, près du haut ou au clavier | défilement | `--t-entree`, seuil 8 px | `navigation.css`, `mouvement.js` |
| Client (Android) | Vibrations : position trouvée (15 ms), « Copié » (10 ms), le mécano décroche (20-60-20 ms) | après un geste sur la page | — | `vibrer()` dans `app.js` |

Jeton ajouté : `--dispo-vif` (`#3dd68c`), voyant vert de la bannière « Vous êtes de nouveau en ligne » (la bannière, elle, est dans le prototype).

## À tester sur un vrai téléphone

- Les transitions entre pages et le portrait qui voyage (impossible à capturer : trop rapides).
- Les vibrations.
- La fluidité du défilement de l'accueil sur un Android d'entrée de gamme.
