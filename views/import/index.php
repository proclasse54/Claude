<?php
// views/import/index.php
$pageTitle = 'Importer depuis Pronote';
ob_start();
?>

<div class="import-page">

  <div class="import-header">
    <h1 class="import-title">📥 Importer depuis Pronote</h1>
    <p class="import-subtitle">Suivez les 3 étapes dans l'ordre recommandé : élèves d'abord, puis séances, puis photos.</p>
  </div>

  <!-- Onglets -->
  <div class="import-tabs" role="tablist">
    <button class="import-tab active" role="tab" aria-selected="true"  data-tab="students">
      <span class="import-tab-icon">👥</span>
      <span class="import-tab-label">Élèves</span>
      <span class="import-tab-step">Étape 1</span>
    </button>
    <button class="import-tab" role="tab" aria-selected="false" data-tab="sessions">
      <span class="import-tab-icon">📅</span>
      <span class="import-tab-label">Séances</span>
      <span class="import-tab-step">Étape 2</span>
    </button>
    <button class="import-tab" role="tab" aria-selected="false" data-tab="photos">
      <span class="import-tab-icon">🖼</span>
      <span class="import-tab-label">Photos</span>
      <span class="import-tab-step">Étape 3</span>
    </button>
  </div>

  <!-- ══════════════════ PANNEAU ÉLÈVES ══════════════════ -->
  <div class="import-panel active" id="tab-students">

    <div class="import-how">
      <div class="import-how-title">Comment exporter depuis Pronote ?</div>
      <ol class="import-how-steps">
        <li>Ouvrez Pronote → <strong>Élèves</strong></li>
        <li>Sélectionnez une ou plusieurs classes</li>
        <li>Clic droit → <strong>Exporter la liste</strong> (ou Ctrl+C sur le tableau)</li>
        <li>Collez directement ci-dessous</li>
      </ol>
    </div>

    <form id="studentsForm" class="import-form">
      <label class="import-label" for="studentsArea">
        Données élèves <span class="import-label-hint">(coller le tableau Pronote)</span>
      </label>
      <textarea
        id="studentsArea"
        name="csv"
        class="import-textarea"
        placeholder="Collez ici le contenu copié depuis Pronote (Ctrl+V)…&#10;&#10;La première ligne doit contenir les en-têtes : Nom, Prénom, Classe…"
        spellcheck="false"
      ></textarea>

      <div class="import-actions">
        <button type="submit" class="btn btn-primary" id="studentsBtn">
          ⚙️ Importer les élèves
        </button>
        <div id="studentsResult" class="import-result" hidden></div>
      </div>
    </form>
  </div>

  <!-- ══════════════════ PANNEAU SÉANCES ══════════════════ -->
  <div class="import-panel" id="tab-sessions">

    <div class="import-how">
      <div class="import-how-title">Comment exporter depuis Pronote ?</div>
      <ol class="import-how-steps">
        <li>Ouvrez Pronote → <strong>Mon EDT</strong></li>
        <li>Cliquez sur <strong>Exporter</strong></li>
        <li>Choisissez <strong>Calendrier (.ics)</strong></li>
        <li>Déposez le fichier ci-dessous</li>
      </ol>
    </div>

    <form id="sessionsForm" class="import-form">
      <label class="import-label">
        Fichier <code>.ics</code> Pronote
      </label>
      <div class="import-dropzone" id="icsDropzone">
        <div class="import-dropzone-icon">📅</div>
        <div class="import-dropzone-text">
          Glissez votre fichier <strong>.ics</strong> ici<br>
          <span class="import-dropzone-hint">ou cliquez pour choisir</span>
        </div>
        <input type="file" name="icsfile" id="icsFile" accept=".ics" class="import-dropzone-input">
        <div class="import-dropzone-filename" id="icsFilename"></div>
      </div>

      <div class="import-actions">
        <button type="submit" class="btn btn-primary" id="sessionsBtn" disabled>
          📅 Importer les séances
        </button>
        <div id="sessionsResult" class="import-result" hidden></div>
      </div>
    </form>
  </div>

  <!-- ══════════════════ PANNEAU PHOTOS ══════════════════ -->
  <div class="import-panel" id="tab-photos">

    <div class="import-how">
      <div class="import-how-title">Comment exporter depuis Pronote ?</div>
      <ol class="import-how-steps">
        <li>Ouvrez Pronote → <strong>Trombinoscope</strong></li>
        <li>Sélectionnez la ou les classes</li>
        <li>Cliquez sur <strong>Imprimer</strong> → <strong>Exporter en PDF</strong></li>
        <li>Déposez le fichier ci-dessous</li>
      </ol>
    </div>

    <form id="photosForm" class="import-form" enctype="multipart/form-data"
          action="/import/photos" method="post">
      <label class="import-label">
        Fichier <code>.pdf</code> trombinoscope
      </label>
      <div class="import-dropzone" id="pdfDropzone">
        <div class="import-dropzone-icon">🖼</div>
        <div class="import-dropzone-text">
          Glissez votre fichier <strong>.pdf</strong> ici<br>
          <span class="import-dropzone-hint">ou cliquez pour choisir</span>
        </div>
        <input type="file" name="pdf" id="pdfFile" accept=".pdf" class="import-dropzone-input">
        <div class="import-dropzone-filename" id="pdfFilename"></div>
      </div>

      <!-- Barre de progression -->
      <div class="import-progress" id="photosProgress" hidden>
        <div class="import-progress-bar">
          <div class="import-progress-fill" id="photosProgressFill"></div>
        </div>
        <div class="import-progress-label" id="photosProgressLabel">Extraction en cours…</div>
      </div>

      <div class="import-actions">
        <button type="submit" class="btn btn-primary" id="photosBtn" disabled>
          🖼 Extraire les photos
        </button>
        <div id="photosResult" class="import-result" hidden></div>
      </div>
    </form>
  </div>

