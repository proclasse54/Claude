/**
 * live-crop.js — Modale fiche élève + recadrage photo (crop interactif).
 * Dépend de live.js (window.apiFetch, window.seatStudentMap, SESSION_ID).
 */

// --------------------------------------------------
// Références DOM — modale fiche élève
// --------------------------------------------------
const studentModal    = document.getElementById('studentModal');
const modalClose      = document.getElementById('modalClose');
const modalAvatar     = document.getElementById('modalAvatar');
const modalName       = document.getElementById('modalStudentName');
const modalClass      = document.getElementById('modalClass');
const modalBody       = document.getElementById('modalBody');
const modalRemoveBtn  = document.getElementById('modalRemoveBtn');
const modalBilanBtn   = document.getElementById('modalBilanBtn');  // bouton 📊 Bilan
const modalPhotoPanel   = document.getElementById('modalPhotoPanel');
const modalPhotoInput   = document.getElementById('modalPhotoInput');
const modalPhotoPreview = document.getElementById('modalPhotoPreview');
const modalPhotoHint    = document.getElementById('modalPhotoHint');
const photoMainActions  = document.getElementById('photoMainActions');

// --------------------------------------------------
// CROP — état et références DOM
// --------------------------------------------------
const cropContainer = document.getElementById('cropContainer');
const cropArea      = document.getElementById('cropArea');
const cropCanvas    = document.getElementById('cropCanvas');
const cropSelection = document.getElementById('cropSelection');
const cropSaveBtn   = document.getElementById('cropSaveBtn');
const cropCancelBtn = document.getElementById('cropCancelBtn');

/** Image source chargée dans le canvas */
let _cropImage = null;  // HTMLImageElement
/** Facteur d'échelle affichage / taille réelle */
let _cropScale = 1;
/** Position + taille de la sélection en pixels canvas (coordonnées affichées) */
let _sel = { x: 0, y: 0, w: 0, h: 0 };
/** Mode d'interaction en cours : 'move' | 'tl' | 'tr' | 'bl' | 'br' | null */
let _dragMode  = null;
let _dragStart = { mx: 0, my: 0, sx: 0, sy: 0, sw: 0, sh: 0 };

/** Taille max du canvas affiché dans la modale */
const CANVAS_MAX_W = 420;
const CANVAS_MAX_H = 340;

/**
 * Charge une image (File) dans le canvas de crop et initialise la sélection.
 * Nouvelle photo uploadée : pas de crop existant en BDD — on place le carré par défaut.
 * @param {File} file
 */
