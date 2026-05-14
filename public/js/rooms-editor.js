/* ROOM_ID et activeMap sont injectés via data-* sur #roomEditorData */
const _edData = document.getElementById('roomEditorData');
const ROOM_ID  = _edData ? (_edData.dataset.roomId !== '' ? parseInt(_edData.dataset.roomId) : null) : null;
let activeMap  = _edData ? JSON.parse(_edData.dataset.activeSeats) : {};

function rebuildGrid() {
  var rows = parseInt(document.getElementById('roomRows').value) || 5;
  var cols = parseInt(document.getElementById('roomCols').value) || 6;
  var grid = document.getElementById('roomGrid');
  grid.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
  grid.innerHTML = '';
  for (var r = 0; r < rows; r++) {
    for (var c = 0; c < cols; c++) {
      (function(row, col) {
        var key  = row + '_' + col;
        var seat = document.createElement('div');
        seat.className   = 'seat-cell ' + (activeMap[key] ? 'active' : 'inactive');
        seat.textContent = String.fromCharCode(65 + row) + (col + 1);
        seat.addEventListener('click', function() {
          if (activeMap[key]) { delete activeMap[key]; seat.className = 'seat-cell inactive'; }
          else                { activeMap[key] = true;  seat.className = 'seat-cell active';   }
        });
        grid.appendChild(seat);
      })(r, c);
    }
  }
}

function saveRoom() {
  var rows = parseInt(document.getElementById('roomRows').value);
  var cols = parseInt(document.getElementById('roomCols').value);
  var name = document.getElementById('roomName').value.trim();
  if (!name) { alert('Donnez un nom à la salle.'); return; }

  var seats = Object.keys(activeMap).map(function(k) {
    var p = k.split('_');
    var r = parseInt(p[0]), c = parseInt(p[1]);
    return { row: r, col: c, label: String.fromCharCode(65 + r) + (c + 1) };
  });

  var url     = ROOM_ID ? '/api/rooms/' + ROOM_ID : '/api/rooms';
  var payload = JSON.stringify({ name: name, rows: rows, cols: cols, seats: seats });

  fetch(url, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    payload
  })
  .then(function(response) {
    return response.text().then(function(text) {
      try {
        var d = JSON.parse(text);
        if (d.ok) {
          window.location.href = '/rooms';
        } else {
          alert('Erreur serveur : ' + JSON.stringify(d));
        }
      } catch(e) {
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

rebuildGrid();
