<?php
// views/classes/show.php
// $class, $students, $plans, $groups, $rooms injectés par ClassController::show()
?>
<!-- Conteneur portant le CLASS_ID injecté en data-attribute pour classes-show.js -->
<div id="classShowData" data-class-id="<?= $class['id'] ?>" hidden></div>

<div class="page-header">
  <div>
    <a href="/classes" class="btn btn-ghost btn-sm">← Retour</a>
    <h1><?= htmlspecialchars($class['name']) ?><?= $class['year'] ? ' <small>'.$class['year'].'</small>' : '' ?></h1>
  </div>
</div>

<!-- data-tab sur chaque bouton — showTab() est appelé via délégation dans classes-show.js -->
<div class="tabs">
  <button class="tab active" data-tab="students">Élèves (<?= count($students) ?>)</button>
  <button class="tab" data-tab="plans">Plans de salle (<?= count($plans) ?>)</button>
  <button class="tab" data-tab="groups">Groupes (<?= count($groups) ?>)</button>
</div>

<!-- ── Onglet Élèves ───────────────────────────────────────────────── -->
<div id="tab-students" class="tab-content">
  <div class="tab-actions">
    <!-- id="btnOpenImport" : géré par event listener dans classes-show.js -->
    <button class="btn btn-primary btn-sm" id="btnOpenImport">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      Importer depuis Pronote
    </button>
  </div>

  <?php if (empty($students)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <h3>Aucun élève</h3>
    <p>Copiez la liste depuis Pronote puis cliquez sur "Importer depuis Pronote".</p>
  </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>Nom</th><th>Prénom</th><th>Classe</th><th>Niveau</th><th>INE</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['last_name']) ?></td>
        <td><?= htmlspecialchars($s['first_name']) ?></td>
        <td><?= htmlspecialchars($s['class_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($s['level'] ?? '') ?></td>
        <td class="text-muted text-sm"><?= htmlspecialchars($s['pronote_id'] ?? '') ?></td>
        <td>
          <!-- Lien vers le bilan individuel de l'élève -->
          <a href="/students/<?= (int)$s['id'] ?>/bilan" class="btn btn-sm btn-ghost" title="Bilan de <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>">
            📊 Bilan
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Onglet Plans ─────────────────────────────────────────────────── -->
<div id="tab-plans" class="tab-content" hidden>
  <div class="tab-actions">
    <!-- id="btnOpenNewPlan" : géré par event listener dans classes-show.js -->
    <button class="btn btn-primary btn-sm" id="btnOpenNewPlan">+ Nouveau plan</button>
  </div>
  <?php if (empty($plans)): ?>
  <div class="empty-state"><p>Aucun plan de salle. Créez-en un pour placer les élèves.</p></div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($plans as $pl): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title"><?= htmlspecialchars($pl['name']) ?></div>
        <div class="card-meta"><?= htmlspecialchars($pl['room_name']) ?></div>
      </div>
      <div class="card-footer">
        <a href="/plans/<?= $pl['id'] ?>/edit" class="btn btn-sm btn-primary">Placer élèves</a>
        <!-- data-plan-id : géré par délégation dans classes-show.js -->
        <button class="btn btn-sm btn-danger btn-delete-plan" data-plan-id="<?= $pl['id'] ?>">Supprimer</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Onglet Groupes ───────────────────────────────────────────────── -->
<div id="tab-groups" class="tab-content" hidden>
  <?php if (empty($groups)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    <h3>Aucun groupe</h3>
    <p>Les groupes sont importés automatiquement depuis Pronote (colonne "Groupes").</p>
  </div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($groups as $g): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title"><?= htmlspecialchars($g['name']) ?></div>
        <div class="card-meta"><?= $g['student_count'] ?> élève<?= $g['student_count'] > 1 ? 's' : '' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Modal import Pronote (coller) ────────────────────────────── -->
<div class="modal-overlay" id="importModal" hidden>
  <div class="modal modal-lg">
    <div class="modal-header">
      <h2>Importer depuis Pronote</h2>
      <!-- data-close-modal géré par délégation dans classes-show.js -->
      <button class="modal-close" data-close-modal="importModal">&times;</button>
    </div>

    <div class="import-instructions">
      <ol>
        <li>Dans Pronote, allez dans <strong>Élèves → Liste des élèves</strong></li>
        <li>Sélectionnez toutes les lignes <kbd>Ctrl+A</kbd></li>
        <li>Copiez <kbd>Ctrl+C</kbd></li>
        <li>Collez ci-dessous <kbd>Ctrl+V</kbd></li>
      </ol>
    </div>

    <div class="form-group">
      <label>Données copiées depuis Pronote</label>
      <!-- oninput géré par event listener dans classes-show.js -->
      <textarea id="pronoteData" rows="10"
        placeholder="Collez ici les données copiées depuis Pronote (Ctrl+V)..."></textarea>
    </div>

    <div id="importPreview" class="import-preview" hidden>
      <span id="previewCount"></span>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" data-close-modal="importModal">Annuler</button>
      <button type="button" class="btn btn-primary" id="importBtn" disabled>Importer</button>
    </div>
  </div>
</div>

<!-- ── Modal nouveau plan ─────────────────────────────────────────────── -->
<div class="modal-overlay" id="newPlanModal" hidden>
  <div class="modal">
    <div class="modal-header">
      <h2>Nouveau plan de salle</h2>
      <!-- data-close-modal géré par délégation dans classes-show.js -->
      <button class="modal-close" data-close-modal="newPlanModal">&times;</button>
    </div>
      <!-- onsubmit géré par event listener dans classes-show.js -->
      <form id="newPlanForm">
        <div class="form-group">
          <label>Salle</label>
          <select name="room_id" required>
            <?php foreach ($rooms as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Pour quel groupe ? <span class="text-muted text-sm">(optionnel — laisser vide = toute la classe)</span></label>
          <select name="group_id">
            <option value="">— Toute la classe —</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?> (<?= $g['student_count'] ?> élèves)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Nom du plan</label>
          <input type="text" name="name" value="Plan par défaut">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-close-modal="newPlanModal">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer</button>
        </div>
      </form>
  </div>
</div>

<script src="/js/classes-show.js" defer></script>