function initCrop(file) {
  const reader = new FileReader();
  reader.onload = ev => {
    const img = new Image();
    img.onload = () => {
      // Nouvelle photo : pas de crop existant en BDD — sélection carré par défaut
      _loadImageIntoCrop(img, null);
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

/**
 * Charge la photo ORIGINALE (sans recadrage) d'un élève dans le canvas de crop.
 * Récupère simultanément le crop BDD pour pré-positionner le cadre sur le
 * recadrage déjà enregistré, permettant à l'utilisateur de le visualiser et
 * de l'ajuster sur l'image complète.
 * @param {number} studentId
 */
function startCropFromExistingPhoto(studentId) {
  modalPhotoHint.textContent = 'Chargement de la photo…';

  // Chargement en parallèle : image ORIGINALE + paramètres crop BDD
  const imgPromise = new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous'; // nécessaire pour toDataURL sur le canvas
    img.onload  = () => resolve(img);
    img.onerror = () => reject(new Error('Impossible de charger la photo existante.'));
    // On charge la photo ORIGINALE via ?original=1 pour afficher l'image complète
    // et que le cadre représente fidèlement la zone recadrée enregistrée en BDD.
    img.src = '/photo?student_id=' + studentId + '&original=1&t=' + Date.now();
  });

  const cropPromise = apiFetch('/api/students/' + studentId + '/photo-crop')
    .catch(() => null); // En cas d'erreur API, on continue sans crop pré-positionné

  Promise.all([imgPromise, cropPromise])
    .then(([img, cropData]) => {
      _loadImageIntoCrop(img, cropData);
    })
    .catch(err => {
      modalPhotoHint.textContent = err.message || 'Impossible de charger la photo existante.';
    });
}

/**
 * Logique commune : prend un HTMLImageElement déjà chargé,
 * l'affiche dans le canvas et initialise la sélection de crop.
 *
 * Si cropData est fourni (résultat de GET /api/students/{id}/photo-crop),
 * la sélection est pré-positionnée sur le recadrage existant.
 * Sinon, on place un carré centré de 80% de la dimension minimale.
 *
 * CORRECTIF POSITIONNEMENT : cropContainer est rendu visible AVANT d'appeler
 * renderSelection(), puis renderSelection() est différé via requestAnimationFrame
 * pour que le navigateur ait calculé le layout et que cropCanvas.offsetWidth
 * retourne les dimensions CSS réelles (et non 0).
 *
 * @param {HTMLImageElement} img
 * @param {object|null} cropData  { crop_x, crop_y, crop_w, crop_h } ou null
 */
function _loadImageIntoCrop(img, cropData) {
  _cropImage = img;

  // Calculer l'échelle pour faire tenir l'image dans CANVAS_MAX
  _cropScale = Math.min(1, CANVAS_MAX_W / img.naturalWidth, CANVAS_MAX_H / img.naturalHeight);
  const dw = Math.round(img.naturalWidth  * _cropScale);
  const dh = Math.round(img.naturalHeight * _cropScale);

  cropCanvas.width  = dw;
  cropCanvas.height = dh;

  // Dessiner l'image sur le canvas
  const ctx = cropCanvas.getContext('2d');
  ctx.clearRect(0, 0, dw, dh);
  ctx.drawImage(img, 0, 0, dw, dh);

  if (cropData && cropData.crop_x !== undefined) {
    // Pré-positionner la sélection sur le crop enregistré en BDD
    // Les coordonnées BDD sont proportionnelles (0→1), converties en pixels canvas
    _sel = {
      x: Math.round(cropData.crop_x * dw),
      y: Math.round(cropData.crop_y * dh),
      w: Math.round(cropData.crop_w * dw),
      h: Math.round(cropData.crop_h * dh),
    };
  } else {
    // Aucun crop enregistré : sélection initiale carré centré de 80% de la dimension minimale
    const side = Math.round(Math.min(dw, dh) * 0.8);
    _sel = {
      x: Math.round((dw - side) / 2),
      y: Math.round((dh - side) / 2),
      w: side,
      h: side
    };
  }

  // Afficher la zone de crop, masquer la prévisualisation et les boutons principaux.
  // IMPORTANT : rendre cropContainer visible AVANT renderSelection() pour que
  // le navigateur calcule les dimensions CSS réelles du canvas.
  modalPhotoPreview.style.display = 'none';
  photoMainActions.style.display  = 'none';
  cropContainer.style.display     = 'block';
  modalPhotoHint.textContent      = 'Déplacez et redimensionnez le cadre, puis cliquez sur « Recadrer & enregistrer ».';

  // Différer renderSelection() via requestAnimationFrame : le navigateur a ainsi
  // le temps de calculer le layout et cropCanvas.offsetWidth retourne la bonne valeur.
  requestAnimationFrame(() => renderSelection());
}

/**
 * Met à jour la position CSS de l'overlay de sélection.
 *
 * CORRECTIF : utilise cropCanvas.offsetWidth / offsetHeight (dimensions CSS réelles
 * de l'élément dans le DOM) au lieu de getBoundingClientRect() qui peut retourner
 * des valeurs incorrectes si le canvas n'est pas encore visible lors de l'appel initial.
 */
function renderSelection() {
  const cssW = cropCanvas.offsetWidth;
  const cssH = cropCanvas.offsetHeight;
  // Si le canvas n'est pas encore visible (offsetWidth === 0), on ne fait rien
  if (!cssW || !cssH) return;
  const scaleX = cssW / cropCanvas.width;
  const scaleY = cssH / cropCanvas.height;
  cropSelection.style.left   = (_sel.x * scaleX) + 'px';
  cropSelection.style.top    = (_sel.y * scaleY) + 'px';
  cropSelection.style.width  = (_sel.w * scaleX) + 'px';
  cropSelection.style.height = (_sel.h * scaleY) + 'px';
}

/** Clamp un nombre entre min et max */
function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

/**
 * Obtenir les coordonnées souris/tactile relatives au canvas.
 * Utilise getBoundingClientRect() ici (correct car appelé lors d'un événement
 * interactif — le canvas est donc visible et son layout est stable)
 */
function getPos(e) {
  const rect = cropCanvas.getBoundingClientRect();
  const src  = e.touches ? e.touches[0] : e;
  // Correction du ratio entre taille CSS affichée et taille canvas réelle
  const scaleX = cropCanvas.width  / rect.width;
  const scaleY = cropCanvas.height / rect.height;
  return {
    x: (src.clientX - rect.left) * scaleX,
    y: (src.clientY - rect.top)  * scaleY
  };
}

/** Détermine si le pointeur est dans la zone de sélection */
function inSelection(px, py) {
  return px >= _sel.x && px <= _sel.x + _sel.w
      && py >= _sel.y && py <= _sel.y + _sel.h;
}

/** Retourne le handle sous le pointeur, ou null si aucun */
function hitHandle(px, py) {
  const HIT = 14; // zone de clic en pixels canvas
  const corners = [
    { id: 'tl', cx: _sel.x,           cy: _sel.y           },
    { id: 'tr', cx: _sel.x + _sel.w,  cy: _sel.y           },
    { id: 'bl', cx: _sel.x,           cy: _sel.y + _sel.h  },
    { id: 'br', cx: _sel.x + _sel.w,  cy: _sel.y + _sel.h  },
  ];
  for (const c of corners) {
    if (Math.abs(px - c.cx) <= HIT && Math.abs(py - c.cy) <= HIT) return c.id;
  }
  return null;
}

// Démarrage interaction (souris)
cropArea.addEventListener('mousedown', e => {
  const pos = getPos(e);
  const handle = hitHandle(pos.x, pos.y);
  _dragMode  = handle || (inSelection(pos.x, pos.y) ? 'move' : null);
  _dragStart = { mx: pos.x, my: pos.y, sx: _sel.x, sy: _sel.y, sw: _sel.w, sh: _sel.h };
  if (_dragMode) e.preventDefault();
});

// Démarrage interaction (tactile)
cropArea.addEventListener('touchstart', e => {
  const pos = getPos(e);
  const handle = hitHandle(pos.x, pos.y);
  _dragMode  = handle || (inSelection(pos.x, pos.y) ? 'move' : null);
  _dragStart = { mx: pos.x, my: pos.y, sx: _sel.x, sy: _sel.y, sw: _sel.w, sh: _sel.h };
  if (_dragMode) e.preventDefault();
}, { passive: false });

/** Applique le déplacement ou redimensionnement pendant le drag */
function onCropMove(e) {
  if (!_dragMode) return;
  const pos = getPos(e);
  const dx  = pos.x - _dragStart.mx;
  const dy  = pos.y - _dragStart.my;
  const W   = cropCanvas.width;
  const H   = cropCanvas.height;
  const MIN = 20; // taille minimale de sélection en pixels canvas

  if (_dragMode === 'move') {
    _sel.x = clamp(_dragStart.sx + dx, 0, W - _sel.w);
    _sel.y = clamp(_dragStart.sy + dy, 0, H - _sel.h);
  } else {
    // Redimensionnement par une poignée de coin
    let { sx, sy, sw, sh } = _dragStart;
    if (_dragMode === 'tl') {
      const nx = clamp(sx + dx, 0, sx + sw - MIN);
      const ny = clamp(sy + dy, 0, sy + sh - MIN);
      _sel = { x: nx, y: ny, w: sx + sw - nx, h: sy + sh - ny };
    } else if (_dragMode === 'tr') {
      const ny = clamp(sy + dy, 0, sy + sh - MIN);
      _sel = { x: sx, y: ny, w: clamp(sw + dx, MIN, W - sx), h: sy + sh - ny };
    } else if (_dragMode === 'bl') {
      const nx = clamp(sx + dx, 0, sx + sw - MIN);
      _sel = { x: nx, y: sy, w: sx + sw - nx, h: clamp(sh + dy, MIN, H - sy) };
    } else if (_dragMode === 'br') {
      _sel = {
        x: sx, y: sy,
        w: clamp(sw + dx, MIN, W - sx),
        h: clamp(sh + dy, MIN, H - sy)
      };
    }
  }
  renderSelection();
}

document.addEventListener('mousemove',  onCropMove);
document.addEventListener('touchmove',  onCropMove, { passive: false });
document.addEventListener('mouseup',    () => { _dragMode = null; });
document.addEventListener('touchend',   () => { _dragMode = null; });

/**
 * Enregistre le crop actuel via l'API (coordonnées proportionnelles 0→1).
 * Appelé par le bouton « Recadrer & enregistrer ».
 */
cropSaveBtn.addEventListener('click', () => {
  if (!_openStudentId) return;

  const W = cropCanvas.width;
  const H = cropCanvas.height;

  // Convertir les coordonnées pixels canvas en proportions (0→1)
  const payload = {
    crop_x: _sel.x / W,
    crop_y: _sel.y / H,
    crop_w: _sel.w / W,
    crop_h: _sel.h / H,
  };

  cropSaveBtn.disabled    = true;
  cropSaveBtn.textContent = '⏳ Enregistrement…';

  apiFetch('/api/students/' + _openStudentId + '/photo-crop', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  })
  .then(d => {
    if (!d.ok) throw new Error(d.error || 'Erreur inconnue');
    modalPhotoHint.textContent = '✓ Recadrage enregistré.';
    // Rechargement de la vignette dans le plan de salle
    const seatPhoto = document.querySelector(`[data-student-id="${_openStudentId}"] .seat-photo`);
    if (seatPhoto) seatPhoto.src = '/photo?student_id=' + _openStudentId + '&t=' + Date.now();
    // Retour à la vue prévisualisation
    renderPhotoTab(_openStudentId);
  })
  .catch(err => {
    modalPhotoHint.textContent = 'Erreur : ' + err.message;
  })
  .finally(() => {
    cropSaveBtn.disabled    = false;
    cropSaveBtn.textContent = '✓ Recadrer & enregistrer';
  });
});