</div>

<script>
// ── Onglets ───────────────────────────────────────────────
document.querySelectorAll('.import-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.import-tab').forEach(t => {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.import-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    tab.setAttribute('aria-selected', 'true');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});

// ── Dropzone générique ────────────────────────────────────
function initDropzone(zoneId, inputId, filenameId, btnId, ext) {
  const zone  = document.getElementById(zoneId);
  const input = document.getElementById(inputId);
  const label = document.getElementById(filenameId);
  const btn   = document.getElementById(btnId);

  zone.addEventListener('click', () => input.click());
  input.addEventListener('change', () => {
    if (input.files[0]) {
      label.textContent = '📎 ' + input.files[0].name;
      btn.disabled = false;
    }
  });
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f && f.name.toLowerCase().endsWith('.' + ext)) {
      const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
      label.textContent = '📎 ' + f.name;
      btn.disabled = false;
    } else {
      label.textContent = '⚠️ Fichier invalide (.' + ext + ' requis)';
      label.style.color = 'var(--color-error)';
    }
  });
}

initDropzone('icsDropzone', 'icsFile', 'icsFilename', 'sessionsBtn', 'ics');
initDropzone('pdfDropzone', 'pdfFile', 'pdfFilename', 'photosBtn',   'pdf');

// ── Import élèves (copier-coller) ─────────────────────────
document.getElementById('studentsForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('studentsBtn');
  const res = document.getElementById('studentsResult');
  const csv = document.getElementById('studentsArea').value.trim();
  if (!csv) { showResult(res, 'error', 'Veuillez coller les données Pronote.'); return; }

  btn.disabled = true; btn.textContent = '⏳ Import en cours…';
  res.hidden = true;

  // ✅ Même appel que show.php : JSON + route /import-paste sans class_id
  const data = await fetch('/api/classes/0/import-paste', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: csv })
  }).then(r => r.json()).catch(() => ({ error: 'Erreur réseau' }));

  if (data.ok) {
    let msg = `✅ <strong>${data.inserted}</strong> élève(s) importé(s)`;
    if (data.classes_created) msg += ` · <strong>${data.classes_created}</strong> classe(s) créée(s)`;
    if (data.skipped)         msg += ` · ${data.skipped} ignoré(s)`;
    showResult(res, 'success', msg);
    if (data.errors?.length) {
      res.innerHTML += `<details style="margin-top:.5rem">
        <summary>⚠️ ${data.errors.length} avertissement(s)</summary>
        ${data.errors.map(e => `<div class="import-error-line">• ${e}</div>`).join('')}
      </details>`;
    }
  } else {
    showResult(res, 'error', '❌ ' + (data.error ?? 'Erreur inconnue'));
  }
  btn.disabled = false; btn.textContent = '⚙️ Importer les élèves';
});

