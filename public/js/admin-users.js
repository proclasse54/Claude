/**
 * admin-users.js — Gestion des utilisateurs (AdminController::users)
 *
 * Remplace tous les onclick inline supprimés de views/admin/users.php
 * pour respecter la CSP (script-src-attr interdit les gestionnaires inline).
 *
 * Pattern : délégation d'événement sur document via data-action.
 * Actions disponibles :
 *   - open-create  : ouvre la modale de création
 *   - open-edit    : ouvre la modale d'édition (data-user = JSON utilisateur)
 *   - delete-user  : supprime un utilisateur (data-id, data-email)
 *   - close-modal  : ferme la modale parente (.modal-overlay)
 */

/**
 * Remplit et affiche la modale d'édition pour l'utilisateur donné.
 * @param {Object} u - Objet utilisateur : { id, email, role, is_active }
 */
function openEdit(u) {
  const f = document.getElementById('formEdit');
  f.action = '/admin/users/' + u.id;
  document.getElementById('e_email').value   = u.email;
  document.getElementById('e_role').value    = u.role;
  document.getElementById('e_active').value  = u.is_active;
  document.getElementById('e_password').value = '';
  document.getElementById('modalEdit').removeAttribute('hidden');
}

/**
 * Demande confirmation puis supprime l'utilisateur via DELETE /admin/users/:id.
 * Retire la ligne du tableau si la suppression réussit.
 * @param {number|string} id    - ID de l'utilisateur
 * @param {string}        email - Email affiché dans le message de confirmation
 */
async function deleteUser(id, email) {
  if (!confirm('Supprimer le compte ' + email + ' ?')) return;
  const res  = await fetch('/admin/users/' + id, { method: 'DELETE' });
  const json = await res.json();
  if (json.ok) {
    const row = document.getElementById('row-' + id);
    if (row) row.remove();
  } else {
    alert(json.error ?? 'Erreur lors de la suppression.');
  }
}

/**
 * Délégation d'événements : gère tous les clics via data-action.
 * Évite tout onclick inline dans le HTML (interdit par la CSP script-src-attr).
 */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;

  switch (btn.dataset.action) {

    case 'open-create':
      // Ouvre la modale de création d'un nouveau compte
      document.getElementById('modalCreate').removeAttribute('hidden');
      break;

    case 'open-edit':
      // Récupère l'objet utilisateur encodé en JSON dans data-user
      openEdit(JSON.parse(btn.dataset.user));
      break;

    case 'delete-user':
      // data-id et data-email portent les paramètres de suppression
      deleteUser(btn.dataset.id, btn.dataset.email);
      break;

    case 'close-modal':
      // Remonte au .modal-overlay le plus proche et le masque
      btn.closest('.modal-overlay').setAttribute('hidden', '');
      break;
  }
});

/**
 * Fermeture des modales ouvertes avec la touche Échap.
 */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay:not([hidden])').forEach(m => {
      m.setAttribute('hidden', '');
    });
  }
});
