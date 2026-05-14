const _d = document.getElementById('planEditData');
const PLAN_ID   = parseInt(_d.dataset.planId);
const ROOM_COLS = parseInt(_d.dataset.roomCols);
let assignments = JSON.parse(document.getElementById('planAssignments').textContent);

let studentSeat = {};
Object.entries(assignments).forEach(([sid, stid]) => {
  if (stid) studentSeat[stid] = parseInt(sid);
});

let selectedSeatId = null;

// ─── Helpers DOM ─────────────────────────────────────────────
const planRoom = document.getElementById('planRoom');

function getSeatEl(seatId)    { return planRoom.querySelector(`[data-seat-id="${seatId}"]`); }
function getStudentEl(studId) { return document.querySelector(`[data-student-id="${studId}"]`); }

// ─── Rendu ───────────────────────────────────────────────────
function renderSeat(seatId, studentId) {
  const el = getSeatEl(seatId);
  if (!el) return;
  if (studentId) {
    const stEl = getStudentEl(studentId);
    const first = stEl?.dataset.first ?? '';
    const last  = stEl?.dataset.last  ?? '';
    el.className = 'plan-seat assigned';
    el.draggable = true;
    el.innerHTML = `<span class="plan-seat-name">${first}<br><small>${last}</small></span>`;
  } else {
    el.className = 'plan-seat free';
    el.draggable = false;
    el.innerHTML = `<span class="plan-seat-empty">—</span>`;
  }
}

function updateStudentEl(studentId) {
  const el = getStudentEl(studentId);
  if (!el) return;
  if (studentSeat[studentId]) {
    el.classList.add('placed');
    el.querySelector('.placed-badge') || el.insertAdjacentHTML('beforeend', '<span class="placed-badge">✓</span>');
  } else {
    el.classList.remove('placed');
    el.querySelector('.placed-badge')?.remove();
  }
}

// ─── Logique métier ──────────────────────────────────────────
function doAssign(studentId, seatId) {
  if (studentSeat[studentId]) {
    const old = studentSeat[studentId];
    assignments[old] = null;
    renderSeat(old, null);
  }
  const prev = assignments[seatId];
  if (prev) { delete studentSeat[prev]; updateStudentEl(prev); }

  assignments[seatId] = studentId;
  studentSeat[studentId] = seatId;
  renderSeat(seatId, studentId);
  updateStudentEl(studentId);

  selectedSeatId = null;
  planRoom.querySelectorAll('.plan-seat').forEach(s => s.classList.remove('selected'));
}

function selectSeat(seatId) {
  planRoom.querySelectorAll('.plan-seat').forEach(s => s.classList.remove('selected'));
  const el = getSeatEl(seatId);
  if (el) el.classList.add('selected');
  selectedSeatId = seatId;
}

function assignStudent(studentId) {
  if (!selectedSeatId) { alert('Cliquez d\'abord sur un siège.'); return; }
  doAssign(studentId, selectedSeatId);
}

function filterStudents(q) {
  const term = q.toLowerCase();
  document.querySelectorAll('.plan-student').forEach(el => {
    el.hidden = !el.dataset.name.includes(term);
  });
}

function clearAll() {
  if (!confirm('Effacer toutes les affectations ?')) return;
  Object.keys(assignments).forEach(sid => { assignments[sid] = null; renderSeat(sid, null); });
  studentSeat = {};
  document.querySelectorAll('.plan-student').forEach(el => {
    el.classList.remove('placed');
    el.querySelector('.placed-badge')?.remove();
  });
}

