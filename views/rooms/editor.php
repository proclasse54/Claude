<?php
// editor.php — contenu uniquement (wrappé par create.php ou edit.php)
$isNew = !($room['id'] ?? null);

// Construire la map des sièges actifs
$activeSeats = [];
foreach (($room['seats'] ?? []) as $s) {
    $activeSeats[$s['row_index'] . '_' . $s['col_index']] = true;
}
?>
<div class="page-header">
  <div>
    <a href="/rooms" class="btn btn-ghost btn-sm">← Retour</a>
    <h1><?= $isNew ? 'Nouvelle salle' : 'Modifier : ' . htmlspecialchars($room['name']) ?></h1>
  </div>
  <button class="btn btn-primary" onclick="saveRoom()">Enregistrer</button>
</div>

<div class="editor-layout">
  <div class="editor-panel">
    <h2>Configuration</h2>
    <div class="form-group">
      <label>Nom de la salle</label>
      <input type="text" id="roomName" value="<?= htmlspecialchars($room['name'] ?? '') ?>" placeholder="ex: Salle 101">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Rangées</label>
        <input type="number" id="roomRows" value="<?= (int)($room['rows'] ?? 5) ?>" min="1" max="15" onchange="rebuildGrid()">
      </div>
      <div class="form-group">
        <label>Colonnes</label>
        <input type="number" id="roomCols" value="<?= (int)($room['cols'] ?? 6) ?>" min="1" max="12" onchange="rebuildGrid()">
      </div>
    </div>
    <p class="form-hint">Cliquez sur une place pour l'activer ou la désactiver.<br>Les places grises sont inactives (allée, bureau…).</p>
    <div class="editor-legend">
      <span class="seat-demo active"></span> Active &nbsp;
      <span class="seat-demo inactive"></span> Inactive
    </div>
  </div>

  <div class="editor-grid-wrap">
    <div class="room-label-top">Tableau / Bureau</div>
    <div id="roomGrid" class="room-grid"></div>
  </div>
</div>

<!-- Données PHP injectées via data-attributes — lues par rooms-editor.js -->
<div id="roomEditorData" hidden
     data-room-id="<?= htmlspecialchars((string)($room['id'] ?? '')) ?>"
     data-active-seats="<?= htmlspecialchars(json_encode($activeSeats), ENT_QUOTES) ?>">
</div>

<script src="/js/rooms-editor.js" nonce="<?= htmlspecialchars($cspNonce ?? '') ?>" defer></script>
