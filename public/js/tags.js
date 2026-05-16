// ── Initialisation des event listeners ─────────────────────────────────────
// Script injecté en bas de <body> via $content : DOM déjà construit,
// pas besoin de DOMContentLoaded ni de defer.
// Tous les onclick=/onsubmit= inline ont été supprimés dans tags/index.php
// (interdit par la CSP script-src-attr).

// Bouton « Nouveau tag » (header)
document.getElementById('btnOpenCreateTag')?.addEventListener('click', openCreateModal);

// Bouton « Créer un tag » (empty-state)
document.getElementById('btnOpenCreateTagEmpty')?.addEventListener('click', openCreateModal);

// Fermeture de la modale via data-close-modal (délégation globale)
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-close-modal]');
  if (btn) closeModal(btn.dataset.closeModal);
});

// Boutons « Modifier » — délégation sur le grid, paramètres lus depuis data-*
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-edit-tag');
  if (!btn) return;
  openEditModal(
    parseInt(btn.dataset.id, 10),
    btn.dataset.label,
    btn.dataset.color,
    btn.dataset.icon,
    parseInt(btn.dataset.order, 10)
  );
});

// Boutons « Supprimer » — délégation globale
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-delete-tag');
  if (!btn) return;
  deleteTag(parseInt(btn.dataset.id, 10), btn.dataset.label);
});

// Formulaire tag (création + édition) — onsubmit= supprimé dans la vue
document.getElementById('tagForm')?.addEventListener('submit', saveTag);

// ── Modale création ─────────────────────────────────────────────────────

/**
 * Ouvre la modale en mode création (formulaire vide).
 * Appelé par #btnOpenCreateTag et #btnOpenCreateTagEmpty.
 */
function openCreateModal() {
  document.getElementById('tagModalTitle').textContent = 'Nouveau tag';
  document.getElementById('tagId').value    = '';
  document.getElementById('tagLabel').value = '';
  document.getElementById('tagIcon').value  = '';
  document.getElementById('tagColor').value = '#888888';
  document.getElementById('tagOrder').value = '99';
  document.getElementById('tagModal').hidden = false;
}

// ── Modale édition ─────────────────────────────────────────────────────

/**
 * Ouvre la modale en mode édition, pré-remplie avec les valeurs du tag.
 * Appelé par délégation sur .btn-edit-tag (data-id, data-label, data-color, data-icon, data-order).
 */
function openEditModal(id, label, color, icon, order) {
  document.getElementById('tagModalTitle').textContent = 'Modifier le tag';
  document.getElementById('tagId').value    = id;
  document.getElementById('tagLabel').value = label;
  document.getElementById('tagIcon').value  = icon;
  document.getElementById('tagColor').value = color;
  document.getElementById('tagOrder').value = order;
  document.getElementById('tagModal').hidden = false;
}

// ── Sauvegarde ─────────────────────────────────────────────────────────────

/**
 * Crée ou met à jour un tag via POST /api/tags.
 * Appelé par le listener submit sur #tagForm.
 */
function saveTag(e) {
  e.preventDefault();
  const id = document.getElementById('tagId').value;
  fetch('/api/tags', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id:         id ? parseInt(id) : null,
      label:      document.getElementById('tagLabel').value,
      icon:       document.getElementById('tagIcon').value,
      color:      document.getElementById('tagColor').value,
      sort_order: parseInt(document.getElementById('tagOrder').value),
    })
  })
  .then(r => r.json())
  .then(d => { if (d.ok) location.reload(); else alert(d.error); });
}

// ── Suppression ──────────────────────────────────────────────────────────

/**
 * Supprime un tag avec confirmation.
 * Si le serveur retourne can_force=true, propose une suppression forcée.
 * Appelé par délégation sur .btn-delete-tag (data-id, data-label).
 */
function deleteTag(id, label) {
  if (!confirm('Supprimer le tag "' + label + '" ?')) return;
  fetch('/api/tags/' + id, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        location.reload();
      } else if (d.can_force) {
        if (confirm('\u26a0\ufe0f ' + d.error + '\n\nForcer la suppression quand m\u00eame ?')) {
          fetch('/api/tags/' + id + '?force=1', { method: 'DELETE' })
            .then(r => r.json())
            .then(d2 => { if (d2.ok) location.reload(); });
        }
      } else {
        alert(d.error);
      }
    });
}

// ── Fermeture de modale ──────────────────────────────────────────────────

/**
 * Masque la modale par son id.
 * Appelé via délégation sur les éléments data-close-modal (voir listener ci-dessus).
 */
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.setAttribute('hidden', '');
}

// ── Emoji picker ───────────────────────────────────────────────────────────

let emojiPickerReady = false;

/**
 * Ouvre/ferme le picker emoji au clic sur #btnEmojiPicker.
 * L'initialisation (listener emoji-click + fermeture au clic extérieur) n'est
 * faite qu'une seule fois grâce à emojiPickerReady.
 */
document.getElementById('btnEmojiPicker')?.addEventListener('click', function(e) {
  e.stopPropagation();
  if (!emojiPickerReady) {
    const picker = document.getElementById('emojiPicker');
    const input  = document.getElementById('tagIcon');
    // Sélection d'un emoji : remplit l'input et ferme le picker
    picker.addEventListener('emoji-click', ev => {
      input.value = ev.detail.unicode;
      picker.classList.add('emoji-picker-hidden');
    });
    // Clic en dehors du picker : ferme le picker
    document.addEventListener('click', ev => {
      if (!picker.contains(ev.target) && ev.target !== document.getElementById('btnEmojiPicker')) {
        picker.classList.add('emoji-picker-hidden');
      }
    });
    emojiPickerReady = true;
  }
  document.getElementById('emojiPicker').classList.toggle('emoji-picker-hidden');
});