/** Annule le mode crop et retourne à la prévisualisation */
cropCancelBtn.addEventListener('click', () => {
  if (_openStudentId) renderPhotoTab(_openStudentId);
});

// --------------------------------------------------
// ONGLET PHOTO — prévisualisation + actions
// --------------------------------------------------

/** Identifiant de l'élève actuellement ouvert dans la modale */
let _openStudentId = null;

/**
 * Construit l'onglet photo : prévisualisation + boutons (Modifier / Supprimer / Upload).
 * Masque la zone de crop si elle était ouverte.
 * @param {number} studentId
 */
function renderPhotoTab(studentId) {
  // Masquer la zone crop, afficher la prévisualisation
  cropContainer.style.display    = 'none';
  modalPhotoPreview.style.display = '';
  photoMainActions.style.display  = '';
  modalPhotoHint.textContent = 'Formats : JPG, PNG, WEBP. Max 2 Mo.';

  // Charger la photo courante (recadrée) pour la prévisualisation
  const cacheBust = Date.now();
  modalPhotoPreview.innerHTML = `
    <img src="/photo?student_id=${studentId}&t=${cacheBust}"
         alt="Photo de l'élève"
         style="width:100px;height:100px;object-fit:cover;border-radius:var(--radius-md);"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div style="width:100px;height:100px;display:none;align-items:center;justify-content:center;
                background:var(--color-surface-offset);border-radius:var(--radius-md);
                color:var(--color-text-muted);font-size:var(--text-sm)">
      Pas de photo
    </div>`;

  // Boutons principaux : Modifier le recadrage + Changer la photo + Supprimer
  photoMainActions.innerHTML = `
    <button class="btn btn-ghost btn-sm" id="btnCropExisting">✂️ Modifier le recadrage</button>
    <button class="btn btn-ghost btn-sm" id="btnChangePhoto">📷 Changer la photo</button>
    <button class="btn btn-danger btn-sm" id="btnDeletePhoto">🗑 Supprimer la photo</button>`;

  document.getElementById('btnCropExisting').addEventListener('click', () => {
    startCropFromExistingPhoto(studentId);
  });

  document.getElementById('btnChangePhoto').addEventListener('click', () => {
    modalPhotoInput.click();
  });

  document.getElementById('btnDeletePhoto').addEventListener('click', () => {
    if (!confirm('Supprimer la photo de cet élève ?')) return;
    apiFetch('/api/students/' + studentId + '/photo', { method: 'DELETE' })
      .then(d => {
        if (!d.ok) throw new Error(d.error || 'Erreur');
        // Recharger la prévisualisation et la vignette dans le plan de salle
        renderPhotoTab(studentId);
        const seatPhoto = document.querySelector(`[data-student-id="${studentId}"] .seat-photo`);
        if (seatPhoto) seatPhoto.src = '/photo?student_id=' + studentId + '&t=' + Date.now();
      })
      .catch(err => { alert('Erreur : ' + err.message); });
  });
}

