/**
 * admin-logs.js — Gestion de la page logs (AdminController::logs)
 *
 * Remplace les onclick inline supprimés de views/admin/logs.php
 * pour respecter la CSP (script-src-attr interdit les gestionnaires inline).
 *
 * Actions disponibles via data-action :
 *   - open-purge  : ouvre la modale de purge
 *   - close-modal : ferme la modale parente (.modal-overlay)
 */

/**
 * Délégation d'événements : gère tous les clics via data-action.
 */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;

  switch (btn.dataset.action) {

    case 'open-purge':
      // Ouvre la modale de confirmation de purge des logs
      document.getElementById('modalPurge').removeAttribute('hidden');
      break;

    case 'close-modal':
      // Remonte au .modal-overlay le plus proche et le masque
      btn.closest('.modal-overlay').setAttribute('hidden', '');
      break;
  }
});

/**
 * Fermeture de la modale avec la touche Échap.
 */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay:not([hidden])').forEach(m => {
      m.setAttribute('hidden', '');
    });
  }
});
