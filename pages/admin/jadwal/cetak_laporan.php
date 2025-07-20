<?php
// File: pages/admin/jadwal/cetak_laporan.php
// Cetak laporan jadwal menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterKelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filterInstruktur = isset($_GET['filter_instruktur']) ? $_GET['filter_instruktur'] : '';
$filterHari = isset($_GET['filter_hari']) ? $_GET['filter_hari'] : '';
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : '';
$filterTanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters (sama seperti di index.php)
$whereConditions = [];
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $whereConditions[] = "(k.nama_kelas LIKE ? OR i.nama LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= "ss";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
    $types .= "s";
}

if (!empty($filterInstruktur)) {
    $whereConditions[] = "i.nama = ?";
    $params[] = $filterInstruktur;
    $types .= "s";
}

if (!empty($filterTanggal)) {
    $whereConditions[] = "j.tanggal = ?";
    $params[] = $filterTanggal;
    $types .= "s";
}

if (!empty($filterHari)) {
    $hariMap = [
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday', 
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
        'Minggu' => 'Sunday'
    ];
    if (isset($hariMap[$filterHari])) {
        $whereConditions[] = "DAYNAME(j.tanggal) = ?";
        $params[] = $hariMap[$filterHari];
        $types .= "s";
    }
}

if (!empty($filterPeriode)) {
    $today = date('Y-m-d');
    switch($filterPeriode) {
        case 'today':
            $whereConditions[] = "j.tanggal = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            $types .= "ss";
            break;
        case 'month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfMonth;
            $params[] = $endOfMonth;
            $types .= "ss";
            break;
        case 'past':
            $whereConditions[] = "j.tanggal < ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'upcoming':
            $whereConditions[] = "j.tanggal > ?";
            $params[] = $today;
            $types .= "s";
            break;
    }
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Query data jadwal
$query = "SELECT j.*, 
          k.nama_kelas, 
          g.nama_gelombang,
          i.nama as nama_instruktur,
          CASE DAYNAME(j.tanggal)
            WHEN 'Monday' THEN 'Senin'
            WHEN 'Tuesday' THEN 'Selasa' 
            WHEN 'Wednesday' THEN 'Rabu'
            WHEN 'Thursday' THEN 'Kamis'
            WHEN 'Friday' THEN 'Jumat'
            WHEN 'Saturday' THEN 'Sabtu'
            WHEN 'Sunday' THEN 'Minggu'
          END as hari_indonesia
          FROM jadwal j 
          LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
          LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
          $whereClause
          ORDER BY j.tanggal DESC, j.waktu_mulai ASC";

// Execute query
try {
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Error executing query: " . mysqli_error($conn));
        }
    }

    // Ambil semua data
    $dataArray = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dataArray[] = $row;
    }

    $totalJadwal = count($dataArray);

    // Tutup statement jika ada
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

} catch (Exception $e) {
    // Log error dan tampilkan pesan user-friendly
    error_log("Database error in cetak_laporan.php: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p>Silakan coba lagi atau hubungi administrator.</p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Jadwal</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterKelas)) {
    $filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);
}
if (!empty($filterInstruktur)) {
    $filter_info[] = "Instruktur: " . htmlspecialchars($filterInstruktur);
}
if (!empty($filterHari)) {
    $filter_info[] = "Hari: " . htmlspecialchars($filterHari);
}
if (!empty($filterTanggal)) {
    $filter_info[] = "Tanggal: " . date('d/m/Y', strtotime($filterTanggal));
}
if (!empty($filterPeriode)) {
    $periode_label = [
        'today' => 'Hari Ini',
        'week' => 'Minggu Ini',
        'month' => 'Bulan Ini',
        'past' => 'Yang Sudah Lewat',
        'upcoming' => 'Yang Akan Datang'
    ];
    $filter_info[] = "Periode: " . ($periode_label[$filterPeriode] ?? $filterPeriode);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Gunakan Portrait untuk 7 kolom (NO, TANGGAL, HARI, WAKTU, KELAS, INSTRUKTUR, STATUS)
    $pdf = LKP_ReportFactory::createJadwalReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Jadwal',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalJadwal,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data jadwal
    if (!empty($dataArray)) {
        // Custom table untuk Jadwal
        createJadwalTableWithMultiCell($pdf, $dataArray);
        
        // Tambah ringkasan data di bawah tabel
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data Jadwal:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik
        $stats_kelas = [];
        $stats_instruktur = [];
        $stats_status = ['Selesai' => 0, 'Hari Ini' => 0, 'Terjadwal' => 0];
        $today = date('Y-m-d');
        
        foreach ($dataArray as $row) {
            $kelas = $row['nama_kelas'] ?? 'Tidak Diketahui';
            $instruktur = $row['nama_instruktur'] ?? 'Belum Ditentukan';
            $tanggal = $row['tanggal'];
            
            // Statistik kelas
            $stats_kelas[$kelas] = ($stats_kelas[$kelas] ?? 0) + 1;
            
            // Statistik instruktur
            $stats_instruktur[$instruktur] = ($stats_instruktur[$instruktur] ?? 0) + 1;
            
            // Statistik status
            if ($tanggal < $today) {
                $stats_status['Selesai']++;
            } elseif ($tanggal == $today) {
                $stats_status['Hari Ini']++;
            } else {
                $stats_status['Terjadwal']++;
            }
        }
        
        $no_ringkasan = 1;
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Jadwal: ' . number_format($totalJadwal) . ' sesi', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Jadwal Selesai: ' . number_format($stats_status['Selesai']) . ' sesi', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Jadwal Hari Ini: ' . number_format($stats_status['Hari Ini']) . ' sesi', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Jadwal Akan Datang: ' . number_format($stats_status['Terjadwal']) . ' sesi', 0, 1, 'L');
        
    } else {
        // Jika tidak ada data
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data jadwal yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Jadwal'];
    
    if (!empty($filterKelas)) {
        $filename_parts[] = str_replace(' ', '_', $filterKelas);
    }
    if (!empty($filterPeriode)) {
        $filename_parts[] = ucfirst($filterPeriode);
    }
    if (!empty($filterTanggal)) {
        $filename_parts[] = date('Y-m-d', strtotime($filterTanggal));
    }
    
    $filename_parts[] = date('Y-m-d_H-i-s');
    $filename = implode('_', $filename_parts) . '.pdf';
    
    // Output PDF
    $pdf->Output('I', $filename); // 'I' = inline di browser, 'D' = download
    
} catch (Exception $e) {
    // Error handling yang user-friendly
    error_log("PDF generation error: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - Generate PDF</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card border-danger'>
                        <div class='card-header bg-danger text-white'>
                            <h5 class='mb-0'>
                                <i class='bi bi-exclamation-triangle'></i> 
                                Error Generating PDF
                            </h5>
                        </div>
                        <div class='card-body'>
                            <div class='alert alert-danger' role='alert'>
                                <h6 class='alert-heading'>Gagal Membuat PDF</h6>
                                <p>Terjadi kesalahan saat membuat file PDF laporan jadwal.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-calendar'></i> Daftar Jadwal
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

// Tutup koneksi database
mysqli_close($conn);

// Function untuk membuat tabel jadwal dengan layout portrait
function createJadwalTableWithMultiCell($pdf, $data) {
    // Header kolom untuk laporan jadwal - 7 kolom sesuai preview
    $headers = ['NO', 'TANGGAL', 'HARI', 'WAKTU', 'KELAS', 'INSTRUKTUR', 'STATUS'];
    
    // Lebar kolom untuk portrait (total ~190mm) - disesuaikan dengan preview
    $widths = [12, 22, 18, 25, 40, 45, 18];
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $no = 1;
    $today = date('Y-m-d');
    
    foreach ($data as $row) {
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 248, 248);
            $fill = true;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = true;
        }
        
        // Persiapkan data
        $tanggal = date('d/m/Y', strtotime($row['tanggal']));
        $hari = $row['hari_indonesia'] ?? '';
        $waktu = date('H:i', strtotime($row['waktu_mulai'])) . '-' . date('H:i', strtotime($row['waktu_selesai']));
        $kelas = $row['nama_kelas'] ?? '';
        $instruktur = $row['nama_instruktur'] ?? 'Belum Ditentukan';
        
        // Status berdasarkan tanggal
        $tanggal_jadwal = $row['tanggal'];
        if ($tanggal_jadwal < $today) {
            $status = 'Selesai';
        } elseif ($tanggal_jadwal == $today) {
            $status = 'Hari Ini';
        } else {
            $status = 'Terjadwal';
        }
        
        // Truncate text jika terlalu panjang
        $kelas = truncateText($kelas, 22);
        $instruktur = truncateText($instruktur, 24);
        
        $rowHeight = 7;
        
        // Render row
        $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], $rowHeight, $tanggal, 1, 0, 'C', $fill);
        $pdf->Cell($widths[2], $rowHeight, $hari, 1, 0, 'C', $fill);
        $pdf->Cell($widths[3], $rowHeight, $waktu, 1, 0, 'C', $fill);
        $pdf->Cell($widths[4], $rowHeight, $kelas, 1, 0, 'L', $fill);
        $pdf->Cell($widths[5], $rowHeight, $instruktur, 1, 0, 'L', $fill);
        $pdf->Cell($widths[6], $rowHeight, $status, 1, 0, 'C', $fill);
        
        $pdf->Ln();
    }
}

function truncateText($text, $maxLength) {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    return substr($text, 0, $maxLength - 3) . '...';
}
?>