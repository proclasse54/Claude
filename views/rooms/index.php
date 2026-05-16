<?php
// views/rooms/index.php
// $rooms injecté par RoomController::index()
?>

<?php
ob_start();
?>

<div class="page-header">
  <div>
    <h1>Salles</h1>
    <p class="text-muted">Configurez la disposition de vos salles de classe</p>
  </div>
  <a href="/rooms/create" class="btn btn-primary">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle salle
  </a>
</div>

<?php if (empty($rooms)): ?>
<div class="empty-state">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
  <h3>Aucune salle</h3>
  <p>Créez votre première salle pour commencer à définir des plans de classe.</p>
  <a href="/rooms/create" class="btn btn-primary">Créer une salle</a>
</div>
<?php else: ?>
<div class="cards-grid">
  <?php foreach ($rooms as $r): ?>
  <div class="card">
    <div class="card-body">
      <div class="card-title"><?= htmlspecialchars($r['name']) ?></div>
      <div class="card-meta"><?= $r['rows'] ?> rangées × <?= $r['cols'] ?> colonnes · <?= $r['seat_count'] ?> places actives</div>
    </div>
    <div class="card-footer">
      <a href="/rooms/<?= $r['id'] ?>/edit" class="btn btn-sm btn-ghost">Modifier</a>
      <!-- onclick= supprimé : la suppression est gérée par délégation dans rooms.js
           via data-id et data-name, conformément à la CSP script-src-attr. -->
      <button class="btn btn-sm btn-danger btn-delete-room"
              data-id="<?= $r['id'] ?>"
              data-name="<?= htmlspecialchars($r['name']) ?>">
        Supprimer
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- <script> inline supprimé : deleteRoom() est dans public/js/rooms.js
     Script sans defer : injecté en bas de $content (lui-même en bas de <body>),
     le DOM est déjà prêt à ce point — pas besoin de DOMContentLoaded ni de defer. -->
<script src="/js/rooms.js" nonce="<?= htmlspecialchars($cspNonce ?? '') ?>"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';