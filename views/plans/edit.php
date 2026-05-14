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

// Map seat_id => student_id pour le JS
$assignmentsJs = [];
foreach ($assignments as $a) { $assignmentsJs[$a['seat_id']] = $a['student_id']; }

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

<!-- Données PHP → JS (pas de nonce nécessaire, type=application/json n'est pas exécuté) -->
<div id="planEditData"
     data-plan-id="<?= (int)$plan['id'] ?>"
     data-room-cols="<?= (int)$room['cols'] ?>"
     hidden></div>
<script id="planAssignments" type="application/json"><?= json_encode($assignmentsJs) ?></script>
<script src="/js/plans-edit.js" defer></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
