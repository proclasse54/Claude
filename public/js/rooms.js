function deleteRoom(id, name) {
  if (!confirm('Supprimer la salle "' + name + '" ?')) return;
  fetch('/api/rooms/' + id, {method:'DELETE'})
    .then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}
