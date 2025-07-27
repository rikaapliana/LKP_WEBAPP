<?php
// File: pages/admin/absensi/cetak_laporan.php
// Cetak laporan absensi menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();
$activePage = 'laporan-absensi'; 

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$tipeAbsensi = isset($_GET['tipe']) ? $_GET['tipe'] : 'siswa';
if (!in_array($tipeAbsensi, ['siswa', 'instruktur'])) {
    $tipeAbsensi = 'siswa';
}

$filterTanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query berdasarkan tipe absensi
if ($tipeAbsensi == 'siswa') {
    // Query untuk absensi siswa
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if (!empty($filterTanggal)) {
        $whereConditions[] = "DATE(a.waktu_absen) = ?";
        $params[] = $filterTanggal;
        $types .= "s";
    }
    
    if (!empty($filterKelas)) {
        $whereConditions[] = "k.nama_kelas = ?";
        $params[] = $filterKelas;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam]);
        $types .= "ss";
    }
    
    // Main query
    $query = "SELECT a.id_absen, a.status, a.waktu_absen,
              s.nama as nama_lengkap, s.nik,
              k.nama_kelas,
              j.tanggal as tanggal_jadwal, j.waktu_mulai, j.waktu_selesai
              FROM absensi_siswa a
              JOIN siswa s ON a.id_siswa = s.id_siswa
              LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
              LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query .= " ORDER BY a.waktu_absen DESC";
    
} else {
    // Query untuk absensi instruktur
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if (!empty($filterTanggal)) {
        $whereConditions[] = "DATE(a.tanggal) = ?";
        $params[] = $filterTanggal;
        $types .= "s";
    }
    
    if (!empty($filterKelas)) {
        $whereConditions[] = "k.nama_kelas = ?";
        $params[] = $filterKelas;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(i.nama LIKE ? OR i.nik LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam]);
        $types .= "ss";
    }
    
    // Main query
    $query = "SELECT a.id_absen, a.status, a.tanggal, a.waktu, a.keterangan,
              i.nama as nama_lengkap, i.nik,
              k.nama_kelas,
              j.tanggal as tanggal_jadwal, j.waktu_mulai, j.waktu_selesai
              FROM absensi_instruktur a
              JOIN instruktur i ON a.id_instruktur = i.id_instruktur
              LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
              LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query .= " ORDER BY a.tanggal DESC, a.waktu DESC";
}

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

    $totalAbsensi = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Data Absensi</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
$filter_info[] = "Tipe: " . ucfirst(htmlspecialchars($tipeAbsensi));

