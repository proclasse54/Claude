/**
 * live.js — Logique principale de la vue séance live.
 * Les données PHP sont injectées via data-attributes sur #liveData (voir live.php).
 */
const _ld = document.getElementById('liveData');
const SESSION_ID       = parseInt(_ld.dataset.sessionId);
const IS_PAST_SESSION  = _ld.dataset.isPast === 'true';   // séance passée → lecture seule
let currentStudentId   = null;
let currentStudentName = '';

const liveRoom = document.getElementById('liveRoom');
const tagsList = document.getElementById('tagsList');

/** Map seatId → studentId (null si vide) — construite à partir du DOM au chargement */
const seatStudentMap = {};
liveRoom.querySelectorAll('.live-seat[data-seat-id]').forEach(el => {
  seatStudentMap[parseInt(el.dataset.seatId)] = el.dataset.studentId ? parseInt(el.dataset.studentId) : null;
});

// --------------------------------------------------
// Helpers DOM siège
// --------------------------------------------------

/** Retourne l'élément siège par seatId */
function getSeatEl(seatId) {
  return liveRoom.querySelector(`[data-seat-id="${seatId}"]`);
}

/** Extrait les données d'un siège occupé pour un rollback éventuel */
function seatMarkupFromData(sourceEl) {
  return {
    html: sourceEl.innerHTML,
    studentId: sourceEl.dataset.studentId || '',
    studentName: sourceEl.dataset.studentName || '',
    occupied: sourceEl.classList.contains('occupied')
  };
}

/** Remplit un siège avec les données d'un élève */
function setSeatOccupied(el, payload) {
  el.innerHTML = payload.html;
  el.dataset.studentId = payload.studentId;
  el.dataset.studentName = payload.studentName;
  el.className = 'live-seat occupied';
  el.draggable = true;
}

/** Vide un siège */
function setSeatEmpty(el) {
  el.innerHTML = '<div class="seat-empty-label">&mdash;</div>';
  el.dataset.studentId = '';
  el.dataset.studentName = '';
  el.className = 'live-seat empty';
  el.draggable = false;
}

/** Désélectionne le siège courant et masque le panneau de tags */
function clearSelection() {
  liveRoom.querySelectorAll('.live-seat.selected').forEach(s => s.classList.remove('selected'));
  currentStudentId = null;
  currentStudentName = '';
  document.getElementById('selectedStudent').hidden = true;
  document.getElementById('selectedName').textContent = '';
}

/** Ouvre le menu de tags pour un élève */
function openTagMenu(seatId, studentId, name) {
  liveRoom.querySelectorAll('.live-seat.selected').forEach(s => s.classList.remove('selected'));
  const seatEl = getSeatEl(seatId);
  if (seatEl) seatEl.classList.add('selected');
  currentStudentId = studentId;
  currentStudentName = name;
  document.getElementById('selectedName').textContent = name;
  document.getElementById('selectedStudent').hidden = false;
}

// --------------------------------------------------
// SESSION EXPIRÉE : détection + toast
// --------------------------------------------------
let _sessionExpired = false;

/** Affiche le toast de session expirée et grise le plan de salle */
function showSessionExpiredToast() {
  if (_sessionExpired) return;
  _sessionExpired = true;
  document.getElementById('sessionExpiredToast').hidden = false;
  liveRoom.classList.add('session-expired');
}

/**
 * Wrapper fetch() sûr pour les API JSON de ProClasse.
 *
 * Détection session expirée :
 *  1. Statut 401 → toast (authentification refusée)
 *  2. Statut 403 → on tente de lire le JSON :
 *     - { expired: true }  → toast session expirée
 *     - { error: '...' }   → erreur métier, on relance avec le message
 *     - JSON illisible     → toast (probable page HTML de login)
 *  3. Statut non-2xx + body non parseable en JSON → toast
 *  4. Statut 2xx mais body non parseable → erreur technique normale
 */
