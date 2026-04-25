<?php
class StudentController
{
    public function apiGet(array $p): void
    {
        $db = Database::get();

        $stmt = $db->prepare("
            SELECT s.*, c.name AS class_name
            FROM students s
            JOIN classes c ON c.id = s.class_id
            WHERE s.id = ?
        ");
        $stmt->execute([(int)$p['id']]);
        $student = $stmt->fetch();

        if (!$student) {
            Response::json(['error' => 'Élève introuvable'], 404);
            return;
        }

        $stmtExtra = $db->prepare("
            SELECT field_name, field_value
            FROM student_pronote_data
            WHERE student_id = ?
            ORDER BY field_name
        ");
        $stmtExtra->execute([(int)$p['id']]);
        $student['pronote_data'] = $stmtExtra->fetchAll();

        Response::json($student);
    }
}