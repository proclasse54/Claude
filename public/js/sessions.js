const _plansEl = document.getElementById('plans-data');
const PLANS = _plansEl ? JSON.parse(_plansEl.dataset.plans) : [];

const TIME_SLOTS = [
  '07:00','07:30','08:00','08:30','09:00','09:30',
  '10:00','10:30','11:00','11:30','12:00','12:30',
  '13:00','13:30','14:00','14:30','15:00','15:30',
  '16:00','16:30','17:00','17:30','18:00','18:30',
  '19:00'
];

function clearWeekTransientUi() {
  document.querySelectorAll('.week-selected-bar, .drag-selection').forEach(el => el.remove());
  document.querySelectorAll('.week-col-body.dragging').forEach(col => col.classList.remove('dragging'));
  dragState = null;
}

// ══ TOGGLE VUE ══
let dragInitialized = false;

function setView(view) {
  const viewList = document.getElementById('viewList');
  const viewWeek = document.getElementById('viewWeek');
  const btnList  = document.getElementById('btnList');
  const btnWeek  = document.getElementById('btnWeek');
  if (view === 'week') {
    viewList.setAttribute('hidden', '');
    viewWeek.removeAttribute('hidden');
    btnList.classList.remove('active');
    btnWeek.classList.add('active');
    if (!dragInitialized) { initDragCreate(); dragInitialized = true; }
  } else {
    viewWeek.setAttribute('hidden', '');
    viewList.removeAttribute('hidden');
    btnWeek.classList.remove('active');
    btnList.classList.add('active');
  }
}

document.getElementById('btnList').addEventListener('click', () => setView('list'));
document.getElementById('btnWeek').addEventListener('click', () => setView('week'));

// ══ DRAG-TO-CREATE ══
let dragState = null;

function yToMinutes(y, heureDebut, pxParHeure) {
  return Math.round(((y / pxParHeure) * 60 + heureDebut * 60 - 15) / 30) * 30;
}
function minutesToTime(min) {
  return String(Math.floor(min/60)).padStart(2,'0') + ':' + String(min%60).padStart(2,'0');
}
function getRelativeY(e, col) {
  const rect = col.getBoundingClientRect();
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  return Math.max(0, Math.min(clientY - rect.top, col.clientHeight));
}
function updateDragEl(el, startMin, endMin, heureDebut, pxParHeure) {
  el.style.top    = Math.round(((startMin/60) - heureDebut) * pxParHeure) + 'px';
  el.style.height = Math.max(((endMin - startMin)/60) * pxParHeure, 16) + 'px';
  el.textContent  = minutesToTime(startMin) + ' – ' + minutesToTime(endMin);
}