function savePlan() {
  const data = Object.entries(assignments)
    .filter(([, v]) => v !== null)
    .map(([seat_id, student_id]) => ({ seat_id: parseInt(seat_id), student_id }));

  fetch(`/api/plans/${PLAN_ID}/assignments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ assignments: data })
  })
  .then(r => r.json())
  .then(d => { if (d.ok) alert('Plan enregistré ✅'); });
}

// ─── Drag souris — SOURCE ─────────────────────────────────────
let draggedStudentId = null;

document.getElementById('studentList').addEventListener('dragstart', e => {
  const el = e.target.closest('.plan-student');
  if (!el) return;
  draggedStudentId = parseInt(el.dataset.studentId);
  el.classList.add('dragging');
  planRoom.classList.add('drag-active');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(draggedStudentId));
});
document.getElementById('studentList').addEventListener('dragend', e => {
  const el = e.target.closest('.plan-student');
  if (el) el.classList.remove('dragging');
  planRoom.classList.remove('drag-active');
  draggedStudentId = null;
});

planRoom.addEventListener('dragstart', e => {
  const el = e.target.closest('.plan-seat.assigned');
  if (!el) { e.preventDefault(); return; }
  const seatId = parseInt(el.dataset.seatId);
  draggedStudentId = assignments[seatId] ?? null;
  if (!draggedStudentId) { e.preventDefault(); return; }
  el.classList.add('dragging');
  planRoom.classList.add('drag-active');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(draggedStudentId));
});
planRoom.addEventListener('dragend', e => {
  const el = e.target.closest('.plan-seat');
  if (el) el.classList.remove('dragging');
  planRoom.classList.remove('drag-active');
  draggedStudentId = null;
});

// ─── Drag souris — CIBLE ──────────────────────────────────────
planRoom.addEventListener('dragover', e => {
  const seat = e.target.closest('.plan-seat:not(.inactive)');
  if (!seat) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  seat.classList.add('drag-over');
});
planRoom.addEventListener('dragleave', e => {
  const seat = e.target.closest('.plan-seat');
  if (seat && !seat.contains(e.relatedTarget)) seat.classList.remove('drag-over');
});
planRoom.addEventListener('drop', e => {
  const seat = e.target.closest('.plan-seat:not(.inactive)');
  if (!seat) return;
  e.preventDefault();
  seat.classList.remove('drag-over');
  planRoom.classList.remove('drag-active');
  const stuId = parseInt(e.dataTransfer.getData('text/plain'));
  const seatId = parseInt(seat.dataset.seatId);
  if (!isNaN(stuId) && !isNaN(seatId)) doAssign(stuId, seatId);
});

// ─── Clics ────────────────────────────────────────────────────
planRoom.addEventListener('click', e => {
  const seat = e.target.closest('.plan-seat:not(.inactive)');
  if (!seat) return;
  selectSeat(parseInt(seat.dataset.seatId));
});
document.getElementById('studentList').addEventListener('click', e => {
  const el = e.target.closest('.plan-student');
  if (!el) return;
  assignStudent(parseInt(el.dataset.studentId));
});

// ─── Drag tactile ─────────────────────────────────────────────
let touchClone = null, touchStudId = null, touchOffX = 0, touchOffY = 0;

function touchStart(e, studentId, sourceEl) {
  const t = e.touches[0];
  touchStudId = studentId;
  const rect = sourceEl.getBoundingClientRect();
  touchOffX = t.clientX - rect.left;
  touchOffY = t.clientY - rect.top;
  touchClone = sourceEl.cloneNode(true);
  Object.assign(touchClone.style, {
    position: 'fixed', left: rect.left + 'px', top: rect.top + 'px',
    width: rect.width + 'px', height: rect.height + 'px',
    opacity: '0.75', pointerEvents: 'none', zIndex: '9999',
    boxShadow: '0 8px 24px rgba(0,0,0,.25)',
    borderRadius: 'var(--radius-lg)', transform: 'scale(1.05)', transition: 'none',
  });
  document.body.appendChild(touchClone);
  sourceEl.classList.add('dragging');
  planRoom.classList.add('drag-active');
}

function touchMove(e) {
  if (!touchClone) return;
  e.preventDefault();
  const t = e.touches[0];
  touchClone.style.left = (t.clientX - touchOffX) + 'px';
  touchClone.style.top  = (t.clientY - touchOffY) + 'px';
  touchClone.style.display = 'none';
  planRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  const under = document.elementFromPoint(t.clientX, t.clientY)?.closest('.plan-seat:not(.inactive)');
  touchClone.style.display = '';
  if (under) under.classList.add('drag-over');
}

function touchEnd(e, sourceEl) {
  if (!touchClone) return;
  const t = e.changedTouches[0];
  touchClone.style.display = 'none';
  const target = document.elementFromPoint(t.clientX, t.clientY)?.closest('.plan-seat:not(.inactive)');
  touchClone.remove(); touchClone = null;
  sourceEl.classList.remove('dragging');
  planRoom.classList.remove('drag-active');
  planRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  if (target && touchStudId !== null) {
    const seatId = parseInt(target.dataset.seatId);
    if (!isNaN(seatId)) doAssign(touchStudId, seatId);
  }
  touchStudId = null;
}

document.getElementById('studentList').addEventListener('touchstart', e => {
  const el = e.target.closest('.plan-student');
  if (!el) return;
  touchStart(e, parseInt(el.dataset.studentId), el);
}, { passive: true });
document.getElementById('studentList').addEventListener('touchmove', e => {
  if (touchClone) touchMove(e);
}, { passive: false });
document.getElementById('studentList').addEventListener('touchend', e => {
  const el = e.target.closest('.plan-student') ?? document.querySelector('.plan-student.dragging');
  if (el) touchEnd(e, el);
});

planRoom.addEventListener('touchstart', e => {
  const seat = e.target.closest('.plan-seat.assigned');
  if (!seat) return;
  const seatId = parseInt(seat.dataset.seatId);
  const studId = assignments[seatId];
  if (!studId) return;
  touchStart(e, studId, seat);
}, { passive: true });
planRoom.addEventListener('touchmove', e => {
  if (touchClone) touchMove(e);
}, { passive: false });
planRoom.addEventListener('touchend', e => {
  const seat = e.target.closest('.plan-seat') ?? planRoom.querySelector('.plan-seat.dragging');
  if (seat) touchEnd(e, seat);
});
planRoom.addEventListener('touchcancel', e => {
  const seat = planRoom.querySelector('.plan-seat.dragging');
  if (touchClone) { touchClone.remove(); touchClone = null; }
  if (seat) seat.classList.remove('dragging');
  planRoom.classList.remove('drag-active');
  planRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  touchStudId = null;
});

// Initialisation draggable des sièges déjà occupés
planRoom.querySelectorAll('.plan-seat.assigned').forEach(el => { el.draggable = true; });