async function apiFetch(url, options = {}) {
  let r;
  try {
    r = await fetch(url, options);
  } catch (networkErr) {
    throw networkErr; // perte réseau, pas une déconnexion
  }

  // 401 = session expirée certaine
  if (r.status === 401) {
    showSessionExpiredToast();
    throw new Error('Session expirée');
  }

  const contentType = r.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');

  let body = null;
  if (isJson) {
    try { body = await r.json(); } catch (_) { body = null; }
  } else {
    try { body = await r.text(); } catch (_) { body = null; }
  }

  // 403 = session expirée uniquement si le backend le dit explicitement
  if (r.status === 403) {
    if (isJson && body && body.expired === true) {
      showSessionExpiredToast();
      throw new Error('Session expirée');
    }
    throw new Error(isJson && body && body.error ? body.error : 'Action non autorisée');
  }

  // Autres erreurs HTTP
  if (!r.ok) {
    if (isJson && body && body.expired === true) {
      showSessionExpiredToast();
      throw new Error('Session expirée');
    }
    if (isJson && body && body.error) throw new Error(body.error);
    throw new Error(`Erreur HTTP ${r.status}`);
  }

  if (!isJson) throw new Error(`Réponse invalide du serveur (${r.status})`);
  if (body === null) throw new Error(`Réponse JSON invalide du serveur (${r.status})`);

  return body;
}

// --------------------------------------------------
// API Tags / Observations
// --------------------------------------------------

/** Enregistre une observation et ajoute le chip dans le DOM */
function selectTag(tag, icon = '', color = '#888') {
  if (!currentStudentId) return;

  apiFetch(`/api/sessions/${SESSION_ID}/observations`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ student_id: currentStudentId, tag })
  })
  .then(d => {
    if (d.ok) addTagChip(currentStudentId, d.obs_id, tag, color, icon);
  })
  .catch(() => {});
}

/** Crée et ajoute un chip de tag dans le DOM */
function addTagChip(studentId, obsId, tag, color = '#888', icon = '') {
  const container = document.getElementById('tags-' + studentId);
  if (!container) return;

  const span = document.createElement('span');
  span.className = 'tag-chip';
  span.style.background = color;
  span.title = 'Retirer';
  span.dataset.obsId = obsId;
  span.dataset.studentId = studentId;
  span.textContent = (icon ? icon + ' ' : '') + tag;
  container.appendChild(span);
}

/** Supprime une observation via l'API et retire le chip du DOM */
function removeObs(obsId, studentId, chipEl = null) {
  apiFetch(`/api/sessions/${SESSION_ID}/observations/${obsId}`, { method: 'DELETE' })
    .then(d => {
      if (!d.ok) return;
      if (chipEl) {
        chipEl.remove();
      } else {
        refreshTags(studentId);
      }
    })
    .catch(() => {});
}

/** Recharge les tags d'un élève depuis l'API et reconstruit les chips */
function refreshTags(studentId) {
  apiFetch(`/api/sessions/${SESSION_ID}/observations`)
    .then(obs => {
      const container = document.getElementById('tags-' + studentId);
      if (!container) return;

      const mine = obs.filter(o => o.student_id == studentId);
      container.innerHTML = mine.map(o =>
        `<span class="tag-chip"
              style="background:${o.color || '#888'}"
              data-obs-id="${o.id}"
              data-student-id="${studentId}"
              title="Retirer">${(o.icon ? o.icon + ' ' : '') + (o.tag || '')}</span>`
      ).join('');
    })
    .catch(() => {});
}

// --------------------------------------------------
// MODALE SCOPE (2 boutons : session / forward)
// --------------------------------------------------
const scopeModal   = document.getElementById('scopeModal');
const skippedModal = document.getElementById('skippedModal');
const scopeNameEl  = document.getElementById('scopeStudentName');
let _scopeResolve  = null;

/** Ouvre la modale scope avec le nom de l'élève et le libellé adapté (déplacement ou permutation) */
function scopeOpen(studentName, isSwap) {
  scopeNameEl.textContent = studentName;
  const subtitle = scopeModal.querySelector('.scope-modal-subtitle');
  if (subtitle) {
    subtitle.textContent = isSwap
      ? 'Cette permutation doit-elle affecter :'
      : 'Ce déplacement doit-il affecter :';
  }
  scopeModal.hidden = false;
  document.getElementById('scopeBtnSession').focus();
}

/** Ferme la modale scope */
function scopeClose() { scopeModal.hidden = true; }

/**
 * Ouvre la modale scope et retourne une Promise résolue avec
 * 'session' | 'forward' | null (annulation).
 */
function askScope(studentName, isSwap = false) {
  if (_sessionExpired) return Promise.resolve(null);
  return new Promise(resolve => {
    _scopeResolve = resolve;
    scopeOpen(studentName, isSwap);
  });
}

/** Résout la Promise de scope et ferme la modale */
function scopeResolve(value) {
  scopeClose();
  if (_scopeResolve) { _scopeResolve(value); _scopeResolve = null; }
}