function initDragCreate() {
  document.querySelectorAll('.week-col-body').forEach(col => {
    const heureDebut = parseInt(col.dataset.heureDebut);
    const pxParHeure = parseInt(col.dataset.pxParHeure);
    const date       = col.dataset.date;
    let hoverEl = null;

    col.addEventListener('mouseenter', () => {
      if (dragState) return;
      hoverEl = document.createElement('div');
      hoverEl.className = 'week-hover-bar';
      hoverEl.style.height = (pxParHeure / 2) + 'px';
      col.appendChild(hoverEl);
    });
    col.addEventListener('mousemove', e => {
      if (!hoverEl || dragState) return;
      if (e.target.closest('.week-card')) { hoverEl.style.display = 'none'; return; }
      hoverEl.style.display = '';
      const snapMin = Math.round((getRelativeY(e,col) / pxParHeure * 60 - 15) / 30) * 30 + heureDebut * 60;
      hoverEl.style.top = Math.round(((snapMin/60) - heureDebut) * pxParHeure) + 'px';
    });
    col.addEventListener('mouseleave', () => { if (hoverEl) { hoverEl.remove(); hoverEl = null; } });
    col.addEventListener('click', e => {
      if (e.target.closest('.week-card') || dragState) return;
      document.querySelectorAll('.week-selected-bar').forEach(el => el.remove());
      const snapMin = Math.round((getRelativeY(e,col) / pxParHeure * 60 - 15) / 30) * 30 + heureDebut * 60;
      const sel = document.createElement('div');
      sel.className = 'week-selected-bar';
      sel.style.top    = Math.round(((snapMin/60) - heureDebut) * pxParHeure) + 'px';
      sel.style.height = (pxParHeure / 2) + 'px';
      col.appendChild(sel);
    });
    col.addEventListener('mousedown', e => {
      if (e.target.closest('.week-card')) return;
      e.preventDefault();
      document.querySelectorAll('.week-selected-bar').forEach(el => el.remove());
      if (hoverEl) hoverEl.style.display = 'none';
      const startMin = yToMinutes(getRelativeY(e,col), heureDebut, pxParHeure);
      const el = document.createElement('div');
      el.className = 'drag-selection';
      col.appendChild(el);
      updateDragEl(el, startMin, startMin + 60, heureDebut, pxParHeure);
      dragState = { col, date, heureDebut, pxParHeure, startMin, endMin: startMin+60, el, fromTouch: false };
      col.classList.add('dragging');
    });
    col.addEventListener('touchstart', e => {
      if (e.target.closest('.week-card')) return;
      document.querySelectorAll('.week-selected-bar').forEach(el => el.remove());
      const startMin = yToMinutes(getRelativeY(e,col), heureDebut, pxParHeure);
      const el = document.createElement('div');
      el.className = 'drag-selection';
      col.appendChild(el);
      updateDragEl(el, startMin, startMin + 60, heureDebut, pxParHeure);
      dragState = { col, date, heureDebut, pxParHeure, startMin, endMin: startMin+60, el, fromTouch: true };
      col.classList.add('dragging');
    }, { passive: true });
  });

  // Délégation click sur week-card
  document.getElementById('viewWeek').addEventListener('click', e => {
    const card = e.target.closest('.week-card[data-session-url]');
    if (card) window.location = card.dataset.sessionUrl;
  });

  document.addEventListener('mousemove', e => {
    if (!dragState || dragState.fromTouch) return;
    dragState.endMin = Math.max(yToMinutes(getRelativeY(e,dragState.col), dragState.heureDebut, dragState.pxParHeure), dragState.startMin + 30);
    updateDragEl(dragState.el, dragState.startMin, dragState.endMin, dragState.heureDebut, dragState.pxParHeure);
  });
  document.addEventListener('mouseup', () => { if (!dragState || dragState.fromTouch) return; finalizeDrag(); });
  document.addEventListener('touchmove', e => {
    if (!dragState || !dragState.fromTouch) return;
    dragState.endMin = Math.max(yToMinutes(getRelativeY(e,dragState.col), dragState.heureDebut, dragState.pxParHeure), dragState.startMin + 30);
    updateDragEl(dragState.el, dragState.startMin, dragState.endMin, dragState.heureDebut, dragState.pxParHeure);
  }, { passive: true });
  document.addEventListener('touchend', () => { if (!dragState || !dragState.fromTouch) return; finalizeDrag(); });
}

function finalizeDrag() {
  if (!dragState) return;
  const { col, date, startMin, endMin, el } = dragState;
  el.remove();
  col.classList.remove('dragging');
  dragState = null;
  openNewSessionModal(date, minutesToTime(startMin), minutesToTime(endMin));
}

// ══ MODALE NOUVELLE SÉANCE ══
let _slotLocked = false;

function openNewSessionModal(date = null, timeStart = null, timeEnd = null) {
  clearWeekTransientUi();
  buildClassSelect();
  buildTimeSelects();
  document.getElementById('nsClass').value = '';
  document.getElementById('nsRoom').value  = '';
  document.getElementById('nsRoom').disabled = true;
  document.getElementById('nsPlan').value  = '';
  document.getElementById('nsPlan').disabled = true;
  document.getElementById('nsSubmitBtn').disabled = true;

  if (date && timeStart && timeEnd) {
    _slotLocked = true;
    document.getElementById('nsDate').value      = date;
    document.getElementById('nsTimeStart').value = timeStart;
    filterEndTimes();
    document.getElementById('nsTimeEnd').value   = timeEnd;
    const d = new Date(date + 'T00:00:00');
    const jours = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    const dateStr = jours[d.getDay()] + ' ' +
      String(d.getDate()).padStart(2,'0') + '/' +
      String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
    document.getElementById('nsSlotLabel').textContent = dateStr + '  ·  ' + timeStart + ' → ' + timeEnd;
    document.getElementById('nsSlotBanner').removeAttribute('hidden');
    document.getElementById('nsManualSlot').style.display = 'none';
  } else {
    _slotLocked = false;
    document.getElementById('nsSlotBanner').setAttribute('hidden', '');
    document.getElementById('nsManualSlot').style.display = '';
  }
  const until = new Date();
  until.setMonth(until.getMonth() + 3);
  document.getElementById('nsRecUntil').value = until.toISOString().slice(0,10);
  document.getElementById('newSessionModal').removeAttribute('hidden');
}

function nsUnlockSlot() {
  _slotLocked = false;
  document.getElementById('nsSlotBanner').setAttribute('hidden', '');
  document.getElementById('nsManualSlot').style.display = '';
}

