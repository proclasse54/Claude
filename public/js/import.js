// ── Onglets ──────────────────────────────────────────────────────────────────
// Script injecté en bas de <body> via $content : DOM déjà construit.
// Corrige l’ancienne version (import-pane / pane-*) pour correspondre
// aux vrais ids du HTML : import-panel / tab-*

document.querySelectorAll('.import-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    // Désactive tous les onglets
    document.querySelectorAll('.import-tab').forEach(t => {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });
    // Masque tous les panneaux
    document.querySelectorAll('.import-panel').forEach(p => p.classList.remove('active'));
    // Active l’onglet et son panneau correspondant
    tab.classList.add('active');
    tab.setAttribute('aria-selected', 'true');
    document.getElementById('tab-' + tab.dataset.tab)?.classList.add('active');
  });
});

// ── Dropzone générique ─────────────────────────────────────────────────────

/**
 * Initialise une zone de dépôt de fichier.
 * @param {string} zoneId     - id de la div dropzone
 * @param {string} inputId    - id du <input type="file">
 * @param {string} filenameId - id du label de nom de fichier
 * @param {string} btnId      - id du bouton Submit à activer
 * @param {string} ext        - extension attendue sans point (ex: 'ics')
 */
function initDropzone(zoneId, inputId, filenameId, btnId, ext) {
  const zone  = document.getElementById(zoneId);
  const input = document.getElementById(inputId);
  const label = document.getElementById(filenameId);
  const btn   = document.getElementById(btnId);
  if (!zone || !input) return;

  // Clic sur la zone → ouvre le sélecteur de fichier natif
  zone.addEventListener('click', () => input.click());

  // Sélection via le sélecteur natif
  input.addEventListener('change', () => {
    if (input.files[0]) {
      label.textContent = '\uD83D\uDCCE ' + input.files[0].name;
      btn.disabled = false;
    }
  });

  // Glisser-déposer
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f && f.name.toLowerCase().endsWith('.' + ext)) {
      const dt = new DataTransfer();
      dt.items.add(f);
      input.files = dt.files;
      label.textContent = '\uD83D\uDCCE ' + f.name;
      btn.disabled = false;
    } else {
      label.textContent = '\u26A0\uFE0F Fichier invalide (.' + ext + ' requis)';
      label.style.color = 'var(--color-error)';
    }
  });
}

initDropzone('icsDropzone', 'icsFile', 'icsFilename', 'sessionsBtn', 'ics');
initDropzone('pdfDropzone', 'pdfFile', 'pdfFilename', 'photosBtn',   'pdf');

// ── Preview instantané lors du collage élèves ────────────────────────────

const studentsArea      = document.getElementById('studentsArea');
const studentsPasteZone = document.getElementById('studentsPasteZone');
const studentsPreview   = document.getElementById('studentsPreview');

/**
 * Analyse le texte TSV collé depuis Pronote et retourne un résumé.
 * @returns {{ totalStudents: number, classCounts: Object }|null}
 */
function parseStudentsPreview(text) {
  if (!text.trim()) return null;
  const lines = text.trim().split('\n').filter(l => l.trim());
  if (lines.length < 2) return null;

  // Détecter la colonne "Classe" dans l’en-tête
  const header    = lines[0].split('\t').map(h => h.trim().toLowerCase());
  const classeIdx = header.findIndex(h => h === 'classe' || h === 'class' || h === 'division');

  const classCounts = {};
  let totalStudents = 0;

  for (let i = 1; i < lines.length; i++) {
    const cols = lines[i].split('\t');
    if (cols.length < 2) continue;
    totalStudents++;
    if (classeIdx >= 0 && cols[classeIdx]) {
      const cls = cols[classeIdx].trim();
      if (cls) classCounts[cls] = (classCounts[cls] || 0) + 1;
    }
  }

  if (totalStudents === 0) return null;
  return { totalStudents, classCounts };
}

if (studentsArea) {
  studentsArea.addEventListener('input', () => {
    const val = studentsArea.value;
    // Basculer has-content pour l’aspect visuel de la zone
    if (val.trim()) {
      studentsPasteZone.classList.add('has-content');
    } else {
      studentsPasteZone.classList.remove('has-content');
      studentsPreview.hidden = true;
      return;
    }
    // Construire et afficher le résumé de pré-visualisation
    const parsed = parseStudentsPreview(val);
    if (!parsed) { studentsPreview.hidden = true; return; }

    const { totalStudents, classCounts } = parsed;
    const classNames = Object.keys(classCounts);

    let html = `\uD83D\uDC65 <strong>${totalStudents}</strong> élève${totalStudents > 1 ? 's' : ''} détecté${totalStudents > 1 ? 's' : ''}`;
    if (classNames.length > 0) {
      html += ` \u00B7 <strong>${classNames.length}</strong> classe${classNames.length > 1 ? 's' : ''} : `;
      html += classNames.sort().map(cls =>
        `<span style="display:inline-block;background:var(--color-primary-highlight);color:var(--color-primary);border-radius:var(--radius-full);padding:1px 8px;font-size:var(--text-xs);margin:1px 2px;">${cls} <em style="font-style:normal;opacity:.7">(${classCounts[cls]})</em></span>`
      ).join('');
    }
    studentsPreview.innerHTML = html;
    studentsPreview.hidden = false;
  });
}

// ── Import élèves (copier-coller) ─────────────────────────────────────────

