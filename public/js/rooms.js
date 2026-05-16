// ── Initialisation des event listeners ─────────────────────────────────────
// Script injecté en bas de <body> via $content : DOM déjà construit,
// pas besoin de DOMContentLoaded ni de defer.

// Suppression d'une salle — délégation sur le grid des cartes
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-delete-room');
  if (btn) deleteRoom(parseInt(btn.dataset.id, 10), btn.dataset.name);
});

// ── Suppression ─────────────────────────────────────────────────────────────

/**
 * Supprime une salle après confirmation.
 * Appelé via délégation sur les boutons .btn-delete-room (data-id, data-name).
 * Plus aucun onclick= inline dans le PHP (interdit par la CSP script-src-attr).
 */
function deleteRoom(id, name) {
  if (!confirm('Supprimer la salle "' + name + '" ?')) return;
  fetch('/api/rooms/' + id, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); });
}
