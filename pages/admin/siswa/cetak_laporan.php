<?php
// File: pages/admin/siswa/cetak_laporan.php
// Cetak laporan siswa menggunakan library LKP_PDF dengan data penting saja

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter yang sama seperti index
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterStatus)) {
    $whereConditions[] = "s.status_aktif = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
    $types .= "s";
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "s";
}

if (!empty($filterJK)) {
    $whereConditions[] = "s.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ? OR s.email LIKE ? OR s.tempat_lahir LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

// Query data siswa - HANYA DATA PENTING untuk laporan
$query = "SELECT s.nik, s.nama, s.tempat_lahir, s.tanggal_lahir, s.jenis_kelamin, 
                 s.pendidikan_terakhir, s.no_hp, s.email, s.status_aktif,
                 k.nama_kelas, g.nama_gelombang 
          FROM siswa s 
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY s.nama ASC";

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

    $totalSiswa = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Siswa</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterStatus)) {
    $filter_info[] = "Status: " . ucfirst($filterStatus);
}
if (!empty($filterKelas)) {
    $filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);
}
if (!empty($filterGelombang)) {
    $filter_info[] = "Gelombang: " . htmlspecialchars($filterGelombang);
}
if (!empty($filterJK)) {
    $filter_info[] = "Jenis Kelamin: " . htmlspecialchars($filterJK);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Auto pilih orientation berdasarkan jumlah kolom
    // Untuk laporan siswa: 7 kolom (landscape) atau 6 kolom (portrait)
    $pdf = LKP_ReportFactory::createSiswaReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Siswa',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalSiswa,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data
    if (!empty($dataArray)) {
        $pdf->createSiswaTable($dataArray);
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data siswa yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Siswa'];
    
    if (!empty($filterKelas)) {
        $filename_parts[] = str_replace(' ', '_', $filterKelas);
    }
    if (!empty($filterGelombang)) {
        $filename_parts[] = str_replace(' ', '_', $filterGelombang);
    }
    if (!empty($filterStatus)) {
        $filename_parts[] = ucfirst($filterStatus);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan siswa.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Siswa
                                </a>
                                <a href='debug_cetak.php' class='btn btn-warning'>
                                    <i class='bi bi-tools'></i> Debug System
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
                                <strong>Filter Applied:</strong> " . (!empty($filter_info) ? implode(', ', $filter_info) : 'Tidak ada filter') . "<br>
                                <strong>Total Records:</strong> " . $totalSiswa . "<br>
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
?>