<?php
// Lien CSS spécifique à la page séances
$extraCss = '/css/sessions.css';
?>

<div class="page-header">
  <h1>Séances</h1>
  <div class="page-header-actions">
    <button id="btnOpenNewSession" class="btn btn-primary">+ Nouvelle séance</button>
  </div>
</div>

<!-- Import ICS -->
<details class="ics-block">
  <summary>&#128197; Importer depuis Pronote (ICS)</summary>
  <p class="ics-hint">
    Exporte ton EDT depuis Pronote &rarr; <em>Mon EDT &rarr; Exporter &rarr; Calendrier (.ics)</em>, puis d&eacute;pose le fichier ici.
  </p>
  <form id="icsForm" class="ics-form">
    <label>Fichier <code>.ics</code>
      <input type="file" id="icsFile" name="icsfile" accept=".ics" required>
    </label>
    <button type="submit" class="btn btn-primary">Importer les s&eacute;ances</button>
  </form>
  <div id="icsResult" class="ics-result"></div>
</details>

<!-- ═══ TOGGLE VUE ═══ -->
<div class="view-toggle">
  <button id="btnList" class="btn btn-view active">☰ Liste</button>
  <button id="btnWeek" class="btn btn-view">&#128197; Semaine</button>
</div>

<!-- ═══ VUE LISTE ═══ -->
<div id="viewList">
  <?php if (empty($sessions)): ?>
    <p class="text-muted">Aucune séance &mdash; créez-en une ou importez votre EDT Pronote.</p>
  <?php else: ?>
    <p class="sessions-meta">
      <?= $total ?> séance(s) au total &mdash; page <?= $page ?>/<?= $totalPages ?>
    </p>
    <table class="table">
      <thead><tr>
        <th>Date</th><th>Heure</th><th>Classe</th><th>Mati&egrave;re</th><th>Plan / Salle</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['date']) ?></td>
          <td><?= $s['time_start'] ? substr($s['time_start'],0,5).' &ndash; '.substr($s['time_end'],0,5) : '&mdash;' ?></td>
          <td><?= htmlspecialchars($s['class_name']) ?></td>
          <td><?= htmlspecialchars($s['subject'] ?? '&mdash;') ?></td>
          <td>
            <?php if ($s['plan_id']): ?>
              <?= htmlspecialchars($s['plan_name'] ?? '') ?> (<?= htmlspecialchars($s['room_name'] ?? '') ?>)
            <?php else: ?>
              <em class="text-muted">Multi-classes</em>
            <?php endif ?>
          </td>
          <td style="white-space:nowrap;">
            <?php if ($s['plan_id']): ?>
              <a href="/sessions/<?= $s['id'] ?>/live" class="btn btn-sm btn-primary">Ouvrir</a>
            <?php else: ?>
              <span class="btn btn-sm session-btn-disabled" title="Séance informative, pas de plan de salle">—</span>
            <?php endif ?>
            <button class="btn btn-sm btn-danger js-delete-session" data-id="<?= $s['id'] ?>">Supprimer</button>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
    <div class="sessions-pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-sm">&larr; Pr&eacute;c&eacute;dent</a>
      <?php endif ?>
      <span>Page <?= $page ?>/<?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-sm">Suivant &rarr;</a>
      <?php endif ?>
    </div>
    <?php endif ?>
  <?php endif ?>
</div>

