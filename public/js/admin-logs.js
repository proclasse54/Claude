document.addEventListener('keydown', e => {
  if (e.key === 'Escape')
    document.querySelectorAll('.modal-overlay:not([hidden])').forEach(m => m.setAttribute('hidden', ''));
});
