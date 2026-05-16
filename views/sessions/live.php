<?php
$pageTitle = 'Séance — ' . $session['class_name'] . ' — ProClasse';

// Organiser les sièges en grille
$seatMap = [];
foreach ($seats as $s) { $seatMap[$s['row_index']][$s['col_index']] = $s; }

$grid = [];
for ($r = 0; $r < $session['room_rows']; $r++) {
    for ($c = 0; $c < $session['room_cols']; $c++) {
        $grid[$r][$c] = $seatMap[$r][$c] ?? null;
    }
}

// Observations indexées par student_id
$obsMap = [];
foreach ($observations as $o) { $obsMap[$o['student_id']][] = $o; }

// ── Séance passée ? (date strictement < aujourd'hui) ──────────────────────────────────────────────
$isPast = strtotime($session['date']) < strtotime(date('Y-m-d'));

// URLs séance précédente / suivante (conserve from_week si présent)
$fromWeek = preg_match('/^\d{4}-W\d{2}$/', $_GET['from_week'] ?? '') ? $_GET['from_week'] : null;
$backUrl  = $fromWeek ? '/sessions?view=week&week=' . htmlspecialchars($fromWeek) : '/sessions';

$prevUrl       = $prevId       ? '/sessions/' . (int)$prevId       . '/live' . ($fromWeek ? '?from_week=' . htmlspecialchars($fromWeek) : '') : null;
$nextUrl       = $nextId       ? '/sessions/' . (int)$nextId       . '/live' . ($fromWeek ? '?from_week=' . htmlspecialchars($fromWeek) : '') : null;
$globalNextUrl = $globalNextId ? '/sessions/' . (int)$globalNextId . '/live' . ($fromWeek ? '?from_week=' . htmlspecialchars($fromWeek) : '') : null;

// Tooltips formatés
function formatNavTooltip(?array $row, string $direction): string {
    if (!$row) return '';
    $label = htmlspecialchars($row['class_name']);
    $date  = date('d/m/Y', strtotime($row['date']));
    $time  = ($row['time_start'] && $row['time_start'] !== '00:00:00')
             ? ' ' . substr($row['time_start'], 0, 5)
             : '';
    $arrow = $direction === 'prev' ? '← ' : '→ ';
    return $arrow . $label . ' · ' . $date . $time;
}
$prevTooltip       = formatNavTooltip($prevRow       ?? null, 'prev');
$nextTooltip       = formatNavTooltip($nextRow       ?? null, 'next');
$globalNextTooltip = formatNavTooltip($globalNextRow ?? null, 'next');