function closeNewSessionModal() {
  document.getElementById('newSessionModal').setAttribute('hidden', '');
  document.getElementById('newSessionForm').reset();
  document.getElementById('nsSlotBanner').setAttribute('hidden', '');
  document.getElementById('nsManualSlot').style.display = '';
  _slotLocked = false;
  clearWeekTransientUi();
}

function buildTimeSelects() {
  const start = document.getElementById('nsTimeStart');
  const end   = document.getElementById('nsTimeEnd');
  start.innerHTML = '<option value="">— Début —</option>';
  end.innerHTML   = '<option value="">— Fin —</option>';
  TIME_SLOTS.forEach(t => {
    start.innerHTML += `<option value="${t}">${t}</option>`;
    end.innerHTML   += `<option value="${t}">${t}</option>`;
  });
  start.value = '08:00';
  filterEndTimes();
  document.getElementById('nsTimeEnd').value = '09:00';
  start.addEventListener('change', filterEndTimes);
}

function filterEndTimes() {
  const startVal = document.getElementById('nsTimeStart').value;
  const end = document.getElementById('nsTimeEnd');
  const prev = end.value;
  end.innerHTML = '<option value="">— Fin —</option>';
  TIME_SLOTS.filter(t => t > startVal).forEach(t => {
    end.innerHTML += `<option value="${t}">${t}</option>`;
  });
  if (prev && prev > startVal) end.value = prev;
}

function buildClassSelect() {
  const classes = [...new Map(PLANS.map(p => [p.class_id, p.class_name])).entries()];
  const sel = document.getElementById('nsClass');
  sel.innerHTML = '<option value="">— Choisir une classe —</option>';
  classes.sort((a,b) => a[1].localeCompare(b[1]))
         .forEach(([id, name]) => { sel.innerHTML += `<option value="${id}">${name}</option>`; });
}

document.getElementById('nsClass').addEventListener('change', function() {
  const classId = parseInt(this.value);
  const roomSel = document.getElementById('nsRoom');
  const planSel = document.getElementById('nsPlan');
  planSel.innerHTML = '<option value="">— Choisir d\'abord une salle —</option>';
  planSel.disabled = true;
  document.getElementById('nsSubmitBtn').disabled = true;
  if (!classId) {
    roomSel.innerHTML = '<option value="">— Choisir d\'abord une classe —</option>';
    roomSel.disabled = true;
    return;
  }
  const rooms = [...new Map(
    PLANS.filter(p => p.class_id == classId).map(p => [p.room_id, p.room_name])
  ).entries()];
  roomSel.innerHTML = '<option value="">— Choisir une salle —</option>';
  rooms.sort((a,b) => a[1].localeCompare(b[1]))
       .forEach(([id, name]) => { roomSel.innerHTML += `<option value="${id}">${name}</option>`; });
  roomSel.disabled = false;
});

document.getElementById('nsRoom').addEventListener('change', function() {
  const classId = parseInt(document.getElementById('nsClass').value);
  const roomId  = parseInt(this.value);
  const planSel = document.getElementById('nsPlan');
  document.getElementById('nsSubmitBtn').disabled = true;
  if (!roomId) {
    planSel.innerHTML = '<option value="">— Choisir d\'abord une salle —</option>';
    planSel.disabled = true;
    return;
  }
  const plans = PLANS.filter(p => p.class_id == classId && p.room_id == roomId);
  planSel.innerHTML = '<option value="">— Choisir une disposition —</option>';
  plans.sort((a,b) => a.name.localeCompare(b.name))
       .forEach(p => { planSel.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
  if (plans.length === 1) { planSel.value = plans[0].id; document.getElementById('nsSubmitBtn').disabled = false; }
  planSel.disabled = false;
});

document.getElementById('nsPlan').addEventListener('change', function() {
  document.getElementById('nsSubmitBtn').disabled = !this.value;
});

// ══ CRÉATION SÉANCE ══
document.getElementById('newSessionForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const recType = fd.get('recurrence_type') || 'none';
  let recurrence = null;
  if (recType === 'count') {
    recurrence = { type: 'count', count: parseInt(document.getElementById('nsRecCount').value) || 1 };
  } else if (recType === 'until') {
    const until = document.getElementById('nsRecUntil').value;
    if (until) recurrence = { type: 'until', until };
  }
  const payload = {
    plan_id:    parseInt(fd.get('plan_id'), 10),
    date:       fd.get('date'),
    time_start: fd.get('time_start') || null,
    time_end:   fd.get('time_end')   || null,
    subject:    fd.get('subject')    || null,
    recurrence,
  };
  const btn = document.getElementById('nsSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Création…';
  fetch('/api/sessions', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload),
  }).then(r => r.json()).then(d => {
    if (d.ok) {
      if (d.ids && d.ids.length > 1) { closeNewSessionModal(); location.reload(); }
      else { window.location = '/sessions/' + (d.id ?? d.ids[0]) + '/live'; }
    } else {
      alert('Erreur : ' + (d.error ?? JSON.stringify(d)));
      btn.disabled = false; btn.textContent = 'Créer la séance';
    }
  }).catch(() => { alert('Erreur réseau.'); btn.disabled = false; btn.textContent = 'Créer la séance'; });
});

