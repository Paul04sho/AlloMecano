// M0 · Accueil mécanicien : démonstration locale de l’interrupteur de disponibilité.
// Cet exemple ne modifie aucun statut en base ; l’état réel sera piloté par l’espace mécano M2.
const carte = document.querySelector('[data-dispo-carte]');

if (carte) {
  const bascule = carte.querySelector('[data-dispo-toggle]');
  const titre = carte.querySelector('[data-dispo-titre]');
  const etiquette = carte.querySelector('[data-dispo-etiquette]');
  const libelle = carte.querySelector('[data-dispo-libelle]');
  const aide = carte.querySelector('[data-dispo-aide]');
  let disponible = true;

  const afficherEtat = () => {
    carte.classList.toggle('is-indisponible', !disponible);
    bascule.setAttribute('aria-pressed', String(disponible));

    titre.textContent = disponible ? 'Disponible depuis 07:32' : 'Vous êtes indisponible';
    etiquette.textContent = disponible
      ? 'VISIBLE À 10 KM AUTOUR DE VOUS'
      : 'MASQUÉ DES CLIENTS';
    libelle.textContent = disponible ? 'Passer indisponible' : 'Passer disponible';
    aide.textContent = disponible
      ? 'Disponible : les clients autour de vous vous voient.'
      : 'Indisponible : votre profil n’apparaît plus dans la recherche.';

    const icone = bascule.querySelector('.icone use');
    if (icone) {
      icone.setAttribute('href', disponible
        ? '/assets/icones/sprite.svg#i-coche'
        : '/assets/icones/sprite.svg#i-viseur');
    }
  };

  bascule.addEventListener('click', () => {
    disponible = !disponible;
    afficherEtat();
  });
}
