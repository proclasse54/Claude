// ── Dark mode ─────────────────────────────────────────────
// Priorité : 1) choix enregistré dans localStorage, 2) préférence système.
// Le thème est appliqué AVANT le premier rendu (IIFE synchrone) pour éviter
// tout flash de mode clair au rechargement ou à la navigation.
(function(){
  const root = document.documentElement;
  const btn  = document.querySelector('[data-theme-toggle]');

  // Lecture du choix persisté, avec fallback sur la préférence système
  const saved = localStorage.getItem('proclasse-theme');
  let theme   = saved ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  // Application immédiate pour éviter le flash
  root.setAttribute('data-theme', theme);

  // Mise à jour de l'icône du bouton selon le thème courant
  function updateIcon() {
    if (!btn) return;
    btn.setAttribute('aria-label', 'Passer en mode ' + (theme === 'dark' ? 'clair' : 'sombre'));
    btn.innerHTML = theme === 'dark'
      ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>'
      : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  }

  updateIcon();

  if (btn) btn.addEventListener('click', () => {
    theme = theme === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', theme);
    // Persistance du choix pour toutes les pages suivantes
    localStorage.setItem('proclasse-theme', theme);
    updateIcon();
  });
})();

// ── Helpers globaux ───────────────────────────────────────
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.setAttribute('hidden', '');
}

// Fermer modale en cliquant sur l'overlay
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.setAttribute('hidden', '');
  }
});

// Fermer avec Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay:not([hidden])').forEach(m => m.setAttribute('hidden', ''));
  }
});


//  toggle vue séances + persistance dans l'URL
let _dragCreateInited = false;

function setView(v) {
    const viewList = document.getElementById('viewList');
    const viewWeek = document.getElementById('viewWeek');
    if (!viewList || !viewWeek) return; // pas sur la page séances → on sort
    viewList.hidden = (v === 'week');
    viewWeek.hidden = (v === 'list');
    const btnList = document.getElementById('btnList');
    const btnWeek = document.getElementById('btnWeek');
    if (btnList) btnList.classList.toggle('active', v === 'list');
    if (btnWeek) btnWeek.classList.toggle('active', v === 'week');
    const url = new URL(location.href);
    url.searchParams.set('view', v);
    history.replaceState(null, '', url);

    // initDragCreate() doit être appelé APRÈS que viewWeek soit visible,
    // sinon getBoundingClientRect() retourne 0 sur les éléments cachés.
    if (v === 'week' && !_dragCreateInited && typeof initDragCreate === 'function') {
        _dragCreateInited = true;
        initDragCreate();
    }
}
setView(new URLSearchParams(location.search).get('view') || 'week');