document.getElementById('studentsForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('studentsBtn');
  const res = document.getElementById('studentsResult');
  const csv = studentsArea.value.trim();
  if (!csv) { showResult(res, 'error', 'Veuillez coller les données Pronote.'); return; }

  btn.disabled = true;
  btn.textContent = '\u23F3 Import en cours…';
  res.hidden = true;

  const data = await fetch('/api/classes/0/import-paste', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: csv })
  }).then(r => r.json()).catch(() => ({ error: 'Erreur réseau' }));

  if (data.ok) {
    let msg = `\u2705 <strong>${data.inserted}</strong> élève(s) importé(s)`;
    if (data.classes_created) msg += ` \u00B7 <strong>${data.classes_created}</strong> classe(s) créée(s)`;
    if (data.skipped)         msg += ` \u00B7 ${data.skipped} ignoré(s)`;
    showResult(res, 'success', msg);
    if (data.errors?.length) {
      res.innerHTML += `<details style="margin-top:.5rem">
        <summary>\u26A0\uFE0F ${data.errors.length} avertissement(s)</summary>
        ${data.errors.map(err => `<div class="import-error-line">\u2022 ${err}</div>`).join('')}
      </details>`;
    }
    // Vider la zone après import réussi
    studentsArea.value = '';
    studentsPasteZone.classList.remove('has-content');
    studentsPreview.hidden = true;
  } else {
    showResult(res, 'error', '\u274C ' + (data.error ?? 'Erreur inconnue'));
  }

  btn.disabled = false;
  btn.textContent = '\u2699\uFE0F Importer les élèves';
});

// ── Import séances (ICS) ─────────────────────────────────────────────────────

document.getElementById('sessionsForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('sessionsBtn');
  const res = document.getElementById('sessionsResult');
  btn.disabled = true;
  btn.textContent = '\u23F3 Import en cours…';
  res.hidden = true;

  const data = await fetch('/api/sessions/import-ics', {
    method: 'POST',
    body: new FormData(e.target)
  }).then(r => r.json()).catch(() => ({ error: 'Erreur réseau' }));

  if (data.ok) {
    let msg = `\u2705 <strong>${data.inserted}</strong> séance(s) créée(s)`;
    if (data.plans_created) msg += ` \u00B7 <strong>${data.plans_created}</strong> plan(s) généré(s)`;
    if (data.skipped)       msg += ` \u00B7 ${data.skipped} doublon(s) ignoré(s)`;
    showResult(res, 'success', msg);
    if (data.errors?.length) {
      res.innerHTML += `<details style="margin-top:.5rem">
        <summary>\u26A0\uFE0F ${data.errors.length} avertissement(s)</summary>
        ${data.errors.map(err => `<div class="import-error-line">\u2022 ${err}</div>`).join('')}
      </details>`;
    }
  } else {
    showResult(res, 'error', '\u274C ' + (data.error ?? 'Erreur inconnue'));
  }

  btn.disabled = false;
  btn.textContent = '\uD83D\uDCC5 Importer les séances';
});

// ── Import photos (PDF) ───────────────────────────────────────────────────────

document.getElementById('photosForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn      = document.getElementById('photosBtn');
  const res      = document.getElementById('photosResult');
  const progress = document.getElementById('photosProgress');
  const fill     = document.getElementById('photosProgressFill');
  const plabel   = document.getElementById('photosProgressLabel');

  btn.disabled = true;
  btn.textContent = '\u23F3 Extraction en cours…';
  res.hidden = true;
  progress.hidden = false;

  // Barre de progression simulée (le serveur ne streame pas)
  fill.style.width = '0%';
  let fakeProgress = 0;
  const ticker = setInterval(() => {
    fakeProgress = Math.min(fakeProgress + Math.random() * 8, 85);
    fill.style.width = fakeProgress + '%';
    plabel.textContent = 'Extraction en cours… ' + Math.round(fakeProgress) + '%';
  }, 400);

  const data = await fetch('/import/photos', {
    method: 'POST',
    body: new FormData(e.target)
  }).then(r => r.json()).catch(() => ({ error: 'Erreur réseau' }));

  clearInterval(ticker);
  fill.style.width = '100%';
  plabel.textContent = 'Terminé !';
  setTimeout(() => { progress.hidden = true; }, 800);

  if (data.ok) {
    let msg = `\u2705 <strong>${data.extracted}</strong> photo(s) extraite(s)`;
    if (data.unknown?.length) {
      msg += `<br>\u26A0\uFE0F <strong>${data.unknown.length}</strong> élève(s) non reconnu(s) :`;
      msg += `<ul style="margin:.3rem 0 0 1rem">${data.unknown.map(n => `<li>${n}</li>`).join('')}</ul>`;
    }
    showResult(res, data.unknown?.length ? 'warning' : 'success', msg);
  } else {
    showResult(res, 'error', '\u274C ' + (data.error ?? 'Erreur inconnue'));
  }

  btn.disabled = false;
  btn.textContent = '\uD83D\uDDBC\uFE0F Extraire les photos';
});

// ── Utilitaire affichage résultat ────────────────────────────────────────────────

/**
 * Affiche un message de résultat dans un élément.
 * @param {HTMLElement} el   - élément récepteur
 * @param {'success'|'error'|'warning'} type
 * @param {string} html      - contenu HTML du message
 */
function showResult(el, type, html) {
  el.hidden = false;
  el.className = 'import-result import-result--' + type;
  el.innerHTML = html;
}
