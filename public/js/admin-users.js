function openEdit(u) {
  const f = document.getElementById('formEdit');
  f.action = '/admin/users/' + u.id;
  document.getElementById('e_email').value  = u.email;
  document.getElementById('e_role').value   = u.role;
  document.getElementById('e_active').value = u.is_active;
  document.getElementById('e_password').value = '';
  document.getElementById('modalEdit').removeAttribute('hidden');
}

async function deleteUser(id, email) {
  if (!confirm('Supprimer le compte ' + email + ' ?')) return;
  const res = await fetch('/admin/users/' + id, { method: 'DELETE' });
  const json = await res.json();
  if (json.ok) {
    const row = document.getElementById('row-' + id);
    if (row) row.remove();
  } else {
    alert(json.error ?? 'Erreur lors de la suppression.');
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay:not([hidden])').forEach(m => m.setAttribute('hidden', ''));
  }
});