<!-- ═══ VUE SEMAINE ═══ -->
<div id="viewWeek" hidden>

  <?php
    $prevWeek = (clone $weekDate)->modify('-1 week')->format('o\\-\\WW');
    $nextWeek = (clone $weekDate)->modify('+1 week')->format('o\\-\\WW');
    $weekLabel = 'Semaine du '.(new \DateTime($weekStart))->format('d/m').' au '.(new \DateTime($weekEnd))->format('d/m/Y');
  ?>

  <div class="week-nav">
    <a href="?view=week&week=<?= $prevWeek ?>" class="btn btn-sm">&larr; Semaine pr&eacute;c.</a>
    <strong><?= $weekLabel ?></strong>
    <a href="?view=week&week=<?= $nextWeek ?>" class="btn btn-sm">Semaine suiv. &rarr;</a>
  </div>

  <?php
    $jours      = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
    $dates      = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = (new \DateTime($weekStart))->modify("+$i days")->format('Y-m-d');
    }
    $byDate = [];
    foreach ($weekSessions as $ws) {
        $byDate[$ws['date']][] = $ws;
    }
    $heureDebut = 8;
    $heureFin   = 18;
    foreach ($weekSessions as $_ws) {
        if ($_ws['time_start']) {
            $h = (int)substr($_ws['time_start'], 0, 2);
            if ($h < $heureDebut) $heureDebut = $h;
        }
        if ($_ws['time_end']) {
            $h = (int)substr($_ws['time_end'], 0, 2);
            $m = (int)substr($_ws['time_end'], 3, 2);
            $hFin = $m > 0 ? $h + 1 : $h;
            if ($hFin > $heureFin) $heureFin = $hFin;
        }
    }
    $pxParHeure   = 64;
    $hauteurTotal = ($heureFin - $heureDebut) * $pxParHeure;
    $currentWeekSlug = $weekDate->format('o-\\WW');
  ?>

  <p class="week-legend">
    💡 Cliquez (ou glissez) sur un créneau vide pour créer une séance.
  </p>

  <div class="week-agenda">
    <div class="week-axis">
      <div class="week-axis-header"></div>
      <div class="week-axis-body" style="height:<?= $hauteurTotal ?>px;">
        <?php for ($h = $heureDebut; $h <= $heureFin; $h++): ?>
        <div class="week-axis-label" style="top:<?= ($h - $heureDebut) * $pxParHeure ?>px;">
          <?= sprintf('%02d', $h) ?>:00
        </div>
        <?php endfor ?>
      </div>
    </div>
    <div class="week-grid">
      <?php foreach ($dates as $i => $d): ?>
      <div class="week-col">
        <div class="week-col-header">
          <?= $jours[$i] ?>
          <small><?= (new \DateTime($d))->format('d/m') ?></small>
        </div>
        <div class="week-col-body" style="height:<?= $hauteurTotal ?>px;"
             data-date="<?= $d ?>"
             data-heure-debut="<?= $heureDebut ?>"
             data-px-par-heure="<?= $pxParHeure ?>">
          <?php if (!empty($byDate[$d])): ?>
            <?php foreach ($byDate[$d] as $ws):
              if (!$ws['time_start'] || !$ws['time_end']) continue;
              [$h,  $m ] = array_map('intval', explode(':', substr($ws['time_start'], 0, 5)));
              [$h2, $m2] = array_map('intval', explode(':', substr($ws['time_end'],   0, 5)));
              $top    = (($h  + $m  / 60) - $heureDebut) * $pxParHeure;
              $height = (($h2 + $m2 / 60) - ($h + $m / 60)) * $pxParHeure - 2;
            ?>
            <div class="week-card <?= $ws['plan_id'] ? '' : 'week-card--multi week-card--no-plan' ?>"
                style="top:<?= round($top) ?>px;height:<?= round($height) ?>px;"
                <?php if ($ws['plan_id']): ?>data-session-url="/sessions/<?= $ws['id'] ?>/live?from_week=<?= $currentWeekSlug ?>"<?php endif ?>>
              <div class="week-card-header">
                <div class="week-card-class"><?= htmlspecialchars($ws['class_name'] ?? '') ?></div>
                <?php if ($ws['room_name']): ?>
                <div class="week-card-room"><?= htmlspecialchars($ws['room_name']) ?></div>
                <?php endif ?>
              </div>
              <?php if (!empty($ws['subject'])): ?>
              <div class="week-card-subject"><?= htmlspecialchars($ws['subject']) ?></div>
              <?php endif ?>
            </div>
            <?php endforeach ?>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- ═══ MODAL NOUVELLE SÉANCE ═══ -->