// Upload d'un nouveau fichier photo
modalPhotoInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    modalPhotoHint.textContent = '⚠ Fichier trop lourd (max 2 Mo).';
    return;
  }

  modalPhotoHint.textContent = 'Upload en cours…';

  const formData = new FormData();
  formData.append('photo', file);

  apiFetch('/api/students/' + _openStudentId + '/photo', {
    method: 'POST',
    body:   formData,
  })
  .then(d => {
    if (!d.ok) throw new Error(d.error || 'Upload échoué');
    modalPhotoHint.textContent = '✓ Photo uploadée. Vous pouvez maintenant recadrer.';
    // Passer directement en mode crop sur la nouvelle photo
    initCrop(file);
    // Mettre à jour la vignette dans le plan de salle
    const seatPhoto = document.querySelector(`[data-student-id="${_openStudentId}"] .seat-photo`);
    if (seatPhoto) seatPhoto.src = '/photo?student_id=' + _openStudentId + '&t=' + Date.now();
  })
  .catch(err => {
    modalPhotoHint.textContent = 'Erreur upload : ' + err.message;
  });

  // Réinitialiser l'input pour permettre de sélectionner le même fichier à nouveau
  e.target.value = '';
});

// --------------------------------------------------
// MODALE FICHE ÉLÈVE — ouverture / fermeture
// --------------------------------------------------

