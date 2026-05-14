const _d = document.getElementById('planEditData');
const PLAN_ID   = parseInt(_d.dataset.planId);
const ROOM_COLS = parseInt(_d.dataset.roomCols);
let assignments = JSON.parse(_d.dataset.assignments);
// Reconstruire studentSeat depuis assignments
let studentSeat = {};
Object.entries(assignments).forEach(([sid, stid]) => {
  if (stid) studentSeat[stid] = parseInt(sid);
});