// ── Import séances (ICS) ──────────────────────────────────
document.getElementById('sessionsForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('sessionsBtn');
  const res = document.getElementById('sessionsResult');
  btn.disabled = true; btn.textContent = '⏳ Import en cours…';
  res.hidden = true;

  const data = await fetch('/api/sessions/import-ics', {
    method:'POST', body: new FormData(e.target)
  }).then(r => r.json()).catch(() => ({error:'Erreur réseau'}));

  if (data.ok) {
    let msg = `✅ <strong>${data.inserted}</strong> séance(s) créée(s)`;
    if (data.plans_created) msg += ` · <strong>${data.plans_created}</strong> plan(s) généré(s)`;
    if (data.skipped)       msg += ` · ${data.skipped} doublon(s) ignoré(s)`;
    showResult(res, 'success', msg);
    if (data.errors?.length) {
      res.innerHTML += `<details style="margin-top:.5rem"><summary>⚠️ ${data.errors.length} avertissement(s)</summary>
        ${data.errors.map(e => `<div class="import-error-line">• ${e}</div>`).join('')}</details>`;
    }
  } else {
    showResult(res, 'error', '❌ ' + (data.error ?? 'Erreur inconnue'));
  }
  btn.disabled = false; btn.textContent = '📅 Importer les séances';
});

// ── Import photos (PDF) ───────────────────────────────────
document.getElementById('photosForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn      = document.getElementById('photosBtn');
  const res      = document.getElementById('photosResult');
  const progress = document.getElementById('photosProgress');
  const fill     = document.getElementById('photosProgressFill');
  const plabel   = document.getElementById('photosProgressLabel');

  btn.disabled = true; btn.textContent = '⏳ Extraction en cours…';
  res.hidden = true;
  progress.hidden = false;

  // Animation indéterminée pendant l'upload
  fill.style.width = '0%';
  let fakeProgress = 0;
  const ticker = setInterval(() => {
    fakeProgress = Math.min(fakeProgress + Math.random() * 8, 85);
    fill.style.width = fakeProgress + '%';
    plabel.textContent = 'Extraction en cours… ' + Math.round(fakeProgress) + '%';
  }, 400);

  const data = await fetch('/import/photos', {
    method:'POST', body: new FormData(e.target)
  }).then(r => r.json()).catch(() => ({error:'Erreur réseau'}));

  clearInterval(ticker);
  fill.style.width = '100%';
  plabel.textContent = 'Terminé !';
  setTimeout(() => { progress.hidden = true; }, 800);

  if (data.ok) {
    let msg = `✅ <strong>${data.extracted}</strong> photo(s) extraite(s)`;
    if (data.unknown?.length) {
      msg += `<br>⚠️ <strong>${data.unknown.length}</strong> élève(s) non reconnu(s) :`;
      msg += `<ul style="margin:.3rem 0 0 1rem">${data.unknown.map(n=>`<li>${n}</li>`).join('')}</ul>`;
    }
    showResult(res, data.unknown?.length ? 'warning' : 'success', msg);
  } else {
    showResult(res, 'error', '❌ ' + (data.error ?? 'Erreur inconnue'));
  }
  btn.disabled = false; btn.textContent = '🖼 Extraire les photos';
});

function showResult(el, type, html) {
  el.hidden = false;
  el.className = 'import-result import-result--' + type;
  el.innerHTML = html;
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>