<div id="newSessionModal" class="modal-overlay" hidden>
  <div class="modal-box ns-modal-box">
    <div class="modal-header">
      <h2>Nouvelle séance</h2>
      <button class="modal-close" id="btnCloseNewSession" aria-label="Fermer">&times;</button>
    </div>

    <div id="nsSlotBanner" class="ns-slot-banner" hidden>
      <span class="ns-slot-banner-icon">&#128197;</span>
      <span id="nsSlotLabel" class="ns-slot-label"></span>
      <button type="button" id="btnNsUnlockSlot" class="ns-slot-unlock" title="Modifier manuellement">✏️ Modifier</button>
    </div>

    <form id="newSessionForm" class="ns-form">

      <div id="nsManualSlot" class="ns-manual-slot">
        <div class="ns-grid-2">
          <div class="form-group">
            <label class="form-label" for="nsDate">Date</label>
            <input class="form-input" type="date" id="nsDate" name="date" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="nsSubject">Matière <span class="ns-label-optional">(optionnel)</span></label>
            <input class="form-input" type="text" id="nsSubject" name="subject" placeholder="ex : Mathématiques">
          </div>
        </div>
        <div class="ns-grid-2">
          <div class="form-group">
            <label class="form-label" for="nsTimeStart">Heure de début</label>
            <select class="form-input" id="nsTimeStart" name="time_start"></select>
          </div>
          <div class="form-group">
            <label class="form-label" for="nsTimeEnd">Heure de fin</label>
            <select class="form-input" id="nsTimeEnd" name="time_end"></select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="nsClass">1. Classe</label>
        <select class="form-input" id="nsClass" required>
          <option value="">— Choisir une classe —</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="nsRoom">Salle</label>
        <select class="form-input" id="nsRoom" disabled required>
          <option value="">— Choisir d'abord une classe —</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="nsPlan">2. Disposition</label>
        <select class="form-input" id="nsPlan" name="plan_id" disabled required>
          <option value="">— Choisir d'abord une salle —</option>
        </select>
        <?php if (empty($plans)): ?>
          <p class="ns-plan-warning">&#9888;&#65039; Aucun plan configuré. Créez d'abord une salle, une classe, et assignez-les.</p>
        <?php endif ?>
      </div>

      <div class="form-group">
        <label class="form-label">3. Récurrence</label>
        <div class="ns-recurrence">
          <label class="ns-recurrence-row">
            <input type="radio" name="recurrence_type" value="none" checked> Une seule séance
          </label>
          <label class="ns-recurrence-row">
            <input type="radio" name="recurrence_type" value="count"> Répéter
            <input type="number" id="nsRecCount" min="2" max="52" value="10"
                   class="form-input ns-rec-count">
            fois (toutes les semaines)
          </label>
          <label class="ns-recurrence-row">
            <input type="radio" name="recurrence_type" value="until"> Jusqu'au
            <input type="date" id="nsRecUntil" class="form-input ns-rec-until">
          </label>
        </div>
      </div>

      <div class="ns-form-footer">
        <button type="button" id="btnCancelNewSession" class="btn">Annuler</button>
        <button type="submit" class="btn btn-primary" id="nsSubmitBtn" disabled>Créer la séance</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL SUPPRESSION SÉANCE ═══ -->
<div id="deleteModal" class="modal-overlay" hidden>
  <div class="modal-box delete-modal-box">
    <h2 class="delete-modal-title">&#9888;&#65039; Supprimer cette séance&nbsp;?</h2>
    <div id="deleteModalBody"></div>
    <div class="delete-modal-footer">
      <button id="btnDeleteCancel"  class="btn">Annuler</button>
      <button id="btnDeleteSave"    class="btn btn-warning">&#128190; Sauvegarder les obs. d'abord</button>
      <button id="btnDeleteConfirm" class="btn btn-danger">&#128465; Supprimer quand même</button>
    </div>
  </div>
</div>

<script>
const PLANS = <?= json_encode(array_values($plans), JSON_HEX_TAG) ?>;

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

  // Délégation click sur week-card (remplacement de onclick="window.location=...")
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

  // Bouton ouvrir modale
  document.getElementById('btnOpenNewSession').addEventListener('click', () => openNewSessionModal());

  // Fermeture modale (croix + bouton Annuler)
  document.getElementById('btnCloseNewSession').addEventListener('click', closeNewSessionModal);
  document.getElementById('btnCancelNewSession').addEventListener('click', closeNewSessionModal);

  // Déverrouiller le slot
  document.getElementById('btnNsUnlockSlot').addEventListener('click', nsUnlockSlot);

  // Délégation : boutons Supprimer dans le tableau
  document.addEventListener('click', e => {
    const btn = e.target.closest('.js-delete-session');
    if (btn) deleteSession(parseInt(btn.dataset.id));
  });

  // nsRecCount et nsRecUntil cochent automatiquement le bon radio
  document.getElementById('nsRecCount').addEventListener('click', () => {
    document.querySelector('[name=recurrence_type][value=count]').checked = true;
  });
  document.getElementById('nsRecUntil').addEventListener('click', () => {
    document.querySelector('[name=recurrence_type][value=until]').checked = true;
  });

  // Modale suppression
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

  // Vue initiale
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
</script>
