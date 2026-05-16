<?php
// views/import/index.php
// $pageTitle injecté par ImportController::index() via ob_start()
// Tout le JS a été externalisé dans public/js/import.js
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
  <!-- studentsForm est soumis via fetch() en JS → protégé par CORS, pas de CSRF HTML nécessaire -->
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

      <div class="import-paste-zone" id="studentsPasteZone">
        <div class="import-paste-hint" id="studentsPasteHint">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
          <span>Cliquez ici puis collez avec <kbd>Ctrl+V</kbd></span>
        </div>
        <textarea
          id="studentsArea"
          name="csv"
          class="import-textarea"
          placeholder="Collez ici le contenu copié depuis Pronote (Ctrl+V)…&#10;&#10;La première ligne doit contenir les en-têtes : Nom, Prénom, Classe…"
          spellcheck="false"
        ></textarea>
      </div>

      <div id="studentsPreview" class="import-preview" hidden></div>

      <div class="import-actions">
        <button type="submit" class="btn btn-primary" id="studentsBtn">
          ⚙️ Importer les élèves
        </button>
        <div id="studentsResult" class="import-result" hidden></div>
      </div>
    </form>
  </div>

  <!-- ══════════════════ PANNEAU SÉANCES ══════════════════ -->
  <!-- sessionsForm est soumis via fetch() en JS → protégé par CORS, pas de CSRF HTML nécessaire -->
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
      <?= Csrf::field() ?>
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

      <div class="import-progress" id="photosProgress" hidden>
        <div class="import-progress-bar">
          <div class="import-progress-fill" id="photosProgressFill"></div>
        </div>
        <div class="import-progress-label" id="photosProgressLabel">Extraction en cours…</div>
      </div>

      <div class="import-actions">
        <button type="submit" class="btn btn-primary" id="photosBtn" disabled>
          🖼️ Extraire les photos
        </button>
        <div id="photosResult" class="import-result" hidden></div>
      </div>
    </form>
  </div>

</div>

<style>
/* ── Zone de collage encadrée ──────────────────────────────────────────── */
.import-paste-zone {
  position: relative;
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-2);
  transition: border-color var(--transition-interactive), background var(--transition-interactive);
  min-height: 160px;
  cursor: text;
}
.import-paste-zone:focus-within,
.import-paste-zone.has-content {
  border-style: solid;
  border-color: var(--color-primary);
  background: var(--color-surface-2);
}
.import-paste-zone:not(.has-content):not(:focus-within):hover {
  border-color: var(--color-primary);
  background: var(--color-primary-highlight);
}

/* Hint centré visible quand vide */
.import-paste-hint {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  pointer-events: none;
  text-align: center;
  padding: 1rem;
  transition: opacity var(--transition-interactive);
}
.import-paste-zone.has-content .import-paste-hint {
  opacity: 0;
}

/* La textarea remplit toute la zone, fond transparent */
.import-paste-zone .import-textarea {
  position: relative;
  width: 100%;
  min-height: 160px;
  background: transparent;
  border: none;
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  resize: vertical;
  z-index: 1;
}
.import-paste-zone .import-textarea:focus {
  outline: none;
  box-shadow: none;
  border: none;
}
</style>

<script src="/js/import.js" nonce="<?= htmlspecialchars($cspNonce ?? '') ?>"></script>