?>
<style>
/* ── Navigation séance précédente / suivante ── */
.live-date-nav {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}
.live-date-label {
  font-variant-numeric: tabular-nums;
}
.live-nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  text-decoration: none;
  transition: background var(--transition), color var(--transition);
  flex-shrink: 0;
  position: relative;
}
a.live-nav-btn:hover {
  background: var(--divider);
  color: var(--primary);
}
.live-nav-btn--disabled {
  opacity: 0.25;
  cursor: default;
  pointer-events: none;
}
/* Tooltip CSS natif enrichi */
a.live-nav-btn::after {
  content: attr(title);
  position: absolute;
  top: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--color-text, #28251d);
  color: var(--color-text-inverse, #f9f8f4);
  font-size: 0.72rem;
  line-height: 1.4;
  white-space: nowrap;
  padding: 4px 8px;
  border-radius: 4px;
  pointer-events: none;
  opacity: 0;
  transition: opacity 150ms ease;
  z-index: 100;
}
a.live-nav-btn:hover::after,
a.live-nav-btn:focus-visible::after {
  opacity: 1;
}

/* ── Nom du plan (discret, italique) ── */
.live-title-plan {
  font-style: italic;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

/* ── Badge lecture seule ── */
.badge-past {
  background: var(--color-warning-highlight, #ddcfc6);
  color: var(--color-warning, #964219);
  font-size: var(--text-xs);
  border-radius: var(--radius-full);
  padding: 2px 8px;
  font-weight: 600;
  vertical-align: middle;
}

/* ── Bouton Supprimer la séance ── */
.btn-delete-session {
  margin-left: var(--space-2);
  flex-shrink: 0;
}

/* ── Bandeau séance passée ── */
.past-session-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  background: var(--color-warning-highlight, #ddcfc6);
  color: var(--color-warning, #964219);
  border-bottom: 1px solid oklch(from var(--color-warning, #964219) l c h / 0.2);
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
}

/* ── Plan de salle lecture seule ── */
.live-room--readonly .live-seat.occupied {
  cursor: default;
  opacity: 0.88;
}
.live-room--readonly .live-seat.occupied:active {
  transform: none;
}

/* ── Toast session expirée ── */
.session-expired-toast {
  position: fixed;
  bottom: var(--space-6);
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: var(--space-4);
  background: var(--color-surface, #fff);
  border: 1.5px solid var(--color-warning, #964219);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 32px oklch(0.2 0.02 60 / 0.18);
  padding: var(--space-4) var(--space-5);
  z-index: 10000;
  max-width: min(480px, calc(100vw - var(--space-8)));
  animation: toastIn 250ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
/* CORRECTIF : [hidden] doit l'emporter sur display:flex */
.session-expired-toast[hidden] {
  display: none !important;
}
@keyframes toastIn {
  from { opacity: 0; transform: translateX(-50%) translateY(12px); }
  to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.session-expired-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}
.session-expired-body {
  flex: 1;
  min-width: 0;
}
.session-expired-body strong {
  display: block;
  color: var(--color-warning, #964219);
  font-size: var(--text-sm);
  margin-bottom: var(--space-1);
}
.session-expired-body p {
  margin: 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}
/* Plan de salle grisé quand session expirée */
.live-room.session-expired {
  opacity: 0.45;
  pointer-events: none;
  user-select: none;
  filter: grayscale(0.4);
  transition: opacity 300ms ease, filter 300ms ease;
}
.skipped-list {
  list-style: none;
  margin: var(--space-3) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  max-height: 260px;
  overflow-y: auto;
}
.skipped-list li {
  background: var(--color-warning-highlight, #ddcfc6);
  border-radius: var(--radius-sm);
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-sm);
  color: var(--color-text);
}

/* ── Variante danger pour scope-btn (modale suppression) ── */
.scope-btn--danger {
  border-color: var(--color-error, #a12c7b);
  color: var(--color-error, #a12c7b);
}
.scope-btn--danger:hover {
  background: var(--color-error-highlight, #e0ced7);
}

/* ── Crop : zone canvas ── */
.crop-area {
  position: relative;
  display: inline-block;
  line-height: 0;
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: var(--shadow-md);
  max-width: 100%;
  cursor: crosshair;
  user-select: none;
  touch-action: none;
}
.crop-area canvas {
  display: block;
  max-width: 100%;
}
/* Overlay de sélection crop */
.crop-selection {
  position: absolute;
  border: 2px solid var(--color-primary, #01696f);
  box-shadow: 0 0 0 9999px oklch(0 0 0 / 0.45);
  box-sizing: border-box;
  cursor: move;
  border-radius: 2px;
}
/* Poignées de redimensionnement */
.crop-handle {
  position: absolute;
  width: 12px;
  height: 12px;
  background: var(--color-primary, #01696f);
  border: 2px solid #fff;
  border-radius: 2px;
}
.crop-handle--tl { top: -6px;  left: -6px;  cursor: nw-resize; }
.crop-handle--tr { top: -6px;  right: -6px; cursor: ne-resize; }
.crop-handle--bl { bottom: -6px; left: -6px; cursor: sw-resize; }
.crop-handle--br { bottom: -6px; right: -6px; cursor: se-resize; }
/* Zone photo + actions */
.modal-photo-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0;
}
.modal-photo-actions {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  justify-content: center;
}
.modal-photo-empty {
  width: 100px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-offset);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>

<!--
  ── Données PHP injectées pour live.js ──────────────────────────────────────────────────────────
  live.js lit SESSION_ID, IS_PAST_SESSION et BACK_URL via ces data-attributes.
  Cette approche évite tout JS inline et respecte la CSP stricte (nonce-only).
-->
<div id="liveData" hidden
     data-session-id="<?= (int)$session['id'] ?>"
     data-is-past="<?= $isPast ? 'true' : 'false' ?>"
     data-back-url="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>"
     data-login-redirect="<?= htmlspecialchars('/sessions/' . (int)$session['id'] . '/live', ENT_QUOTES) ?>"
></div>

<div class="live-header">

  <!-- ── Zone gauche : retour + identité de la séance ── -->
  <div class="live-header-left">
    <a href="<?= $backUrl ?>" class="btn btn-ghost btn-sm live-back-btn" title="Retour aux séances">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
      Séances
    </a>

    <div class="live-identity">
      <span class="live-identity-class"><?= htmlspecialchars($session['class_name']) ?></span>

      <?php if (!empty($session['room_name'])): ?>
        <span class="live-identity-sep" aria-hidden="true">·</span>
        <span class="live-identity-room"><?= htmlspecialchars($session['room_name']) ?></span>
      <?php endif; ?>

      <?php if (!empty($session['plan_name'])): ?>
        <span class="live-identity-sep" aria-hidden="true">·</span>
        <span class="live-identity-plan" title="Plan de salle"><?= htmlspecialchars($session['plan_name']) ?></span>
      <?php endif; ?>

      <?php if (!empty($session['subject'])): ?>
        <span class="live-identity-sep" aria-hidden="true">·</span>
        <span class="badge"><?= htmlspecialchars($session['subject']) ?></span>
      <?php endif; ?>

      <?php if ($isPast): ?>
        <span class="badge badge-past" aria-label="Séance passée – lecture seule">🔒 Lecture seule</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Zone droite : navigation + actions ── -->
  <div class="live-header-right">

    <!-- Groupe 1 : navigation même classe (← date →) -->
    <div class="live-nav-group" title="Navigation dans la classe <?= htmlspecialchars($session['class_name']) ?>">

      <?php if ($prevUrl): ?>
        <a href="<?= $prevUrl ?>" class="live-nav-btn" title="<?= $prevTooltip ?>" aria-label="Séance précédente (<?= htmlspecialchars($session['class_name']) ?>)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
      <?php else: ?>
        <span class="live-nav-btn live-nav-btn--disabled" aria-hidden="true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </span>
      <?php endif; ?>

      <span class="live-nav-date">
        <span class="live-nav-class-badge"><?= htmlspecialchars($session['class_name']) ?></span>
        <?= date('d/m/Y', strtotime($session['date'])) ?>
        <?php if (!empty($session['time_start']) && $session['time_start'] !== '00:00:00'): ?>
          <span class="live-nav-time"><?= substr($session['time_start'], 0, 5) ?></span>
        <?php endif; ?>
      </span>

      <?php if ($nextUrl): ?>
        <a href="<?= $nextUrl ?>" class="live-nav-btn" title="<?= $nextTooltip ?>" aria-label="Séance suivante (<?= htmlspecialchars($session['class_name']) ?>)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      <?php else: ?>
        <span class="live-nav-btn live-nav-btn--disabled" aria-hidden="true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
      <?php endif; ?>
    </div><!-- /.live-nav-group -->

    <!-- Groupe 2 : séance globale suivante (toutes classes) -->
    <?php if ($globalNextUrl): ?>
      <div class="live-nav-divider" aria-hidden="true"></div>
      <a href="<?= $globalNextUrl ?>" class="live-nav-global-next btn btn-ghost btn-sm"
         title="<?= $globalNextTooltip ?>"
         aria-label="Prochaine séance planning : <?= $globalNextTooltip ?>">
        Séance suivante
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    <?php endif; ?>

    <!-- Séparateur avant actions -->
    <div class="live-nav-divider" aria-hidden="true"></div>

    <!-- Bouton Supprimer -->
    <button type="button" id="btnDeleteSession" class="btn btn-danger btn-sm btn-delete-session" title="Supprimer cette séance">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      Supprimer
    </button>

  </div><!-- /.live-header-right -->
</div><!-- /.live-header -->

<?php if ($isPast): ?>
<!-- ================================================
     BANDEAU SÉANCE PASSÉE
     ================================================ -->
<div class="past-session-banner" role="note" aria-live="polite">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  Séance passée — le plan de salle est en <strong>lecture seule</strong>. Les tags restent modifiables.
</div>
<?php endif; ?>

<!-- ================================================
     TOAST SESSION EXPIRÉE
     ================================================ -->
<div id="sessionExpiredToast" class="session-expired-toast" hidden role="alert" aria-live="assertive">
  <div class="session-expired-icon">&#128274;</div>
  <div class="session-expired-body">
    <strong>Session expirée</strong>
    <p>Vous avez été déconnecté. Vos modifications ne sont plus enregistrées.</p>
  </div>
  <a href="/login?redirect=<?= urlencode('/sessions/' . (int)$session['id'] . '/live') ?>" class="btn btn-primary btn-sm">Se reconnecter</a>
</div>

<div class="live-layout">
  <div class="live-room-wrap">
    <div class="room-label-top">Tableau / Bureau du professeur</div>
    <div class="live-room <?= $isPast ? 'live-room--readonly' : '' ?>" id="liveRoom" style="--room-cols: <?= $session['room_cols'] ?>">
      <?php foreach ($grid as $rowIdx => $cols): ?>
        <?php foreach ($cols as $colIdx => $seat): ?>
          <?php if ($seat === null): ?>
            <div class="live-seat inactive"></div>
          <?php else: ?>
            <div class="live-seat <?= $seat['student_id'] ? 'occupied' : 'empty' ?>"
                 data-seat-id="<?= $seat['id'] ?>"
                 data-student-id="<?= $seat['student_id'] ?? '' ?>"
                 data-student-name="<?= $seat['student_id'] ? htmlspecialchars($seat['last_name'] . ' ' . $seat['first_name'], ENT_QUOTES) : '' ?>"
                 <?= ($seat['student_id'] && !$isPast) ? 'draggable="true"' : '' ?>>
              <?php if ($seat['student_id']): ?>
                <?php
                  $photoUrl = $seat['student_id'] ? '/photo?student_id=' . (int)$seat['student_id'] : null;
                ?>
                <div class="seat-photo-wrapper">
                  <img src="<?= htmlspecialchars($photoUrl) ?>"
                      alt="<?= htmlspecialchars($seat['first_name'] . ' ' . $seat['last_name']) ?>"
                      class="seat-photo" loading="lazy">
                  <div class="seat-photo-placeholder" style="display:none;">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($seat['first_name'], 0, 1) . mb_substr($seat['last_name'], 0, 1))) ?>
                  </div>
                </div>

                <div class="seat-name">
                  <?= htmlspecialchars($seat['first_name']) ?><br>
                  <small><?= htmlspecialchars($seat['last_name']) ?></small>
                </div>

                <div class="seat-tags" id="tags-<?= $seat['student_id'] ?>">
                  <?php foreach ($obsMap[$seat['student_id']] ?? [] as $o): ?>
                  <span class="tag-chip"
                        style="background:<?= htmlspecialchars($o['color'] ?? '#888') ?>"
                        data-obs-id="<?= $o['id'] ?>"
                        data-student-id="<?= $seat['student_id'] ?>"
                        title="Retirer"><?= htmlspecialchars(($o['icon'] ?? '') . (($o['tag'] ?? '') ? ' ' . $o['tag'] : '')) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="seat-empty-label">&mdash;</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="live-sidebar">
    <h3>Tags rapides</h3>
    <div class="tags-list" id="tagsList">
      <?php foreach ($tags as $t): ?>
      <button class="tag-btn"
              style="--tag-color:<?= htmlspecialchars($t['color']) ?>"
              data-tag="<?= htmlspecialchars($t['label']) ?>"
              data-icon="<?= htmlspecialchars($t['icon'] ?? '') ?>"
              data-color="<?= htmlspecialchars($t['color']) ?>">
        <?= htmlspecialchars(($t['icon'] ?? '') . ' ' . $t['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="selectedStudent" class="selected-student" hidden>
      <div class="selected-name" id="selectedName"></div>
      <p class="text-muted text-sm">Choisissez un tag ci-dessus</p>
    </div>
  </div>
</div>

<!-- ================================================
     MODALE SCOPE : session / futures / toutes
     ================================================ -->
<div id="scopeModal" class="scope-modal" role="dialog" aria-modal="true" aria-labelledby="scopeModalTitle" hidden>
  <div class="scope-modal-box">
    <p class="scope-modal-title" id="scopeModalTitle">
      Déplacer <strong id="scopeStudentName"></strong>
    </p>
    <p class="scope-modal-subtitle">Ce déplacement doit-il affecter :</p>
    <div class="scope-modal-btns">
      <button id="scopeBtnSession" class="scope-btn" type="button">
        <span class="scope-btn-icon">📅</span>
        <span class="scope-btn-label">Cette séance uniquement</span>
        <span class="scope-btn-hint">Les autres séances ne sont pas modifiées</span>
      </button>
      <button id="scopeBtnForward" class="scope-btn scope-btn--primary" type="button">
        <span class="scope-btn-icon">⏩</span>
        <span class="scope-btn-label">Cette séance + les suivantes</span>
        <span class="scope-btn-hint">Les séances passées ne sont jamais modifiées</span>
      </button>
    </div>
    <button id="scopeBtnCancel" class="scope-cancel-btn" type="button">✕ Annuler</button>
  </div>
</div>

<div id="skippedModal" class="scope-modal" role="dialog" aria-modal="true" aria-labelledby="skippedModalTitle" hidden>
  <div class="scope-modal-box">
    <p class="scope-modal-title" id="skippedModalTitle">⚠️ Propagation partielle</p>
    <p class="scope-modal-subtitle">
      Le déplacement n'a pas pu être appliqué sur certaines séances car
      les élèves n'étaient pas aux places attendues :
    </p>
    <ul id="skippedList" class="skipped-list"></ul>
    <button id="skippedClose" class="scope-btn scope-btn--primary" type="button" style="margin-top:var(--space-4)">
      Compris
    </button>
  </div>
</div>

<!-- ================================================
     MODALE CONFIRMATION SUPPRESSION SÉANCE
     ================================================ -->
<div id="deleteSessionModal" class="scope-modal" role="dialog" aria-modal="true" aria-labelledby="deleteSessionModalTitle" hidden>
  <div class="scope-modal-box">
    <p class="scope-modal-title" id="deleteSessionModalTitle">🗑 Supprimer la séance</p>
    <p class="scope-modal-subtitle">
      Êtes-vous sûr de vouloir supprimer la séance du
      <strong><?= date('d/m/Y', strtotime($session['date'])) ?></strong>
      pour la classe <strong><?= htmlspecialchars($session['class_name']) ?></strong> ?<br>
      <span style="color:var(--color-error);font-size:var(--text-sm)">
        Cette action supprimera aussi toutes les observations enregistrées. Elle est irréversible.
      </span>
    </p>
    <div class="scope-modal-btns">
      <button id="deleteSessionConfirm" class="scope-btn scope-btn--danger" type="button">
        <span class="scope-btn-icon">🗑</span>
        <span class="scope-btn-label">Oui, supprimer définitivement</span>
      </button>
    </div>
    <button id="deleteSessionCancel" class="scope-cancel-btn" type="button">✕ Annuler</button>
  </div>
</div>

<!-- Modale infos élève -->
<div id="studentModal" class="student-modal-overlay" hidden
     aria-modal="true" role="dialog" aria-labelledby="modalStudentName">
  <div class="student-modal">
    <button class="student-modal-close" id="modalClose" aria-label="Fermer">&#x2715;</button>
    <div class="student-modal-header">
      <div class="student-modal-avatar" id="modalAvatar"></div>
      <div>
        <div class="student-modal-name" id="modalStudentName"></div>
        <div class="student-modal-class" id="modalClass"></div>
      </div>
    </div>
    <!-- Onglets -->
    <div class="student-modal-tabs">
      <button class="student-modal-tab active" data-tab="donnees">Données</button>
      <button class="student-modal-tab" data-tab="photo">Photo</button>
    </div>
    <!-- Onglet Données -->
    <div class="student-modal-body" id="modalBody" data-panel="donnees">
      <div class="student-modal-loading">Chargement&hellip;</div>
    </div>
    <!-- Onglet Photo : upload + recadrage interactif -->
    <div class="student-modal-body" id="modalPhotoPanel" data-panel="photo" hidden>
      <div class="modal-photo-zone">

        <!-- Prévisualisation photo actuelle (hors mode crop) -->
        <div id="modalPhotoPreview"></div>

        <!-- Zone de recadrage (masquée par défaut, affichée après sélection d'un fichier ou clic sur Modifier) -->
        <div id="cropContainer" style="display:none; width:100%; text-align:center;">
          <div class="crop-area" id="cropArea">
            <!-- Le canvas recevra l'image source -->
            <canvas id="cropCanvas"></canvas>
            <!-- La sélection de recadrage (overlay CSS) -->
            <div class="crop-selection" id="cropSelection">
              <div class="crop-handle crop-handle--tl" data-handle="tl"></div>
              <div class="crop-handle crop-handle--tr" data-handle="tr"></div>
              <div class="crop-handle crop-handle--bl" data-handle="bl"></div>
              <div class="crop-handle crop-handle--br" data-handle="br"></div>
            </div>
          </div>
          <!-- Boutons d'action du crop -->
          <div class="modal-photo-actions" style="margin-top:var(--space-3);">
            <button class="btn btn-primary btn-sm" id="cropSaveBtn">✓ Recadrer &amp; enregistrer</button>
            <button class="btn btn-ghost btn-sm" id="cropCancelBtn">✕ Annuler</button>
          </div>
        </div>

        <!-- Boutons principaux (hors mode crop) — reconstruits dynamiquement par renderPhotoTab() -->
        <div class="modal-photo-actions" id="photoMainActions"></div>

        <!-- Input fichier (caché, déclenché programmatiquement) -->
        <input type="file" id="modalPhotoInput" accept="image/*" style="display:none;">

        <p class="form-hint" id="modalPhotoHint">Formats : JPG, PNG, WEBP. Max 2 Mo.</p>
      </div>
    </div>
    <div class="student-modal-footer">
      <!-- Bouton Bilan : href mis à jour dynamiquement à l'ouverture de la modale -->
      <a href="#" id="modalBilanBtn" class="btn btn-ghost btn-sm" target="_blank" rel="noopener noreferrer" title="Ouvrir le bilan de l'élève dans un nouvel onglet">
        📊 Bilan
      </a>
      <button class="btn btn-danger btn-sm" id="modalRemoveBtn">
        &#128465; Retirer du plan de salle
      </button>
    </div>
  </div>
</div>

<!--
  ── Scripts externalisés ─────────────────────────────────────────────────────────────────────────
  live.js     : logique séance (tags, déplacements, drag & drop, modales scope/suppression,
                détection session expirée). Lit ses données PHP via #liveData[data-*].
  live-crop.js : modale fiche élève, onglet photo, upload et recadrage interactif.
                 Dépend de live.js (window.apiFetch, window.seatStudentMap, SESSION_ID).
-->
<script src="/js/live.js" nonce="<?= htmlspecialchars($cspNonce ?? '') ?>" defer></script>
<script src="/js/live-crop.js" nonce="<?= htmlspecialchars($cspNonce ?? '') ?>" defer></script>
