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

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">window.PLANS = <?= json_encode(array_values($plans), JSON_HEX_TAG) ?>;</script>
<script src="/js/sessions.js" defer></script>
