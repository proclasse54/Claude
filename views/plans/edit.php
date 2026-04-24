<?php
$pageTitle = 'Plan : ' . $plan['name'] . ' — ProClasse';

// Grille des sièges
$seatMap = [];
foreach ($seats as $s) { $seatMap[$s['row_index']][$s['col_index']] = $s; }

$grid = [];
for ($r = 0; $r < $room['rows']; $r++) {
    for ($c = 0; $c < $room['cols']; $c++) {
        $grid[$r][$c] = $seatMap[$r][$c] ?? null;
    }
}

// Index des affectations existantes : seat_id → student
$assignMap = [];
foreach ($assignments as $a) { $assignMap[$a['seat_id']] = $a; }

ob_start();
?>
<div class="page-header">
  <div>
    <a href="/classes/<?= $plan['class_id'] ?>" class="btn btn-ghost btn-sm">← Retour</a>
    <h1>Plan : <?= htmlspecialchars($plan['name']) ?></h1>
    <p class="text-muted">Glissez un élève sur un siège — ou cliquez sur un siège puis sur un élève</p>
  </div>
  <button class="btn btn-primary" onclick="savePlan()">Enregistrer le plan</button>
</div>

<div class="plan-layout">
  <!-- Grille salle -->
  <div class="plan-room-wrap">
    <div class="room-label-top">Tableau / Bureau</div>
    <div class="plan-room" id="planRoom" style="--room-cols: <?= $room['cols'] ?>">
      <?php foreach ($grid as $rowIdx => $cols): ?>
        <?php foreach ($cols as $colIdx => $seat): ?>
          <?php if ($seat === null): ?>
            <div class="plan-seat inactive"></div>
          <?php else: ?>
            <?php $assigned = $assignMap[$seat['id']] ?? null; ?>
            <div class="plan-seat <?= $assigned ? 'assigned' : 'free' ?>"
                data-seat-id="<?= $seat['id'] ?>">
              <?php if ($assigned): ?>
                <span class="plan-seat-name"><?= htmlspecialchars($assigned['first_name']) ?><br><small><?= htmlspecialchars($assigned['last_name']) ?></small></span>
              <?php else: ?>
                <span class="plan-seat-empty"><?= chr(65 + $rowIdx) . ($colIdx + 1) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Liste élèves -->
  <div class="plan-sidebar">
    <div class="plan-sidebar-header">
      <h3>Élèves</h3>
      <input type="search" id="studentSearch" placeholder="Rechercher…" oninput="filterStudents(this.value)">
    </div>
    <div class="plan-students" id="studentList">
      <?php foreach ($students as $st): ?>
      <div class="plan-student <?= isset($assignedStudents[$st['id']]) ? 'placed' : '' ?>"
          data-student-id="<?= $st['id'] ?>"
          data-first="<?= htmlspecialchars($st['first_name']) ?>"
          data-last="<?= htmlspecialchars($st['last_name']) ?>"
          data-name="<?= strtolower($st['last_name'] . ' ' . $st['first_name'])?>"
          draggable="true">
        <span class="student-initials"><?= mb_substr($st['first_name'],0,1) . mb_substr($st['last_name'],0,1) ?></span>
        <span class="student-fullname"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></span>
        <?php if (isset($assignedStudents[$st['id']])): ?>
        <span class="placed-badge">✓</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-ghost btn-sm" style="margin-top: auto" onclick="clearAll()">Tout effacer</button>
  </div>
</div>

<script>
const PLAN_ID   = <?= $plan['id'] ?>;
const ROOM_COLS = <?= $room['cols'] ?>;

// ─── État ────────────────────────────────────────────────────
let assignments = {};
<?php foreach ($assignments as $a): ?>
assignments[<?= $a['seat_id'] ?>] = <?= $a['student_id'] ?>;
<?php endforeach; ?>

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
    // On met à jour uniquement les classes et le contenu — SANS toucher aux listeners
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
  // Libérer l'ancien siège de cet élève
  if (studentSeat[studentId]) {
    const old = studentSeat[studentId];
    assignments[old] = null;
    renderSeat(old, null);
  }
  // Libérer l'élève qui était sur ce siège
  const prev = assignments[seatId];
  if (prev) { delete studentSeat[prev]; updateStudentEl(prev); }

  // Affecter
  assignments[seatId] = studentId;
  studentSeat[studentId] = seatId;
  renderSeat(seatId, studentId);
  updateStudentEl(studentId);

  // Désélectionner
  selectedSeatId = null;
  planRoom.querySelectorAll('.plan-seat').forEach(s => s.classList.remove('selected'));
}

// Clic siège (sélection)
function selectSeat(seatId) {
  planRoom.querySelectorAll('.plan-seat').forEach(s => s.classList.remove('selected'));
  const el = getSeatEl(seatId);
  if (el) el.classList.add('selected');
  selectedSeatId = seatId;
}

// Clic élève (affectation par clic)
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

// ─────────────────────────────────────────────────────────────
//  DRAG & DROP — principe clé :
//  Les listeners sont posés UNE SEULE FOIS sur les conteneurs
//  (délégation d'événements). On ne retouche jamais le DOM
//  des éléments individuels → zéro cloneNode, zéro doublon.
// ─────────────────────────────────────────────────────────────

// ─── Drag souris — SOURCE (élèves + sièges occupés) ──────────
// draggedStudentId : résolu dynamiquement à chaque dragstart
let draggedStudentId = null;

// Délégation sur la sidebar (élèves)
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

// Délégation sur la grille (sièges occupés → drag siège→siège)
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

// ─── Drag souris — CIBLE (sièges) ────────────────────────────
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

// ─── Clic sur les sièges (délégation) ────────────────────────
planRoom.addEventListener('click', e => {
  const seat = e.target.closest('.plan-seat:not(.inactive)');
  if (!seat) return;
  selectSeat(parseInt(seat.dataset.seatId));
});

// ─── Clic sur les élèves (délégation) ────────────────────────
document.getElementById('studentList').addEventListener('click', e => {
  const el = e.target.closest('.plan-student');
  if (!el) return;
  assignStudent(parseInt(el.dataset.studentId));
});

// ─────────────────────────────────────────────────────────────
//  DRAG TACTILE — Touch Events (tablette / iPad)
// ─────────────────────────────────────────────────────────────
let touchClone   = null;
let touchStudId  = null;
let touchOffX    = 0;
let touchOffY    = 0;

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
    borderRadius: 'var(--radius-lg)',
    transform: 'scale(1.05)', transition: 'none',
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
  // Surbrillance siège sous le doigt
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

// Délégation touch — sidebar (élèves)
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

// Délégation touch — grille (sièges occupés → siège→siège)
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

// Initialisation draggable des sièges déjà occupés au chargement
planRoom.querySelectorAll('.plan-seat.assigned').forEach(el => {
  el.draggable = true;
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
