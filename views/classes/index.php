<?php
// views/classes/index.php
// $classes injecté par ClassController::index()
?>
<div class="page-header">
  <div>
    <h1>Classes</h1>
    <p class="text-muted">Gérez vos classes et importez les élèves depuis Pronote</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-secondary" onclick="openImportModal()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      Importer Pronote
    </button>
    <button class="btn btn-primary" onclick="openCreateClassModal()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nouvelle classe
    </button>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="empty-state">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
  <h3>Aucune classe</h3>
  <p>Importez depuis Pronote — les classes seront créées automatiquement.</p>
  <button class="btn btn-primary" onclick="openImportModal()">Importer depuis Pronote</button>
</div>
<?php else: ?>

<!-- Barre de sélection multiple -->
<div class="bulk-bar" id="bulkBar">
  <label class="checkbox-label">
    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"> Tout sélectionner
  </label>
  <span id="selectedCount" class="text-muted text-sm">0 sélectionnée(s)</span>
  <button class="btn btn-danger btn-sm" id="deleteSelectedBtn" onclick="deleteSelected()" disabled>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
    Supprimer la sélection
  </button>
  <button class="btn btn-danger btn-sm btn-outline" onclick="deleteAll()">Tout supprimer</button>
</div>

<div class="cards-grid" id="classesGrid">
  <?php foreach ($classes as $c): ?>
  <div class="card selectable" data-id="<?= $c['id'] ?>">
    <div class="card-select">
      <input type="checkbox" class="class-checkbox" value="<?= $c['id'] ?>" onchange="updateSelection()">
    </div>
    <div class="card-body">
      <div class="card-title"><?= htmlspecialchars($c['name']) ?></div>
      <div class="card-meta">
        <?= $c['student_count'] ?> élève<?= $c['student_count'] > 1 ? 's' : '' ?>
        <?= $c['year'] ? ' · ' . $c['year'] : '' ?>
      </div>
    </div>
    <div class="card-footer">
      <a href="/classes/<?= $c['id'] ?>" class="btn btn-sm btn-primary">Gérer</a>
      <button class="btn btn-sm btn-danger" onclick="deleteClass(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">Supprimer</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Modal import Pronote (global) ──────────────────────── -->
<div class="modal-overlay" id="importModal" hidden>
  <div class="modal modal-lg">
    <div class="modal-header">
      <h2>Importer depuis Pronote</h2>
      <button class="modal-close" onclick="closeModal('importModal')">&times;</button>
    </div>

    <div class="import-instructions">
      <ol>
        <li>Dans Pronote → <strong>Élèves → Liste des élèves</strong></li>
        <li>Sélectionnez toutes les lignes <kbd>Ctrl+A</kbd></li>
        <li>Copiez <kbd>Ctrl+C</kbd></li>
        <li>Collez ci-dessous <kbd>Ctrl+V</kbd></li>
      </ol>
      <p style="margin-top:.75rem;color:var(--text-muted);font-size:var(--text-xs)">
        Les classes sont créées automatiquement d'après le champ "Classe" de chaque élève.
      </p>
    </div>

    <div class="form-group">
      <label>Données copiées depuis Pronote</label>
      <textarea id="pronoteData" rows="10"
        placeholder="Collez ici les données (Ctrl+V)..."
        oninput="previewImport(this.value)"></textarea>
    </div>

    <div id="importPreview" class="import-preview" hidden></div>

    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('importModal')">Annuler</button>
      <button type="button" class="btn btn-primary" id="importBtn" onclick="doImport()" disabled>Importer</button>
    </div>
  </div>
</div>

<!-- ── Modal création manuelle ────────────────────────────── -->
<div class="modal-overlay" id="createClassModal" hidden>
  <div class="modal">
    <div class="modal-header">
      <h2>Nouvelle classe</h2>
      <button class="modal-close" onclick="closeModal('createClassModal')">&times;</button>
    </div>
    <form onsubmit="createClass(event)">
      <div class="form-group">
        <label>Nom de la classe</label>
        <input type="text" name="name" placeholder="ex: 3ème B" required>
      </div>
      <div class="form-group">
        <label>Année scolaire</label>
        <input type="text" name="year" placeholder="ex: 2025-2026">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createClassModal')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<script src="/js/classes-index.js" defer></script>
