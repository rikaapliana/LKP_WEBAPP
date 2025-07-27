<?php
// File: pages/admin/pengguna/cetak_laporan.php
// Cetak laporan pengguna menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();
$activePage = 'laporan-pengguna'; 

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterRole = isset($_GET['role']) ? $_GET['role'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterRole)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(u.username LIKE ? OR COALESCE(a.nama, i.nama, s.nama) LIKE ? OR COALESCE(a.email, i.email, s.email) LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data user dengan join ke semua tabel role
$query = "SELECT u.id_user, u.username, u.role, u.created_at,
          CASE 
            WHEN u.role = 'admin' THEN a.nama
            WHEN u.role = 'instruktur' THEN i.nama
            WHEN u.role = 'siswa' THEN s.nama
            ELSE NULL
          END as nama_lengkap,
          CASE 
            WHEN u.role = 'admin' THEN a.email
            WHEN u.role = 'instruktur' THEN i.email
            WHEN u.role = 'siswa' THEN s.email
            ELSE NULL
          END as email,
          CASE 
            WHEN u.role = 'instruktur' THEN i.status_aktif
            WHEN u.role = 'siswa' THEN s.status_aktif
            ELSE 'aktif'
          END as status_aktif,
          CASE 
            WHEN u.role = 'admin' THEN a.no_hp
            WHEN u.role = 'siswa' THEN s.no_hp
            ELSE NULL
          END as no_hp
          FROM user u 
          LEFT JOIN admin a ON u.id_user = a.id_user AND u.role = 'admin'
          LEFT JOIN instruktur i ON u.id_user = i.id_user AND u.role = 'instruktur'
          LEFT JOIN siswa s ON u.id_user = s.id_user AND u.role = 'siswa'";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

// Filter status setelah CASE statement
if (!empty($filterStatus)) {
    if (!empty($whereConditions)) {
        $query .= " AND ";
    } else {
        $query .= " WHERE ";
    }
    $query .= "CASE 
               WHEN u.role = 'instruktur' THEN i.status_aktif
               WHEN u.role = 'siswa' THEN s.status_aktif
               ELSE 'aktif'
               END = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY u.role ASC, COALESCE(a.nama, i.nama, s.nama) ASC";

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

    $totalPengguna = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Pengguna</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterRole)) {
    $filter_info[] = "Role: " . ucfirst(htmlspecialchars($filterRole));
}
if (!empty($filterStatus)) {
    $filter_info[] = "Status: " . ucfirst(htmlspecialchars($filterStatus));
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Auto pilih orientation - 6 kolom cocok untuk portrait
    $pdf = LKP_ReportFactory::createPendaftarReport(); // Gunakan yang sudah ada (6 kolom)
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Pengguna',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalPengguna,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data pengguna dengan MultiCell untuk teks panjang
    if (!empty($dataArray)) {
        // Custom MultiCell Table untuk Pengguna
        createPenggunaTableWithMultiCell($pdf, $dataArray);
        
        // Tambah detail tambahan di bawah tabel
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik role
        $stats_role = [];
        $stats_status = [];
        foreach ($dataArray as $row) {
            $role = $row['role'] ?? 'Tidak Diketahui';
            $status = $row['status_aktif'] ?? 'Tidak Diketahui';
            $stats_role[$role] = ($stats_role[$role] ?? 0) + 1;
            $stats_status[$status] = ($stats_status[$status] ?? 0) + 1;
        }
        
        $no_ringkasan = 1;
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Pengguna: ' . $totalPengguna . ' akun', 0, 1, 'L');
        $no_ringkasan++;
        
        // Stats berdasarkan role
        foreach ($stats_role as $role => $count) {
            $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' ' . ucfirst($role) . ': ' . $count . ' akun', 0, 1, 'L');
            $no_ringkasan++;
        }
        
        // Stats berdasarkan status (hanya jika ada instruktur/siswa)
        if (count($stats_status) > 1) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, 'Status Keaktifan:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            
            foreach ($stats_status as $status => $count) {
                $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
                $pdf->Cell(0, 4, ' ' . ucfirst($status) . ': ' . $count . ' orang', 0, 1, 'L');
                $no_ringkasan++;
            }
        }
        
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data pengguna yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Pengguna'];
    
    if (!empty($filterRole)) {
        $filename_parts[] = ucfirst($filterRole);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan pengguna.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Pengguna
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
                                <strong>Total Records:</strong> " . $totalPengguna . "<br>
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

// Function untuk membuat tabel dengan MultiCell dan Merge Cell untuk baris ke-2
function createPenggunaTableWithMultiCell($pdf, $data) {
    // Header kolom untuk laporan pengguna
    $headers = ['NO', 'USERNAME', 'NAMA LENGKAP', 'ROLE', 'EMAIL', 'STATUS'];
    
    // Lebar kolom yang optimal untuk portrait (total ~190mm)
    $widths = [12, 35, 45, 20, 50, 28];
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel dengan MultiCell untuk handle teks panjang
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $no = 1;
    foreach ($data as $row) {
        // Persiapkan data untuk split text
        $usernameData = prepareUsernameData($row['username'] ?? '');
        $namaData = prepareNameData($row['nama_lengkap'] ?? 'Belum diatur');
        $emailData = prepareEmailData($row['email'] ?? 'Belum diatur');
        
        // Tentukan apakah perlu 2 baris
        $needTwoRows = $usernameData['needSplit'] || $namaData['needSplit'] || $emailData['needSplit'];
        $rowHeight = $needTwoRows ? 7 : 7; // Tinggi per sub-baris
        
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 248, 248);
            $fill = true;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = true;
        }
        
        // BARIS PERTAMA
        $yStart = $pdf->GetY();
        
        // NO - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        }
        
        // USERNAME - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $usernameData['line1'], 1, 0, 'L', $fill);
        
        // NAMA - baris pertama
        $pdf->Cell($widths[2], $rowHeight, $namaData['line1'], 1, 0, 'L', $fill);
        
        // ROLE - merge jika ada 2 baris
        $roleText = ucfirst($row['role'] ?? '');
        if ($needTwoRows) {
            $pdf->Cell($widths[3], $rowHeight * 2, $roleText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[3], $rowHeight, $roleText, 1, 0, 'C', $fill);
        }
        
        // EMAIL - baris pertama
        $pdf->Cell($widths[4], $rowHeight, $emailData['line1'], 1, 0, 'L', $fill);
        
        // STATUS - merge jika ada 2 baris
        $statusText = ucfirst($row['status_aktif'] ?? 'Aktif');
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $statusText, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $statusText, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            // Skip kolom yang sudah di-merge (NO, ROLE, STATUS)
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // USERNAME - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $usernameData['line2'], 1, 0, 'L', $fill);
            
            // NAMA - baris kedua
            $pdf->Cell($widths[2], $rowHeight, $namaData['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[3], 0, '', 0, 0); // ROLE - kosong
            
            // EMAIL - baris kedua
            $pdf->Cell($widths[4], $rowHeight, $emailData['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[5], 0, '', 0, 0); // STATUS - kosong
            $pdf->Ln();
        }
    }
}

function prepareUsernameData($username) {
    $maxChars = 20; // Maksimal karakter per baris untuk username
    
    if (strlen($username) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $username,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata atau posisi
    $line1 = substr($username, 0, $maxChars);
    $line2 = substr($username, $maxChars);
    
    // Jika line2 masih terlalu panjang, potong
    if (strlen($line2) > $maxChars) {
        $line2 = substr($line2, 0, $maxChars - 3) . '...';
    }
    
    return [
        'needSplit' => true,
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareNameData($nama) {
    $maxChars = 22; // Maksimal karakter per baris untuk nama
    
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

function prepareEmailData($email) {
    $maxChars = 30; // Maksimal karakter per baris untuk email
    
    if (strlen($email) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $email,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan @ untuk email
    if (strpos($email, '@') !== false) {
        $parts = explode('@', $email);
        $localPart = $parts[0];
        $domainPart = '@' . $parts[1];
        
        if (strlen($localPart) <= $maxChars - 5) {
            $line1 = $localPart;
            $line2 = $domainPart;
        } else {
            $line1 = substr($email, 0, $maxChars);
            $line2 = substr($email, $maxChars);
        }
    } else {
        // Jika bukan email yang valid, split biasa
        $line1 = substr($email, 0, $maxChars);
        $line2 = substr($email, $maxChars);
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