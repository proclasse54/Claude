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

function openCreateClassModal() { document.getElementById('createClassModal').removeAttribute('hidden'); }

function createClass(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fetch('/api/classes', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(fd)) })
    .then(r => r.json()).then(d => { if (d.ok) window.location = '/classes/' + d.id; });
}

function deleteClass(id, name) {
  if (!confirm('Supprimer "' + name + '" et tous ses \u00e9l\u00e8ves ?')) return;
  fetch('/api/classes/' + id, { method: 'DELETE' })
    .then(r => r.json()).then(d => { if (d.ok) location.reload(); });
}

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
