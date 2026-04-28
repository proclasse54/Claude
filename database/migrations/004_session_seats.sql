-- ============================================================
--  Migration 004 – session_seats
--  Remplace session_seat_overrides par un snapshot complet
--  par séance.
--
--  À exécuter UNE SEULE FOIS sur la base proclasse.
-- ============================================================

USE proclasse;

-- ------------------------------------------------------------
-- 1. Nouvelle table snapshot
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_seats (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    seat_id    INT NOT NULL,
    student_id INT NULL,          -- NULL = siège vide / élève absent
    UNIQUE KEY uq_session_seat (session_id, seat_id),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id)    REFERENCES seats(id)    ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. Remplir session_seats pour les séances existantes
--    Base = plan seating_assignments
--    Les overrides existants sont appliqués par-dessus
-- ------------------------------------------------------------
INSERT IGNORE INTO session_seats (session_id, seat_id, student_id)
SELECT
    se.id          AS session_id,
    s.id           AS seat_id,
    COALESCE(sso.student_id, sa.student_id) AS student_id
FROM sessions se
JOIN seating_plans sp ON sp.id = se.plan_id
JOIN seats s          ON s.room_id = sp.room_id
LEFT JOIN seating_assignments sa
       ON sa.seat_id = s.id AND sa.plan_id = se.plan_id
LEFT JOIN session_seat_overrides sso
       ON sso.session_id = se.id AND sso.seat_id = s.id
WHERE se.plan_id IS NOT NULL;

-- ------------------------------------------------------------
-- 3. Supprimer l'ancienne table
-- ------------------------------------------------------------
DROP TABLE IF EXISTS session_seat_overrides;
