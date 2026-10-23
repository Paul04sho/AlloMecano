document.addEventListener('DOMContentLoaded', () => {
    const boutons = document.querySelectorAll('.accueil__theme-btn');

    // 1. Appliquer le thème actif au clic
    boutons.forEach(bouton => {
        bouton.addEventListener('click', () => {
            const themeChoisi = bouton.getAttribute('data-theme');
            
            if (themeChoisi === 'systeme') {
                // Supprimer le cookie pour laisser le système décider
                document.cookie = "theme=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                const estSombreSysteme = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', estSombreSysteme ? 'sombre' : 'clair');
            } else {
                // Enregistrer le cookie pour PHP (valable 1 an)
                const d = new Date();
                d.setTime(d.getTime() + (365*24*60*60*1000));
                document.cookie = `theme=${themeChoisi}; expires=${d.toUTCString()}; path=/;`;
                document.documentElement.setAttribute('data-theme', themeChoisi);
            }
            
            mettreAJourBoutonsUI(themeChoisi);
        });
    });

    function mettreAJourBoutonsUI(themeActif) {
        boutons.forEach(bouton => {
            if (bouton.getAttribute('data-theme') === themeActif) {
                bouton.classList.add('accueil__theme-btn--actif');
            } else {
                bouton.classList.remove('accueil__theme-btn--actif');
            }
        });
    }
});