if (!empty($filterTanggal)) {
    $filter_info[] = "Tanggal: " . date('d/m/Y', strtotime($filterTanggal));
}
if (!empty($filterKelas)) {
    $filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Gunakan factory method baru untuk absensi
    $pdf = LKP_ReportFactory::createAbsensiReport(); 
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Absensi ' . ucfirst($tipeAbsensi),
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalAbsensi,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data absensi dengan MultiCell untuk teks panjang
    if (!empty($dataArray)) {
        // Custom MultiCell Table untuk Absensi
        createAbsensiTableWithMultiCell($pdf, $dataArray, $tipeAbsensi);
        
        // Tambah detail tambahan di bawah tabel
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik status
        $stats_status = [];
        $stats_kelas = [];
        foreach ($dataArray as $row) {
            $status = $row['status'] ?? 'Tidak Diketahui';
            $kelas = $row['nama_kelas'] ?? 'Tidak Ada';
            $stats_status[$status] = ($stats_status[$status] ?? 0) + 1;
            $stats_kelas[$kelas] = ($stats_kelas[$kelas] ?? 0) + 1;
        }
        
        $no_ringkasan = 1;
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Absensi: ' . $totalAbsensi . ' record', 0, 1, 'L');
        $no_ringkasan++;
        
        // Stats berdasarkan status
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 4, 'Berdasarkan Status:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        foreach ($stats_status as $status => $count) {
            $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' ' . ucfirst($status) . ': ' . $count . ' orang', 0, 1, 'L');
            $no_ringkasan++;
        }
        
        // Stats berdasarkan kelas (jika ada variasi kelas)
        if (count($stats_kelas) > 1) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, 'Berdasarkan Kelas:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            
            foreach ($stats_kelas as $kelas => $count) {
                if ($kelas != 'Tidak Ada') {
                    $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
                    $pdf->Cell(0, 4, ' ' . htmlspecialchars($kelas) . ': ' . $count . ' record', 0, 1, 'L');
                    $no_ringkasan++;
                }
            }
        }
        
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data absensi yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Absensi', ucfirst($tipeAbsensi)];
    
    if (!empty($filterTanggal)) {
        $filename_parts[] = date('Y-m-d', strtotime($filterTanggal));
    }
    if (!empty($filterKelas)) {
        $filename_parts[] = str_replace(' ', '_', $filterKelas);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan absensi.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <h6 class='mt-4'>Kemungkinan Penyebab:</h6>
                            <ul class='small'>
                                <li><strong>Library FPDF:</strong> File library tidak ditemukan atau rusak</li>
                                <li><strong>File Logo:</strong> Logo LKP tidak ditemukan di lokasi yang ditentukan</li>
                                <li><strong>Memory Limit:</strong> Data terlalu banyak untuk diproses sekaligus</li>
                                <li><strong>Database:</strong> Koneksi database bermasalah</li>
                                <li><strong>Permission:</strong> Tidak ada izin untuk menulis file</li>
                            </ul>
                            
                            <h6 class='mt-4'>Solusi yang Bisa Dicoba:</h6>
                            <ul class='small'>
                                <li>Gunakan filter untuk mengurangi jumlah data</li>
                                <li>Refresh halaman dan coba lagi</li>
                                <li>Hubungi administrator sistem jika masalah berlanjut</li>
                            </ul>
                            
                            <hr>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-clipboard-check'></i> Data Absensi
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Debug Info untuk Development -->
                    <div class='card mt-4'>
                        <div class='card-header'>
                            <h6 class='mb-0'>
                                <i class='bi bi-info-circle'></i> 
                                Debug Information
                            </h6>
                        </div>
                        <div class='card-body'>
                            <small class='text-muted'>
                                <strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>
                                <strong>Tipe Absensi:</strong> " . ucfirst($tipeAbsensi) . "<br>
                                <strong>Filter Applied:</strong> " . (!empty($filter_info) ? implode(', ', $filter_info) : 'Tidak ada filter') . "<br>
                                <strong>Total Records:</strong> " . $totalAbsensi . "<br>
                                <strong>User:</strong> " . ($_SESSION['nama'] ?? 'Unknown') . "<br>
                                <strong>PHP Version:</strong> " . phpversion() . "<br>
                                <strong>Memory Usage:</strong> " . memory_get_usage(true) / 1024 / 1024 . " MB
                            </small>
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

// Function untuk membuat tabel dengan MultiCell dan Merge Cell
function createAbsensiTableWithMultiCell($pdf, $data, $tipeAbsensi) {
    if ($tipeAbsensi == 'siswa') {
        // Header kolom untuk absensi siswa
        $headers = ['NO', 'NAMA SISWA', 'NIK', 'KELAS', 'TANGGAL', 'WAKTU', 'STATUS'];
        // Lebar kolom yang optimal untuk portrait (total ~190mm)
        $widths = [12, 45, 25, 30, 25, 20, 23];
    } else {
        // Header kolom untuk absensi instruktur
        $headers = ['NO', 'NAMA INSTRUKTUR', 'NIK', 'KELAS', 'TANGGAL', 'WAKTU', 'STATUS', 'KET'];
        // Lebar kolom yang optimal untuk portrait (total ~190mm)
        $widths = [10, 38, 22, 25, 22, 18, 20, 25];
    }
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel dengan MultiCell untuk handle teks panjang
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    
    $no = 1;
    foreach ($data as $row) {
        // Persiapkan data untuk split text
        $namaData = prepareNameDataAbsensi($row['nama_lengkap'] ?? 'Tidak Diketahui');
        $kelasData = prepareKelasData($row['nama_kelas'] ?? '-');
        
        // Untuk instruktur, siapkan data keterangan
        $keteranganData = [];
        if ($tipeAbsensi == 'instruktur') {
            $keteranganData = prepareKeteranganData($row['keterangan'] ?? '-');
        }
        
        // Tentukan apakah perlu 2 baris
        $needTwoRows = $namaData['needSplit'] || $kelasData['needSplit'] || 
                       ($tipeAbsensi == 'instruktur' && $keteranganData['needSplit']);
        
        $rowHeight = $needTwoRows ? 6 : 6; // Tinggi per sub-baris
        
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 248, 248);
            $fill = true;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = true;
        }
        
        // BARIS PERTAMA
        // NO - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        }
        
        // NAMA - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $namaData['line1'], 1, 0, 'L', $fill);
        
        // NIK - merge jika ada 2 baris
        $nikText = $row['nik'] ?? '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[2], $rowHeight * 2, $nikText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[2], $rowHeight, $nikText, 1, 0, 'C', $fill);
        }
        
        // KELAS - baris pertama
        $pdf->Cell($widths[3], $rowHeight, $kelasData['line1'], 1, 0, 'C', $fill);
        
        // TANGGAL - merge jika ada 2 baris
        if ($tipeAbsensi == 'siswa') {
            $tanggalText = date('d/m/Y', strtotime($row['waktu_absen']));
        } else {
            $tanggalText = date('d/m/Y', strtotime($row['tanggal']));
        }
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $tanggalText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $tanggalText, 1, 0, 'C', $fill);
        }
        
        // WAKTU - merge jika ada 2 baris
        if ($tipeAbsensi == 'siswa') {
            $waktuText = date('H:i', strtotime($row['waktu_absen']));
        } else {
            $waktuText = $row['waktu'] ? date('H:i', strtotime($row['waktu'])) : '-';
        }
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $waktuText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $waktuText, 1, 0, 'C', $fill);
        }
        
        // STATUS - merge jika ada 2 baris
        $statusText = ucfirst($row['status'] ?? 'Tidak Diketahui');
        if ($needTwoRows) {
            $pdf->Cell($widths[6], $rowHeight * 2, $statusText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[6], $rowHeight, $statusText, 1, 0, 'C', $fill);
        }
        
        // KETERANGAN (hanya untuk instruktur)
        if ($tipeAbsensi == 'instruktur') {
            if ($needTwoRows) {
                $pdf->Cell($widths[7], $rowHeight, $keteranganData['line1'], 1, 0, 'L', $fill);
            } else {
                $pdf->Cell($widths[7], $rowHeight, $keteranganData['line1'], 1, 0, 'L', $fill);
            }
        }
        
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            // Skip kolom yang sudah di-merge
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // NAMA - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $namaData['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[2], 0, '', 0, 0); // NIK - kosong
            
            // KELAS - baris kedua
            $pdf->Cell($widths[3], $rowHeight, $kelasData['line2'], 1, 0, 'C', $fill);
            
            $pdf->Cell($widths[4], 0, '', 0, 0); // TANGGAL - kosong
            $pdf->Cell($widths[5], 0, '', 0, 0); // WAKTU - kosong
            $pdf->Cell($widths[6], 0, '', 0, 0); // STATUS - kosong
            
            // KETERANGAN - baris kedua (hanya untuk instruktur)
            if ($tipeAbsensi == 'instruktur') {
                $pdf->Cell($widths[7], $rowHeight, $keteranganData['line2'], 1, 0, 'L', $fill);
            }
            
            $pdf->Ln();
        }
    }
}

function prepareNameDataAbsensi($nama) {
    $maxChars = 25; // Maksimal karakter per baris untuk nama
    
    if (strlen($nama) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $nama,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $nama);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    // Jika line2 masih terlalu panjang, potong
    if (strlen($line2) > $maxChars) {
        $line2 = substr($line2, 0, $maxChars - 3) . '...';
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareKelasData($kelas) {
    $maxChars = 18; // Maksimal karakter per baris untuk kelas
    
    if (strlen($kelas) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $kelas,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata atau spasi
    $words = explode(' ', $kelas);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    // Jika line2 masih terlalu panjang, potong
    if (strlen($line2) > $maxChars) {
        $line2 = substr($line2, 0, $maxChars - 3) . '...';
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareKeteranganData($keterangan) {
    $maxChars = 15; // Maksimal karakter per baris untuk keterangan
    
    if (strlen($keterangan) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $keterangan,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $keterangan);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    // Jika line2 masih terlalu panjang, potong
    if (strlen($line2) > $maxChars) {
        $line2 = substr($line2, 0, $maxChars - 3) . '...';
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}
?>