document.getElementById('scopeBtnSession').addEventListener('click', () => scopeResolve('session'));
document.getElementById('scopeBtnForward').addEventListener('click', () => scopeResolve('forward'));
document.getElementById('scopeBtnCancel').addEventListener('click',  () => scopeResolve(null));
scopeModal.addEventListener('click', e => { if (e.target === scopeModal) scopeResolve(null); });

// Touche Escape : gère toutes les modales (scope, skipped, suppression)
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (!scopeModal.hidden)         { e.stopImmediatePropagation(); scopeResolve(null); }
    if (!skippedModal.hidden)       { e.stopImmediatePropagation(); skippedModal.hidden = true; }
    if (!deleteSessionModal.hidden) { e.stopImmediatePropagation(); deleteSessionModal.hidden = true; }
  }
});

// Modale avertissement séances ignorées lors d'une propagation
function showSkippedWarning(skipped) {
  const list = document.getElementById('skippedList');
  list.innerHTML = skipped.map(s => {
    const d = new Date(s.date);
    const dateStr = d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
    const timeStr = s.time ? ' ' + s.time.substring(0, 5) : '';
    return `<li><strong>${dateStr}${timeStr}</strong> — ${s.reason}</li>`;
  }).join('');
  skippedModal.hidden = false;
  document.getElementById('skippedClose').focus();
}
document.getElementById('skippedClose').addEventListener('click', () => { skippedModal.hidden = true; });
skippedModal.addEventListener('click', e => { if (e.target === skippedModal) skippedModal.hidden = true; });

// --------------------------------------------------
// API persistMove (scope: 'session' | 'forward')
// --------------------------------------------------
async function persistMove(studentId, sourceSeatId, targetSeatId, scope) {
  return apiFetch(`/api/sessions/${SESSION_ID}/move-seat`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({
      student_id:     studentId,
      source_seat_id: sourceSeatId,
      target_seat_id: targetSeatId,
      scope:          scope,
    }),
  });
}

// --------------------------------------------------
// moveSeat : swap ou déplacement vers place vide
// --------------------------------------------------
async function moveSeat(studentId, targetSeatId) {
  // Séance passée ou session expirée → aucun déplacement autorisé
  if (IS_PAST_SESSION || _sessionExpired) return;

  const sourceSeatId = parseInt(
    Object.keys(seatStudentMap).find(k => seatStudentMap[k] === studentId)
  );
  if (isNaN(sourceSeatId) || sourceSeatId === targetSeatId) return;

  const srcEl = getSeatEl(sourceSeatId);
  const tgtEl = getSeatEl(targetSeatId);
  if (!srcEl || !tgtEl) return;

  const targetStudentId = seatStudentMap[targetSeatId] != null
    ? parseInt(seatStudentMap[targetSeatId])
    : null;

  const isSwap  = targetStudentId !== null;
  const srcName = srcEl.dataset.studentName || 'l\'élève';
  const scope   = await askScope(srcName, isSwap);
  if (!scope) return;

  // Optimistic UI — applique immédiatement le changement dans le DOM
  const srcPayload = seatMarkupFromData(srcEl);
  const tgtPayload = seatMarkupFromData(tgtEl);
  setSeatOccupied(tgtEl, srcPayload);
  if (isSwap) setSeatOccupied(srcEl, tgtPayload); else setSeatEmpty(srcEl);
  seatStudentMap[targetSeatId] = studentId ? parseInt(studentId) : null;
  seatStudentMap[sourceSeatId] = isSwap ? parseInt(targetStudentId) : null;
  clearSelection();

  try {
    const result = await persistMove(studentId, sourceSeatId, targetSeatId, scope);
    if (!result.ok) throw new Error(result.error || 'Erreur inconnue');

    if (result.skipped_sessions && result.skipped_sessions.length > 0) {
      showSkippedWarning(result.skipped_sessions);
    }
  } catch (e) {
    if (_sessionExpired) return;
    // Rollback : annule les changements optimistes dans le DOM
    if (srcPayload.occupied) setSeatOccupied(srcEl, srcPayload); else setSeatEmpty(srcEl);
    if (tgtPayload.occupied) setSeatOccupied(tgtEl, tgtPayload); else setSeatEmpty(tgtEl);
    seatStudentMap[sourceSeatId] = srcPayload.studentId ? parseInt(srcPayload.studentId) : null;
    seatStudentMap[targetSeatId] = tgtPayload.studentId ? parseInt(tgtPayload.studentId) : null;
    alert('Déplacement non enregistré.\n\nDétail : ' + e.message);
  }
}

// --------------------------------------------------
// Événements UI — clics sur les sièges et les tags
// --------------------------------------------------

