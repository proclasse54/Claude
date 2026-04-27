<?php
// FICHIER TEMPORAIRE DE DEBUG - A SUPPRIMER APRES UTILISATION
require_once __DIR__ . '/../src/Database.php';

$sessionId = (int)($_GET['session_id'] ?? 0);
if (!$sessionId) { die('Passe ?session_id=XXX dans l URL'); }

$db = Database::get();

$session = $db->prepare("SELECT id, plan_id, date, time_start FROM sessions WHERE id = ?");
$session->execute([$sessionId]);
$ses = $session->fetch(PDO::FETCH_ASSOC);
if (!$ses) { die('Session introuvable'); }

$planId = $ses['plan_id'];

echo "<h2>Session $sessionId — plan_id=$planId — date={$ses['date']} {$ses['time_start']}</h2>";

echo "<h3>seating_assignments (plan $planId)</h3>";
$rows = $db->prepare("SELECT sa.seat_id, sa.student_id, st.first_name, st.last_name FROM seating_assignments sa LEFT JOIN students st ON st.id = sa.student_id WHERE sa.plan_id = ? ORDER BY sa.seat_id");
$rows->execute([$planId]);
echo "<table border=1 cellpadding=4><tr><th>seat_id</th><th>student_id</th><th>nom</th></tr>";
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "<tr><td>{$r['seat_id']}</td><td>{$r['student_id']}</td><td>{$r['last_name']} {$r['first_name']}</td></tr>";
}
echo "</table>";

echo "<h3>session_seat_overrides pour session $sessionId</h3>";
$ovs = $db->prepare("SELECT sso.seat_id, sso.student_id, st.first_name, st.last_name FROM session_seat_overrides sso LEFT JOIN students st ON st.id = sso.student_id WHERE sso.session_id = ? ORDER BY sso.seat_id");
$ovs->execute([$sessionId]);
$ovList = $ovs->fetchAll(PDO::FETCH_ASSOC);
if (!$ovList) {
    echo "<p><em>Aucun override pour cette séance.</em></p>";
} else {
    echo "<table border=1 cellpadding=4><tr><th>seat_id</th><th>student_id</th><th>nom</th></tr>";
    foreach ($ovList as $r) {
        echo "<tr><td>{$r['seat_id']}</td><td>{$r['student_id']}</td><td>{$r['last_name']} {$r['first_name']}</td></tr>";
    }
    echo "</table>";
}

echo "<h3>Toutes les séances du plan $planId avec leurs overrides sur sièges 127/128/131</h3>";
$all = $db->prepare("
    SELECT se.id, se.date, se.time_start,
           sso.seat_id, sso.student_id,
           st.first_name, st.last_name
    FROM sessions se
    LEFT JOIN session_seat_overrides sso ON sso.session_id = se.id AND sso.seat_id IN (127,128,131)
    LEFT JOIN students st ON st.id = sso.student_id
    WHERE se.plan_id = ?
    ORDER BY se.date, se.time_start, sso.seat_id
");
$all->execute([$planId]);
echo "<table border=1 cellpadding=4><tr><th>session_id</th><th>date</th><th>time_start</th><th>seat_id</th><th>student_id</th><th>nom</th></tr>";
foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['date']}</td><td>{$r['time_start']}</td><td>{$r['seat_id']}</td><td>{$r['student_id']}</td><td>{$r['last_name']} {$r['first_name']}</td></tr>";
}
echo "</table>";