/**
 * Ouvre la modale fiche élève pour un studentId donné.
 * Charge les données via l'API et affiche l'onglet Données par défaut.
 * @param {number} studentId
 * @param {string} name
 * @param {string} className
 */
function openStudentModal(studentId, name, className) {
  _openStudentId = studentId;

  // Remplir l'en-tête
  modalAvatar.innerHTML = `<img src="/photo?student_id=${studentId}" alt="${name}"
    style="width:56px;height:56px;object-fit:cover;border-radius:var(--radius-full);"
    onerror="this.style.display='none'">`;
  modalName.textContent  = name;
  modalClass.textContent = className;

  // Lien bilan
  modalBilanBtn.href = '/students/' + studentId + '/bilan';

  // Onglet Données — loader initial
  modalBody.innerHTML = '<div class="student-modal-loading">Chargement&hellip;</div>';

  // Afficher la modale avant le fetch pour que l'utilisateur voit le loader
  activateTab('donnees');
  studentModal.hidden = false;
  modalClose.focus();

  // Charger les données élève
  apiFetch('/api/students/' + studentId)
    .then(d => {
      if (!d.ok) throw new Error(d.error || 'Données introuvables');
      const s = d.student;
      modalBody.innerHTML = `
        <div class="student-modal-row">
          <span class="student-modal-label">Prénom</span>
          <span>${s.first_name || '—'}</span>
        </div>
        <div class="student-modal-row">
          <span class="student-modal-label">Nom</span>
          <span>${s.last_name || '—'}</span>
        </div>
        <div class="student-modal-row">
          <span class="student-modal-label">Email</span>
          <span>${s.email ? `<a href="mailto:${s.email}">${s.email}</a>` : '—'}</span>
        </div>
        <div class="student-modal-row">
          <span class="student-modal-label">Notes</span>
          <span>${s.notes || '—'}</span>
        </div>`;
    })
    .catch(err => {
      modalBody.innerHTML = `<div class="student-modal-error">Erreur : ${err.message}</div>`;
    });
}

