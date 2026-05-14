// Détection du thème système au chargement de la page d'installation
// Applique data-theme="dark" si l'OS est en mode sombre, avant tout rendu
(function () {
  if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
