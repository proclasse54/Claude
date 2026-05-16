// ── Initialisation des event listeners ──────────────────────────────────────
// Tous les handlers sont enregistrés ici (plus de onclick= inline dans le PHP)
// afin de satisfaire la CSP script-src sans unsafe-hashes ni unsafe-inline.
document.addEventListener('DOMContentLoaded', () => {

  // Boutons d'ouverture de la modale import (header + empty-state)
  document.getElementById('btnOpenImport')?.addEventListener('click', openImportModal);
  document.getElementById('btnOpenImportEmpty')?.addEventListener('click', openImportModal);

  // Bouton d'ouverture de la modale création manuelle
  document.getElementById('btnOpenCreateClass')?.addEventListener('click', openCreateClassModal);

  // Fermeture des modales via data-close-modal (délégation — couvre tous les boutons Annuler et ×)
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-close-modal]');
    if (btn) closeModal(btn.dataset.closeModal);
  });

  // Bouton « Importer » dans la modale Pronote
  document.getElementById('importBtn')?.addEventListener('click', doImport);

  // Textarea Pronote — prévisualisation en temps réel
  document.getElementById('pronoteData')?.addEventListener('input', e => previewImport(e.target.value));

  // Formulaire de création manuelle de classe
  document.getElementById('createClassForm')?.addEventListener('submit', createClass);

  // Suppression individuelle — délégation sur le grid des cartes
  document.getElementById('classesGrid')?.addEventListener('click', e => {
    const btn = e.target.closest('.btn-delete-class');
    if (btn) deleteClass(parseInt(btn.dataset.id, 10), btn.dataset.name);
  });

  // Checkboxes des classes — délégation pour updateSelection()
  document.getElementById('classesGrid')?.addEventListener('change', e => {
    if (e.target.classList.contains('class-checkbox')) updateSelection();
  });

  // Checkbox « Tout sélectionner »
  document.getElementById('selectAll')?.addEventListener('change', e => toggleSelectAll(e.target));

  // Bouton « Supprimer la sélection »
  document.getElementById('deleteSelectedBtn')?.addEventListener('click', deleteSelected);

  // Bouton « Tout supprimer »
  document.getElementById('btnDeleteAll')?.addEventListener('click', deleteAll);
});

// ── Modale import Pronote ────────────────────────────────────────────────────

function openImportModal() {
  document.getElementById('pronoteData').value = '';
  document.getElementById('importPreview').hidden = true;
  document.getElementById('importBtn').disabled = true;
  document.getElementById('importModal').removeAttribute('hidden');
  setTimeout(() => document.getElementById('pronoteData').focus(), 100);
}

function previewImport(text) {
  const lines = text.trim().split('\n').filter(l => l.trim());
  const preview = document.getElementById('importPreview');
  const btn = document.getElementById('importBtn');
  if (lines.length < 2) { preview.hidden = true; btn.disabled = true; return; }
  const count = lines.slice(1).filter(l => l.trim()).length;
  const headerLine = lines[0].split('\t');
  const classeIdx = headerLine.findIndex(h => h.trim() === 'Classe');
  let classes = new Set();
  if (classeIdx >= 0) {
    lines.slice(1).forEach(l => {
      const val = l.split('\t')[classeIdx];
      if (val && val.trim()) classes.add(val.trim());
    });
  }
  preview.innerHTML = '\u2705 <strong>' + count + '</strong> \u00e9l\u00e8ve(s) \u00b7 '
    + '<strong>' + classes.size + '</strong> classe(s) d\u00e9tect\u00e9e(s) : '
    + Array.from(classes).sort().join(', ');
  preview.hidden = false;
  btn.disabled = count === 0;
}

function doImport() {
  const text = document.getElementById('pronoteData').value.trim();
  if (!text) return;
  const btn = document.getElementById('importBtn');
  btn.disabled = true;
  btn.textContent = 'Import en cours\u2026';

  fetch('/api/classes/0/import-paste', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: text })
  })
  .then(r => r.text()).then(text => {
    try {
      const d = JSON.parse(text);
      if (d.ok) {
        closeModal('importModal');
        let msg = '\u2705 ' + d.inserted + ' \u00e9l\u00e8ve(s) import\u00e9(s)';
        if (d.classes_created) msg += '\n\ud83d\udcda ' + d.classes_created + ' classe(s) cr\u00e9\u00e9e(s)';
        if (d.skipped) msg += '\n\u26a0\ufe0f ' + d.skipped + ' ligne(s) ignor\u00e9e(s)';
        alert(msg);
        location.reload();
      } else {
        btn.disabled = false; btn.textContent = 'Importer';
        alert('Erreur : ' + (d.error || JSON.stringify(d)));
      }
    } catch(e) {
      document.open(); document.write(text); document.close();
    }
  });
}

// ── Modale création manuelle ─────────────────────────────────────────────────

function openCreateClassModal() { document.getElementById('createClassModal').removeAttribute('hidden'); }

function createClass(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fetch('/api/classes', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(fd)) })
    .then(r => r.json()).then(d => { if (d.ok) window.location = '/classes/' + d.id; });
}

// ── Suppression ──────────────────────────────────────────────────────────────

function deleteClass(id, name) {
  if (!confirm('Supprimer "' + name + '" et tous ses \u00e9l\u00e8ves ?')) return;
  fetch('/api/classes/' + id, { method: 'DELETE' })
    .then(r => r.json()).then(d => { if (d.ok) location.reload(); });
}

// ── Sélection multiple ───────────────────────────────────────────────────────

function updateSelection() {
  const checked = document.querySelectorAll('.class-checkbox:checked');
  const count = checked.length;
  document.getElementById('selectedCount').textContent = count + ' s\u00e9lectionn\u00e9e(s)';
  document.getElementById('deleteSelectedBtn').disabled = count === 0;
  document.getElementById('selectAll').indeterminate =
    count > 0 && count < document.querySelectorAll('.class-checkbox').length;
  document.getElementById('selectAll').checked =
    count === document.querySelectorAll('.class-checkbox').length;
}

function toggleSelectAll(cb) {
  document.querySelectorAll('.class-checkbox').forEach(c => c.checked = cb.checked);
  updateSelection();
}

function deleteSelected() {
  const ids = Array.from(document.querySelectorAll('.class-checkbox:checked')).map(c => c.value);
  if (!ids.length) return;
  if (!confirm('Supprimer ' + ids.length + ' classe(s) et tous leurs \u00e9l\u00e8ves ?')) return;
  Promise.all(ids.map(id => fetch('/api/classes/' + id, { method: 'DELETE' }).then(r => r.json())))
    .then(() => location.reload());
}

function deleteAll() {
  const total = document.querySelectorAll('.class-checkbox').length;
  if (!confirm('\u26a0\ufe0f Supprimer les ' + total + ' classes, \u00e9l\u00e8ves, groupes, plans et s\u00e9ances ?\n\nCette action est irr\u00e9versible.')) return;
  fetch('/api/classes', { method: 'DELETE' })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert('Erreur : ' + d.error); });
}

// ── Fermeture des modales ────────────────────────────────────────────────────

/**
 * Masque une modale par son id.
 * Appelé depuis les boutons Annuler/× via data-close-modal (voir DOMContentLoaded).
 */
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.setAttribute('hidden', '');
}