// Clic sur un siège occupé : ouvre le menu de tags
liveRoom.addEventListener('click', e => {
  if (e.target.closest('.tag-chip')) return;

  const seat = e.target.closest('.live-seat.occupied');
  if (!seat) return;

  if (seat._dragJustHappened) {
    seat._dragJustHappened = false;
    return;
  }

  openTagMenu(
    parseInt(seat.dataset.seatId),
    parseInt(seat.dataset.studentId),
    seat.dataset.studentName
  );
});

// Clic sur un chip de tag : supprime l'observation
liveRoom.addEventListener('click', e => {
  const chip = e.target.closest('.tag-chip');
  if (!chip) return;
  e.stopPropagation();
  removeObs(parseInt(chip.dataset.obsId), parseInt(chip.dataset.studentId), chip);
});

// Clic sur un bouton de tag : ajoute l'observation
tagsList.addEventListener('click', e => {
  const btn = e.target.closest('.tag-btn');
  if (!btn) return;
  selectTag(btn.dataset.tag, btn.dataset.icon, btn.dataset.color);
});

// --------------------------------------------------
// Drag & Drop souris — désactivé si séance passée
// --------------------------------------------------
let draggedStudentId = null;

liveRoom.addEventListener('dragstart', e => {
  if (IS_PAST_SESSION || _sessionExpired) { e.preventDefault(); return; }
  const seat = e.target.closest('.live-seat.occupied');
  if (!seat) { e.preventDefault(); return; }

  const studentId = parseInt(seat.dataset.studentId);
  if (!studentId) { e.preventDefault(); return; }

  draggedStudentId = studentId;
  seat.classList.add('dragging');
  liveRoom.classList.add('drag-active');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(studentId));
});

liveRoom.addEventListener('dragend', e => {
  const seat = e.target.closest('.live-seat');
  if (seat) seat.classList.remove('dragging');
  liveRoom.classList.remove('drag-active');
  liveRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  draggedStudentId = null;
});

liveRoom.addEventListener('dragover', e => {
  if (IS_PAST_SESSION || _sessionExpired) return;
  const seat = e.target.closest('.live-seat:not(.inactive)');
  if (!seat) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  seat.classList.add('drag-over');
});

liveRoom.addEventListener('dragleave', e => {
  const seat = e.target.closest('.live-seat');
  if (seat && !seat.contains(e.relatedTarget)) seat.classList.remove('drag-over');
});

liveRoom.addEventListener('drop', e => {
  if (IS_PAST_SESSION || _sessionExpired) return;
  const seat = e.target.closest('.live-seat:not(.inactive)');
  if (!seat) return;

  e.preventDefault();
  seat.classList.remove('drag-over');
  liveRoom.classList.remove('drag-active');

  const studentId    = parseInt(e.dataTransfer.getData('text/plain'));
  const targetSeatId = parseInt(seat.dataset.seatId);

  if (!isNaN(studentId) && !isNaN(targetSeatId)) {
    seat._dragJustHappened = true;
    moveSeat(studentId, targetSeatId);
  }
});

// --------------------------------------------------
// Drag & Drop tactile — désactivé si séance passée
// --------------------------------------------------
const DRAG_THRESHOLD = 8;
let touchClone  = null;
let touchStudId = null;
let touchSrcEl  = null;
let touchStartX = 0;
let touchStartY = 0;
let touchOffX   = 0;
let touchOffY   = 0;
let touchIsDrag = false;

liveRoom.addEventListener('touchstart', e => {
  if (IS_PAST_SESSION || _sessionExpired) return;
  if (e.target.closest('.tag-chip')) return;

  const seat = e.target.closest('.live-seat.occupied');
  if (!seat) return;

  const t = e.touches[0];
  touchStudId = parseInt(seat.dataset.studentId);
  touchSrcEl  = seat;
  touchStartX = t.clientX;
  touchStartY = t.clientY;
  touchIsDrag = false;

  const rect = seat.getBoundingClientRect();
  touchOffX = t.clientX - rect.left;
  touchOffY = t.clientY - rect.top;
}, { passive: true });

