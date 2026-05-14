// ── Onglets ───────────────────────────────────────────────
document.querySelectorAll('.import-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.import-tab').forEach(t => {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });
    tab.classList.add('active');
    tab.setAttribute('aria-selected', 'true');
    const target = tab.dataset.tab;
    document.querySelectorAll('.import-pane').forEach(p => {
      p.hidden = p.id !== 'pane-' + target;
    });
  });
});
