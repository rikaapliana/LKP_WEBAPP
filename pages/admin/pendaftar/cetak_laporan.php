<?php
// File: pages/admin/pendaftar/cetak_laporan.php
// Cetak laporan pendaftar menggunakan library LKP_PDF

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$filterPendidikan = isset($_GET['pendidikan']) ? $_GET['pendidikan'] : '';
$filterJamPilihan = isset($_GET['jam_pilihan']) ? $_GET['jam_pilihan'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterStatus)) {
    $whereConditions[] = "p.status_pendaftaran = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "s";
}

if (!empty($filterJK)) {
    $whereConditions[] = "p.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($filterPendidikan)) {
    $whereConditions[] = "p.pendidikan_terakhir = ?";
    $params[] = $filterPendidikan;
    $types .= "s";
}

if (!empty($filterJamPilihan)) {
    $whereConditions[] = "p.jam_pilihan = ?";
    $params[] = $filterJamPilihan;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(p.nama_pendaftar LIKE ? OR p.nik LIKE ? OR p.email LIKE ? OR p.tempat_lahir LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

// Query data pendaftar - data penting untuk laporan
$query = "SELECT p.nik, p.nama_pendaftar, p.tempat_lahir, p.tanggal_lahir, 
                 p.jenis_kelamin, p.pendidikan_terakhir, p.no_hp, p.email, 
                 p.jam_pilihan, p.status_pendaftaran, g.nama_gelombang 
          FROM pendaftar p 
          LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY p.nama_pendaftar ASC";

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

    $totalPendaftar = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Pendaftar</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterStatus)) {
    $filter_info[] = "Status: " . ucfirst(str_replace('_', ' ', $filterStatus));
}
if (!empty($filterGelombang)) {
    $filter_info[] = "Gelombang: " . htmlspecialchars($filterGelombang);
}
if (!empty($filterJK)) {
    $filter_info[] = "Jenis Kelamin: " . htmlspecialchars($filterJK);
}
if (!empty($filterPendidikan)) {
    $filter_info[] = "Pendidikan: " . htmlspecialchars($filterPendidikan);
}
if (!empty($filterJamPilihan)) {
    $filter_info[] = "Jam Pilihan: " . htmlspecialchars($filterJamPilihan);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Auto pilih orientation - 6 kolom cocok untuk portrait
    $pdf = LKP_ReportFactory::createPendaftarReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Pendaftar',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalPendaftar,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data pendaftar menggunakan createTable yang fleksibel
    if (!empty($dataArray)) {
        // Header kolom untuk laporan pendaftar (6 kolom - portrait)
        $headers = ['NO', 'NIK', 'NAMA LENGKAP', 'TEMPAT LAHIR', 'PENDIDIKAN', 'STATUS'];
        
        // Lebar kolom untuk portrait (total ~190mm)
        $column_widths = [15, 35, 50, 35, 25, 30];
        
        // Transform data untuk tabel
        $table_data = [];
        $no = 1;
        foreach ($dataArray as $row) {
            $table_data[] = [
                $no++,
                $row['nik'] ?? '',
                $row['nama_pendaftar'] ?? '',
                $row['tempat_lahir'] ?? '',
                $row['pendidikan_terakhir'] ?? '',
                $row['status_pendaftaran'] ?? ''
            ];
        }
        
        // Options untuk styling tabel
        $options = [
            'header_bg' => [70, 130, 180],
            'header_text' => [255, 255, 255],
            'row_bg_1' => [255, 255, 255],
            'row_bg_2' => [248, 248, 248],
            'border' => true,
            'zebra' => true,
            'font_size' => 8,
            'header_font_size' => 9,
            'cell_height' => 7,
            'header_height' => 8
        ];
        
        $pdf->createTable($headers, $table_data, $column_widths, $options);
        
        // Tambah detail tambahan di bawah tabel
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik status pendaftaran
        $stats = [];
        foreach ($dataArray as $row) {
            $status = $row['status_pendaftaran'] ?? 'Tidak Diketahui';
            $stats[$status] = ($stats[$status] ?? 0) + 1;
        }
        
        $no_ringkasan = 1;
        foreach ($stats as $status => $count) {
            $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' ' . ucfirst(str_replace('_', ' ', $status)) . ': ' . $count . ' orang', 0, 1, 'L');
            $no_ringkasan++;
        }
        
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data pendaftar yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Pendaftar'];
    
    if (!empty($filterGelombang)) {
        $filename_parts[] = str_replace(' ', '_', $filterGelombang);
    }
    if (!empty($filterStatus)) {
        $filename_parts[] = str_replace(' ', '_', ucfirst($filterStatus));
    }
    if (!empty($filterPendidikan)) {
        $filename_parts[] = $filterPendidikan;
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan pendaftar.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Pendaftar
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
                                <strong>Total Records:</strong> " . $totalPendaftar . "<br>
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