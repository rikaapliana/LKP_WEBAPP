<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';

// Set header for JSON response
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    if (!isset($_POST['action']) || $_POST['action'] !== 'get_questions') {
        throw new Exception('Invalid action');
    }

    $jenis_evaluasi = $_POST['jenis_evaluasi'] ?? '';
    $materi_terkait = $_POST['materi_terkait'] ?? null;

    // Validate input
    if (!in_array($jenis_evaluasi, ['per_materi', 'akhir_kursus'])) {
        throw new Exception('Jenis evaluasi tidak valid');
    }

    // Build query berdasarkan jenis evaluasi
    $whereConditions = ['is_active = 1', 'jenis_evaluasi = ?'];
    $params = [$jenis_evaluasi];
    $types = 's';

    // Add materi filter for per_materi
    if ($jenis_evaluasi === 'per_materi') {
        if (empty($materi_terkait)) {
            throw new Exception('Materi terkait diperlukan untuk evaluasi per materi');
        }
        
        if (!in_array($materi_terkait, ['word', 'excel', 'ppt', 'internet'])) {
            throw new Exception('Materi terkait tidak valid');
        }
        
        $whereConditions[] = 'materi_terkait = ?';
        $params[] = $materi_terkait;
        $types .= 's';
    } else {
        // For akhir_kursus, materi_terkait should be NULL
        $whereConditions[] = '(materi_terkait IS NULL OR materi_terkait = "")';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // Query untuk ambil pertanyaan
    $query = "SELECT 
                id_pertanyaan,
                pertanyaan,
                aspek_dinilai,
                jenis_evaluasi,
                materi_terkait,
                tipe_jawaban,
                pilihan_jawaban,
                question_order
              FROM pertanyaan_evaluasi 
              $whereClause
              ORDER BY question_order ASC, id_pertanyaan ASC";

    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $questions = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // Clean up data
        $question = [
            'id_pertanyaan' => (int)$row['id_pertanyaan'],
            'pertanyaan' => htmlspecialchars_decode($row['pertanyaan']),
            'aspek_dinilai' => htmlspecialchars_decode($row['aspek_dinilai']),
            'jenis_evaluasi' => $row['jenis_evaluasi'],
            'materi_terkait' => $row['materi_terkait'],
            'tipe_jawaban' => $row['tipe_jawaban'],
            'pilihan_jawaban' => $row['pilihan_jawaban'],
            'question_order' => (int)$row['question_order']
        ];

        // Validate JSON for pilihan_jawaban
        if ($question['tipe_jawaban'] === 'pilihan_ganda' && !empty($question['pilihan_jawaban'])) {
            $decoded = json_decode($question['pilihan_jawaban'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $question['pilihan_jawaban'] = null;
            }
        }

        $questions[] = $question;
    }

    mysqli_stmt_close($stmt);

    // Response sukses
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'total' => count($questions),
        'filter' => [
            'jenis_evaluasi' => $jenis_evaluasi,
            'materi_terkait' => $materi_terkait
        ]
    ]);

} catch (Exception $e) {
    // Response error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'questions' => []
    ]);
} catch (Error $e) {
    // Response fatal error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'questions' => []
    ]);
}
?>