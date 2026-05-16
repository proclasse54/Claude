// ── Lecture des données PHP injectées via data-attributes ─────────────────────────
// ROOM_ID et activeMap sont injectés via data-* sur #roomEditorData (voir editor.php)
const _edData = document.getElementById('roomEditorData');
const ROOM_ID = _edData ? (_edData.dataset.roomId !== '' ? parseInt(_edData.dataset.roomId) : null) : null;
let activeMap = _edData ? JSON.parse(_edData.dataset.activeSeats) : {};

// ── Initialisation des event listeners ──────────────────────────────────────
// Script injecté en bas de <body> via $content : DOM déjà construit,
// pas besoin de DOMContentLoaded ni de defer.
// Les onclick=/onchange= inline ont été supprimés dans editor.php
// (interdit par la CSP script-src-attr).

// Bouton « Enregistrer »
document.getElementById('btnSaveRoom')?.addEventListener('click', saveRoom);

// Inputs Rangées et Colonnes — reconstruction de la grille à chaque changement
document.getElementById('roomRows')?.addEventListener('change', rebuildGrid);
document.getElementById('roomCols')?.addEventListener('change', rebuildGrid);

// ── Construction de la grille ─────────────────────────────────────────────────────

/**
 * Reconstruit la grille des sièges selon les valeurs des inputs Rangées/Colonnes.
 * Appelé à l'initialisation et à chaque changement via les listeners ci-dessus.
 */
function rebuildGrid() {
  const rows = parseInt(document.getElementById('roomRows').value) || 5;
  const cols = parseInt(document.getElementById('roomCols').value) || 6;
  const grid = document.getElementById('roomGrid');
  grid.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
  grid.innerHTML = '';
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      (function(row, col) {
        const key  = row + '_' + col;
        const seat = document.createElement('div');
        seat.className   = 'seat-cell ' + (activeMap[key] ? 'active' : 'inactive');
        seat.textContent = String.fromCharCode(65 + row) + (col + 1);
        // Bascule active/inactive au clic sur le siège
        seat.addEventListener('click', function() {
          if (activeMap[key]) { delete activeMap[key]; seat.className = 'seat-cell inactive'; }
          else                { activeMap[key] = true;  seat.className = 'seat-cell active';   }
        });
        grid.appendChild(seat);
      })(r, c);
    }
  }
}

// ── Sauvegarde ──────────────────────────────────────────────────────────────────

/**
 * Envoie la configuration de la salle (nom, dimensions, sièges actifs) à l'API.
 * Appelé par le listener sur #btnSaveRoom (voir ci-dessus).
 */
function saveRoom() {
  const rows = parseInt(document.getElementById('roomRows').value);
  const cols = parseInt(document.getElementById('roomCols').value);
  const name = document.getElementById('roomName').value.trim();
  if (!name) { alert('Donnez un nom à la salle.'); return; }

  const seats = Object.keys(activeMap).map(function(k) {
    const p = k.split('_');
    const r = parseInt(p[0]), c = parseInt(p[1]);
    return { row: r, col: c, label: String.fromCharCode(65 + r) + (c + 1) };
  });

  const url     = ROOM_ID ? '/api/rooms/' + ROOM_ID : '/api/rooms';
  const payload = JSON.stringify({ name: name, rows: rows, cols: cols, seats: seats });

  fetch(url, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    payload
  })
  .then(function(response) {
    return response.text().then(function(text) {
      try {
        const d = JSON.parse(text);
        if (d.ok) {
          window.location.href = '/rooms';
        } else {
          alert('Erreur serveur : ' + JSON.stringify(d));
        }
      } catch(e) {
        // Affiche l'erreur PHP brute si la réponse n'est pas du JSON valide
        document.open();
        document.write('<h2 style="color:red;font-family:monospace">Erreur PHP reçue :</h2>' + text);
        document.close();
      }
    });
  })
  .catch(function(err) {
    alert('Erreur fetch : ' + err.message);
  });
}

// Construction initiale de la grille au chargement du script
rebuildGrid();