// ══ SUPPRESSION SÉANCE ══
async function deleteSession(id) {
  const summary = await fetch('/api/sessions/' + id + '/observations-summary').then(r => r.json());
  if (summary.count === 0) {
    if (!confirm('Supprimer cette séance ? (aucune observation enregistrée)')) return;
    await doDeleteSession(id);
    return;
  }
  const byStudent = {};
  summary.rows.forEach(r => {
    const key = r.last_name + ' ' + r.first_name;
    if (!byStudent[key]) byStudent[key] = [];
    byStudent[key].push((r.icon ?? '') + ' ' + r.tag);
  });
  let html = `<p style="margin-bottom:.75rem"><strong>${summary.count} observation(s)</strong> seront définitivement supprimées&nbsp;:</p><ul style="margin:.5rem 0 1rem 1.25rem;line-height:2">`;
  for (const [student, tags] of Object.entries(byStudent)) {
    html += `<li><strong>${student}</strong>&nbsp;: ${tags.join(', ')}</li>`;
  }
  html += '</ul>';
  document.getElementById('deleteModalBody').innerHTML = html;
  const modal = document.getElementById('deleteModal');
  modal.dataset.sessionId = id;
  modal.removeAttribute('hidden');
}

async function doDeleteSession(id) {
  const d = await fetch('/api/sessions/' + id, {method:'DELETE'}).then(r => r.json());
  if (d.ok) location.reload();
  else alert('Erreur : ' + (d.error ?? JSON.stringify(d)));
}

// ══ EVENT LISTENERS (DOMContentLoaded) ══
document.addEventListener('DOMContentLoaded', () => {

  document.getElementById('btnOpenNewSession').addEventListener('click', () => openNewSessionModal());

  document.getElementById('btnCloseNewSession').addEventListener('click', closeNewSessionModal);
  document.getElementById('btnCancelNewSession').addEventListener('click', closeNewSessionModal);

  document.getElementById('btnNsUnlockSlot').addEventListener('click', nsUnlockSlot);

  document.addEventListener('click', e => {
    const btn = e.target.closest('.js-delete-session');
    if (btn) deleteSession(parseInt(btn.dataset.id));
  });

  document.getElementById('nsRecCount').addEventListener('click', () => {
    document.querySelector('[name=recurrence_type][value=count]').checked = true;
  });
  document.getElementById('nsRecUntil').addEventListener('click', () => {
    document.querySelector('[name=recurrence_type][value=until]').checked = true;
  });

  document.getElementById('btnDeleteConfirm').addEventListener('click', async () => {
    const id = document.getElementById('deleteModal').dataset.sessionId;
    document.getElementById('deleteModal').setAttribute('hidden', '');
    await doDeleteSession(id);
  });
  document.getElementById('btnDeleteCancel').addEventListener('click', () => {
    document.getElementById('deleteModal').setAttribute('hidden', '');
  });
  document.getElementById('btnDeleteSave').addEventListener('click', () => {
    const id = document.getElementById('deleteModal').dataset.sessionId;
    window.open('/api/sessions/' + id + '/observations-export', '_blank');
  });

  const params = new URLSearchParams(location.search);
  if (params.get('view') === 'week') setView('week');
});

// ══ IMPORT ICS ══
document.getElementById('icsForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Import en cours…';
  const data = await fetch('/api/sessions/import-ics', {
    method: 'POST', body: new FormData(this),
  }).then(r => r.json());
  const el = document.getElementById('icsResult');
  if (data.ok) {
    let html = `<span style="color:var(--color-success,green)">✅ ${data.inserted} séance(s) créée(s)${data.plans_created ? ` &middot; ${data.plans_created} plan(s) généré(s)` : ''} &middot; ${data.skipped} ignorée(s) (doublons)</span>`;
    if (data.errors?.length) {
      html += '<br><details><summary>&#9888;&#65039; ' + data.errors.length + ' avertissement(s)</summary>'
            + data.errors.map(e => `<div>&bull; ${e}</div>`).join('') + '</details>';
    }
    el.innerHTML = html;
    setTimeout(() => location.reload(), 2000);
  } else {
    el.innerHTML = `<span style="color:var(--color-error,red)">❌ ${data.error}</span>`;
  }
  btn.disabled = false; btn.textContent = 'Importer les séances';
});
