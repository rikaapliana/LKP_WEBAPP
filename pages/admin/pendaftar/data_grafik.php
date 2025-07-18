<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';

// Set header untuk JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Ambil parameter filter
    $gelombang_filter = isset($_GET['gelombang']) ? (int)$_GET['gelombang'] : null;
    $tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;
    
    // Base WHERE clause
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($gelombang_filter) {
        $whereClause .= " AND p.id_gelombang = ?";
        $params[] = $gelombang_filter;
    }
    
    if ($tahun_filter) {
        $whereClause .= " AND g.tahun = ?";
        $params[] = $tahun_filter;
    }

    // 1. STATISTIK JENIS KELAMIN
    $queryJenisKelamin = "
        SELECT 
            p.jenis_kelamin,
            COUNT(*) as jumlah
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.jenis_kelamin IS NOT NULL
        GROUP BY p.jenis_kelamin
        ORDER BY p.jenis_kelamin
    ";
    
    $stmtJK = mysqli_prepare($conn, $queryJenisKelamin);
    if ($params) {
        $types = str_repeat('i', count($params));
        mysqli_stmt_bind_param($stmtJK, $types, ...$params);
    }
    mysqli_stmt_execute($stmtJK);
    $resultJK = mysqli_stmt_get_result($stmtJK);
    
    $dataJenisKelamin = [];
    while ($row = mysqli_fetch_assoc($resultJK)) {
        $dataJenisKelamin[] = [
            'label' => $row['jenis_kelamin'],
            'value' => (int)$row['jumlah']
        ];
    }

    // 2. STATISTIK PENDIDIKAN TERAKHIR
    $queryPendidikan = "
        SELECT 
            p.pendidikan_terakhir,
            COUNT(*) as jumlah
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.pendidikan_terakhir IS NOT NULL
        GROUP BY p.pendidikan_terakhir
        ORDER BY 
            CASE p.pendidikan_terakhir
                WHEN 'SD' THEN 1
                WHEN 'SLTP' THEN 2
                WHEN 'SLTA' THEN 3
                WHEN 'D1' THEN 4
                WHEN 'D2' THEN 5
                WHEN 'S1' THEN 6
                WHEN 'S2' THEN 7
                WHEN 'S3' THEN 8
                ELSE 9
            END
    ";
    
    $stmtPendidikan = mysqli_prepare($conn, $queryPendidikan);
    if ($params) {
        $types = str_repeat('i', count($params));
        mysqli_stmt_bind_param($stmtPendidikan, $types, ...$params);
    }
    mysqli_stmt_execute($stmtPendidikan);
    $resultPendidikan = mysqli_stmt_get_result($stmtPendidikan);
    
    $dataPendidikan = [];
    while ($row = mysqli_fetch_assoc($resultPendidikan)) {
        $dataPendidikan[] = [
            'label' => $row['pendidikan_terakhir'],
            'value' => (int)$row['jumlah']
        ];
    }

    // 3. STATISTIK USIA (KATEGORI)
    $queryUsia = "
        SELECT 
            p.tanggal_lahir,
            TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) as usia
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.tanggal_lahir IS NOT NULL
    ";
    
    $stmtUsia = mysqli_prepare($conn, $queryUsia);
    if ($params) {
        $types = str_repeat('i', count($params));
        mysqli_stmt_bind_param($stmtUsia, $types, ...$params);
    }
    mysqli_stmt_execute($stmtUsia);
    $resultUsia = mysqli_stmt_get_result($stmtUsia);
    
    // Kelompokkan usia ke kategori
    $kategoriUsia = [
        '17-20 tahun' => 0,
        '21-25 tahun' => 0,
        '26-30 tahun' => 0,
        '31-35 tahun' => 0,
        '36+ tahun' => 0
    ];
    
    while ($row = mysqli_fetch_assoc($resultUsia)) {
        $usia = (int)$row['usia'];
        
        if ($usia >= 17 && $usia <= 20) {
            $kategoriUsia['17-20 tahun']++;
        } elseif ($usia >= 21 && $usia <= 25) {
            $kategoriUsia['21-25 tahun']++;
        } elseif ($usia >= 26 && $usia <= 30) {
            $kategoriUsia['26-30 tahun']++;
        } elseif ($usia >= 31 && $usia <= 35) {
            $kategoriUsia['31-35 tahun']++;
        } elseif ($usia >= 36) {
            $kategoriUsia['36+ tahun']++;
        }
    }
    
    $dataUsia = [];
    foreach ($kategoriUsia as $kategori => $jumlah) {
        $dataUsia[] = [
            'label' => $kategori,
            'value' => $jumlah
        ];
    }

    // 4. STATISTIK TOTAL UNTUK CARDS
    $queryTotal = "
        SELECT 
            COUNT(*) as total_pendaftar,
            SUM(CASE WHEN p.jenis_kelamin = 'Laki-Laki' THEN 1 ELSE 0 END) as total_laki,
            SUM(CASE WHEN p.jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as total_perempuan,
            SUM(CASE WHEN p.status_pendaftaran = 'Belum di Verifikasi' THEN 1 ELSE 0 END) as belum_verifikasi,
            SUM(CASE WHEN p.status_pendaftaran = 'Terverifikasi' THEN 1 ELSE 0 END) as terverifikasi,
            SUM(CASE WHEN p.status_pendaftaran = 'Diterima' THEN 1 ELSE 0 END) as diterima
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
    ";
    
    $stmtTotal = mysqli_prepare($conn, $queryTotal);
    if ($params) {
        $types = str_repeat('i', count($params));
        mysqli_stmt_bind_param($stmtTotal, $types, ...$params);
    }
    mysqli_stmt_execute($stmtTotal);
    $resultTotal = mysqli_stmt_get_result($stmtTotal);
    $dataTotal = mysqli_fetch_assoc($resultTotal);

    // 5. DATA UNTUK DROPDOWN FILTER
    $queryGelombang = "
        SELECT DISTINCT 
            g.id_gelombang,
            g.nama_gelombang,
            g.gelombang_ke,
            g.tahun,
            COUNT(p.id_pendaftar) as jumlah_pendaftar
        FROM gelombang g
        LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
        GROUP BY g.id_gelombang, g.nama_gelombang, g.gelombang_ke, g.tahun
        HAVING jumlah_pendaftar > 0
        ORDER BY g.tahun DESC, g.gelombang_ke DESC
    ";
    $resultGelombangList = mysqli_query($conn, $queryGelombang);
    
    $gelombangList = [];
    while ($row = mysqli_fetch_assoc($resultGelombangList)) {
        $gelombangList[] = [
            'id' => $row['id_gelombang'],
            'nama' => $row['nama_gelombang'],
            'tahun' => $row['tahun'],
            'jumlah' => $row['jumlah_pendaftar']
        ];
    }
    
    $queryTahun = "
        SELECT DISTINCT 
            g.tahun,
            COUNT(p.id_pendaftar) as jumlah_pendaftar
        FROM gelombang g
        LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
        GROUP BY g.tahun
        HAVING jumlah_pendaftar > 0
        ORDER BY g.tahun DESC
    ";
    $resultTahunList = mysqli_query($conn, $queryTahun);
    
    $tahunList = [];
    while ($row = mysqli_fetch_assoc($resultTahunList)) {
        $tahunList[] = [
            'tahun' => $row['tahun'],
            'jumlah' => $row['jumlah_pendaftar']
        ];
    }

    // Buat response JSON
    $response = [
        'success' => true,
        'data' => [
            'jenis_kelamin' => $dataJenisKelamin,
            'pendidikan' => $dataPendidikan,
            'usia' => $dataUsia,
            'total' => $dataTotal,
            'gelombang_list' => $gelombangList,
            'tahun_list' => $tahunList
        ],
        'filter' => [
            'gelombang' => $gelombang_filter,
            'tahun' => $tahun_filter
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Error response
    $response = [
        'success' => false,
        'error' => 'Terjadi kesalahan dalam mengambil data',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($response, JSON_PRETTY_PRINT);
} finally {
    // Tutup koneksi
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>