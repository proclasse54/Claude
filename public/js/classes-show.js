/* CLASS_ID est injecté via data-class-id sur #classShowData */
const CLASS_ID = parseInt(document.getElementById('classShowData').dataset.classId);

// ── Initialisation des event listeners ──────────────────────────────────────
// Tous les handlers sont enregistrés ici (plus de onclick= inline dans le PHP)
// afin de satisfaire la CSP script-src sans unsafe-hashes ni unsafe-inline.
document.addEventListener('DOMContentLoaded', () => {

  // Navigation par onglets — délégation sur .tabs via data-tab
  document.querySelector('.tabs')?.addEventListener('click', e => {
    const btn = e.target.closest('.tab[data-tab]');
    if (btn) showTab(btn.dataset.tab, btn);
  });

  // Bouton "Importer depuis Pronote" (onglet Élèves)
  document.getElementById('btnOpenImport')?.addEventListener('click', openImportModal);

  // Bouton "+ Nouveau plan" (onglet Plans)
  document.getElementById('btnOpenNewPlan')?.addEventListener('click', openNewPlanModal);

  // Fermeture des modales via data-close-modal (délégation globale)
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-close-modal]');
    if (btn) closeModal(btn.dataset.closeModal);
  });

  // Textarea Pronote — prévisualisation en temps réel
  document.getElementById('pronoteData')?.addEventListener('input', e => previewImport(e.target.value));

  // Bouton « Importer » dans la modale Pronote
  document.getElementById('importBtn')?.addEventListener('click', doImport);

  // Formulaire nouveau plan
  document.getElementById('newPlanForm')?.addEventListener('submit', createPlan);

  // Suppression de plan — délégation sur le grid des plans
  document.getElementById('tab-plans')?.addEventListener('click', e => {
    const btn = e.target.closest('.btn-delete-plan');
    if (btn) deletePlan(parseInt(btn.dataset.planId, 10));
  });

  // Restauration de l'onglet actif depuis le paramètre URL ?tab=
  const params = new URLSearchParams(window.location.search);
  const tabParam = params.get('tab');
  if (tabParam) {
    const btn = document.querySelector(`.tab[data-tab="${tabParam}"]`);
    if (btn) showTab(tabParam, btn);
  }
});

// ── Navigation par onglets ──────────────────────────────────────────────────────

function showTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.hidden = true);
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + name).hidden = false;
  btn.classList.add('active');
}

// ── Modale import Pronote ─────────────────────────────────────────────────

function openImportModal() {
  document.getElementById('pronoteData').value = '';
  document.getElementById('importPreview').hidden = true;
  document.getElementById('importBtn').disabled = true;
  document.getElementById('importModal').classList.add('is-open');
  setTimeout(() => document.getElementById('pronoteData').focus(), 100);
}

function previewImport(text) {
  const lines = text.trim().split('\n').filter(l => l.trim());
  if (lines.length < 2) {
    document.getElementById('importPreview').hidden = true;
    document.getElementById('importBtn').disabled = true;
    return;
  }
  const dataLines = lines.slice(1).filter(l => l.trim());
  const count = dataLines.length;
  document.getElementById('previewCount').textContent =
    '\u2705 ' + count + ' \u00e9l\u00e8ve' + (count > 1 ? 's' : '') + ' d\u00e9tect\u00e9' + (count > 1 ? 's' : '');
  document.getElementById('importPreview').hidden = false;
  document.getElementById('importBtn').disabled = count === 0;
}

function doImport() {
  const text = document.getElementById('pronoteData').value.trim();
  if (!text) return;
  document.getElementById('importBtn').disabled = true;
  document.getElementById('importBtn').textContent = 'Import en cours\u2026';

  fetch('/api/classes/' + CLASS_ID + '/import-paste', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: text })
  })
  .then(r => r.text()).then(text => {
    try {
      const d = JSON.parse(text);
      if (d.ok) {
        closeModal('importModal');
        location.reload();
      } else {
        alert('Erreur : ' + (d.error || JSON.stringify(d)));
      }
    } catch(e) {
      document.open(); document.write(text); document.close();
    }
  });
}

// ── Modale nouveau plan ──────────────────────────────────────────────────

function openNewPlanModal() { document.getElementById('newPlanModal').classList.add('is-open'); }

function createPlan(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fetch('/api/classes/' + CLASS_ID + '/plans', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      room_id:  parseInt(fd.get('room_id'), 10),
      group_id: fd.get('group_id') ? parseInt(fd.get('group_id'), 10) : null,
      name:     fd.get('name') || 'Plan par d\u00e9faut',
    })
  }).then(r => r.json()).then(d => {
    if (d.ok) window.location = '/classes/' + CLASS_ID + '?tab=plans';
    else alert('Erreur : ' + (d.error ?? JSON.stringify(d)));
  });
}

// ── Suppression d'un plan ─────────────────────────────────────────────────

function deletePlan(id) {
  if (!confirm('Supprimer ce plan ?')) return;
  fetch('/api/plans/' + id, { method: 'DELETE' })
    .then(r => r.json()).then(d => { if (d.ok) location.reload(); });
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
