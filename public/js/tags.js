function openCreateModal() {
  document.getElementById('tagModalTitle').textContent = 'Nouveau tag';
  document.getElementById('tagId').value    = '';
  document.getElementById('tagLabel').value = '';
  document.getElementById('tagIcon').value  = '';
  document.getElementById('tagColor').value = '#888888';
  document.getElementById('tagOrder').value = '99';
  document.getElementById('tagModal').hidden = false;
}

function openEditModal(id, label, color, icon, order) {
  document.getElementById('tagModalTitle').textContent = 'Modifier le tag';
  document.getElementById('tagId').value    = id;
  document.getElementById('tagLabel').value = label;
  document.getElementById('tagIcon').value  = icon;
  document.getElementById('tagColor').value = color;
  document.getElementById('tagOrder').value = order;
  document.getElementById('tagModal').hidden = false;
}

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

function deleteTag(id, label) {
  fetch(`/api/tags/${id}`, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        location.reload();
      } else if (d.can_force) {
        if (confirm(`\u26a0\ufe0f ${d.error}\n\nForcer la suppression quand m\u00eame ?`)) {
          fetch(`/api/tags/${id}?force=1`, { method: 'DELETE' })
            .then(r => r.json())
            .then(d2 => { if (d2.ok) location.reload(); });
        }
      } else {
        alert(d.error);
      }
    });
}

let emojiPickerReady = false;

document.getElementById('btnEmojiPicker').addEventListener('click', function(e) {
    e.stopPropagation();

    if (!emojiPickerReady) {
        const picker = document.getElementById('emojiPicker');
        const input  = document.getElementById('tagIcon');

        picker.addEventListener('emoji-click', (ev) => {
            input.value = ev.detail.unicode;
            picker.classList.add('emoji-picker-hidden');
        });

        document.addEventListener('click', (ev) => {
            if (!picker.contains(ev.target) && ev.target !== document.getElementById('btnEmojiPicker')) {
                picker.classList.add('emoji-picker-hidden');
            }
        });

        emojiPickerReady = true;
    }

    document.getElementById('emojiPicker').classList.toggle('emoji-picker-hidden');
});