liveRoom.addEventListener('touchmove', e => {
  if (!touchSrcEl) return;

  const t = e.touches[0];
  const dx = t.clientX - touchStartX;
  const dy = t.clientY - touchStartY;

  if (!touchIsDrag && Math.hypot(dx, dy) < DRAG_THRESHOLD) return;

  if (!touchIsDrag) {
    touchIsDrag = true;
    liveRoom.classList.add('drag-active');
    touchSrcEl.classList.add('dragging');

    const rect = touchSrcEl.getBoundingClientRect();
    touchClone = touchSrcEl.cloneNode(true);
    Object.assign(touchClone.style, {
      position:     'fixed',
      left:         rect.left + 'px',
      top:          rect.top + 'px',
      width:        rect.width + 'px',
      height:       rect.height + 'px',
      opacity:      '0.75',
      pointerEvents: 'none',
      zIndex:       '9999',
      boxShadow:    '0 8px 24px rgba(0,0,0,.25)',
      borderRadius: 'var(--radius-lg)',
      transform:    'scale(1.05)',
      transition:   'none'
    });
    document.body.appendChild(touchClone);
  }

  e.preventDefault();
  touchClone.style.left = (t.clientX - touchOffX) + 'px';
  touchClone.style.top  = (t.clientY - touchOffY) + 'px';

  liveRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  touchClone.style.display = 'none';
  const under = document.elementFromPoint(t.clientX, t.clientY)?.closest('.live-seat:not(.inactive)');
  touchClone.style.display = '';
  if (under && under !== touchSrcEl) under.classList.add('drag-over');
}, { passive: false });

liveRoom.addEventListener('touchend', e => {
  if (!touchSrcEl) return;

  if (touchIsDrag && touchClone) {
    const t = e.changedTouches[0];
    touchClone.style.display = 'none';
    const target = document.elementFromPoint(t.clientX, t.clientY)?.closest('.live-seat:not(.inactive)');

    touchClone.remove();
    touchClone = null;
    touchSrcEl.classList.remove('dragging');
    liveRoom.classList.remove('drag-active');
    liveRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));

    if (!IS_PAST_SESSION && !_sessionExpired && target && target !== touchSrcEl && touchStudId !== null) {
      const targetSeatId = parseInt(target.dataset.seatId);
      if (!isNaN(targetSeatId)) {
        target._dragJustHappened = true;
        moveSeat(touchStudId, targetSeatId);
      }
    }
  }

  touchSrcEl  = null;
  touchStudId = null;
  touchIsDrag = false;
});

liveRoom.addEventListener('touchcancel', () => {
  if (touchClone) { touchClone.remove(); touchClone = null; }
  if (touchSrcEl) touchSrcEl.classList.remove('dragging');
  liveRoom.classList.remove('drag-active');
  liveRoom.querySelectorAll('.drag-over').forEach(s => s.classList.remove('drag-over'));
  touchSrcEl  = null;
  touchStudId = null;
  touchIsDrag = false;
});

// --------------------------------------------------
// SUPPRESSION DE LA SÉANCE
// --------------------------------------------------
const deleteSessionModal   = document.getElementById('deleteSessionModal');
const btnDeleteSession     = document.getElementById('btnDeleteSession');
const deleteSessionCancel  = document.getElementById('deleteSessionCancel');
const deleteSessionConfirm = document.getElementById('deleteSessionConfirm');

/** URL de retour après suppression — lue depuis le data-attribute #liveData */
const BACK_URL = _ld.dataset.backUrl;

btnDeleteSession.addEventListener('click', () => {
  deleteSessionModal.hidden = false;
  deleteSessionCancel.focus();
});

deleteSessionCancel.addEventListener('click', () => {
  deleteSessionModal.hidden = true;
});

deleteSessionModal.addEventListener('click', e => {
  if (e.target === deleteSessionModal) deleteSessionModal.hidden = true;
});

deleteSessionConfirm.addEventListener('click', () => {
  deleteSessionConfirm.disabled = true;
  deleteSessionConfirm.querySelector('.scope-btn-label').textContent = 'Suppression…';

  apiFetch(`/api/sessions/${SESSION_ID}`, { method: 'DELETE' })
    .then(d => {
      if (d.ok) {
        window.location.href = BACK_URL;
      } else {
        alert('Erreur : ' + (d.error || 'Suppression échouée'));
        deleteSessionConfirm.disabled = false;
        deleteSessionConfirm.querySelector('.scope-btn-label').textContent = 'Oui, supprimer définitivement';
      }
    })
    .catch(() => {
      deleteSessionConfirm.disabled = false;
      deleteSessionConfirm.querySelector('.scope-btn-label').textContent = 'Oui, supprimer définitivement';
    });
});

// Expose sur window pour live-crop.js
window.apiFetch       = apiFetch;
window.seatStudentMap = seatStudentMap;
window.SESSION_ID     = SESSION_ID;