/** Ferme la modale fiche élève et nettoie l'état crop */
function closeStudentModal() {
  studentModal.hidden = true;
  _openStudentId      = null;
  _dragMode           = null;
  // Remettre à zéro le mode crop pour éviter un état incohérent à la prochaine ouverture
  cropContainer.style.display    = 'none';
  modalPhotoPreview.style.display = '';
  photoMainActions.style.display  = '';
  modalPhotoInput.value           = '';
}

modalClose.addEventListener('click', closeStudentModal);
studentModal.addEventListener('click', e => {
  if (e.target === studentModal) closeStudentModal();
});

// Touche Escape : ferme la modale fiche élève si elle est ouverte
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !studentModal.hidden) {
    e.stopImmediatePropagation();
    closeStudentModal();
  }
});

// --------------------------------------------------
// ONGLETS de la modale (Données / Photo)
// --------------------------------------------------

/** Active un onglet par son identifiant ('donnees' | 'photo') */
function activateTab(tabId) {
  document.querySelectorAll('.student-modal-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  modalBody.hidden         = tabId !== 'donnees';
  modalPhotoPanel.hidden   = tabId !== 'photo';

  // Initialiser l'onglet photo au premier affichage
  if (tabId === 'photo' && _openStudentId) renderPhotoTab(_openStudentId);
}

document.querySelectorAll('.student-modal-tab').forEach(btn => {
  btn.addEventListener('click', () => activateTab(btn.dataset.tab));
});

// --------------------------------------------------
// RETIRER un élève du plan de salle
// --------------------------------------------------
modalRemoveBtn.addEventListener('click', () => {
  if (!_openStudentId) return;

  // La route existante attend : DELETE /api/sessions/{id}/remove-student/{student_id}
  apiFetch(`/api/sessions/${SESSION_ID}/remove-student/${_openStudentId}`, {
    method: 'DELETE',
  })
  .then(d => {
    if (!d.ok) throw new Error(d.error || 'Erreur');

    // Vider le siège dans le DOM
    const seatId = parseInt(
      Object.keys(window.seatStudentMap).find(k => window.seatStudentMap[k] === _openStudentId)
    );
    if (!isNaN(seatId)) {
      const el = document.querySelector(`[data-seat-id="${seatId}"]`);
      if (el) {
        el.innerHTML = '<div class="seat-empty-label">&mdash;</div>';
        el.dataset.studentId   = '';
        el.dataset.studentName = '';
        el.className = 'live-seat empty';
        el.draggable = false;
      }
      window.seatStudentMap[seatId] = null;
    }
    closeStudentModal();
  })
  .catch(err => { alert('Erreur : ' + err.message); });
});

// --------------------------------------------------
// Ouverture par double-clic sur une vignette
// --------------------------------------------------
document.getElementById('liveRoom').addEventListener('dblclick', e => {
  const seat = e.target.closest('.live-seat.occupied');
  if (!seat) return;

  const studentId = parseInt(seat.dataset.studentId);
  const name      = seat.dataset.studentName || 'Élève';
  // La classe est disponible dans l'en-tête de la séance
  const className = document.querySelector('.live-identity-class')?.textContent || '';

  openStudentModal(studentId, name, className);
});
