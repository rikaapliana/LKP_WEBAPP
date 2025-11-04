<?php
// File: pages/admin/laporan/cetak_laporan.php
// Cetak laporan manajemen lengkap menggunakan library LKP_PDF

session_start();
require_once '../../includes/auth.php';
requireAdminAuth();

include '../../includes/db.php';
require_once('../../vendor/fpdf/lkp_pdf.php');

// SET TIMEZONE
date_default_timezone_set('Asia/Makassar');

/**
 * Fungsi format tanggal Indonesia
 */
function formatTanggalIndonesia($format, $timestamp = null) {
    $timestamp = $timestamp === null ? time() : (is_numeric($timestamp) ? $timestamp : strtotime($timestamp));
    $date_str = date($format, $timestamp);
    
    $hariInggris = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    $bulanInggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $bulanIndonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $date_str_id = str_replace($hariInggris, $hariIndonesia, $date_str);
    $date_str_id = str_replace($bulanInggris, $bulanIndonesia, $date_str_id);
    
    return $date_str_id;
}

// Ambil parameter filter tahun
$filter_tahun = isset($_GET['tahun']) && $_GET['tahun'] != '' ? $_GET['tahun'] : 'semua';

$where_gelombang = "";
if($filter_tahun != 'semua') {
    $where_gelombang = " WHERE g.tahun = '$filter_tahun'";
}

// Build filter info untuk header PDF
$filter_info = [];
if($filter_tahun != 'semua') {
    $filter_info[] = "Tahun: " . $filter_tahun;
} else {
    $filter_info[] = "Periode: Semua Tahun";
}

try {
    // ========================================
    // QUERY SEMUA DATA YANG DIPERLUKAN
    // ========================================
    
    // DATA PENDAFTARAN
    $query_pendaftar = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_pendaftar = mysqli_fetch_assoc(mysqli_query($conn, $query_pendaftar))['total'];

    $query_belum_verif = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Belum di Verifikasi'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $pendaftar_belum_verif = mysqli_fetch_assoc(mysqli_query($conn, $query_belum_verif))['total'];

    $query_terverifikasi = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Terverifikasi'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $pendaftar_terverifikasi = mysqli_fetch_assoc(mysqli_query($conn, $query_terverifikasi))['total'];

    $query_diterima = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Diterima'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $pendaftar_diterima = mysqli_fetch_assoc(mysqli_query($conn, $query_diterima))['total'];

    // DATA SISWA
    $query_siswa_aktif = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $total_siswa_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_aktif))['total'];

    $query_alumni = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.status_aktif = 'nonaktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $total_alumni = mysqli_fetch_assoc(mysqli_query($conn, $query_alumni))['total'];

    $query_laki = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.jenis_kelamin = 'Laki-Laki' AND s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $siswa_laki = mysqli_fetch_assoc(mysqli_query($conn, $query_laki))['total'];

    $query_perempuan = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.jenis_kelamin = 'Perempuan' AND s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $siswa_perempuan = mysqli_fetch_assoc(mysqli_query($conn, $query_perempuan))['total'];

    // DATA INSTRUKTUR
    $total_instruktur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_instruktur) as total FROM instruktur"))['total'];
    $instruktur_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_instruktur) as total FROM instruktur WHERE status_aktif = 'aktif'"))['total'];

    // DATA KELAS & GELOMBANG
    $query_kelas = "SELECT COUNT(k.id_kelas) as total FROM kelas k JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_kelas = mysqli_fetch_assoc(mysqli_query($conn, $query_kelas))['total'];

    $query_gelombang_aktif = "SELECT COUNT(id_gelombang) as total FROM gelombang WHERE status = 'aktif'" . ($filter_tahun != 'semua' ? " AND tahun = '$filter_tahun'" : "");
    $gelombang_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_gelombang_aktif))['total'];

    $query_kapasitas = "SELECT SUM(k.kapasitas) as total FROM kelas k JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_kapasitas = mysqli_fetch_assoc(mysqli_query($conn, $query_kapasitas))['total'] ?? 0;
    $utilisasi_kelas = ($total_kapasitas > 0) ? round(($total_siswa_aktif / $total_kapasitas) * 100, 1) : 0;

    // DATA KELULUSAN
    $query_lulus = "SELECT COUNT(n.id_nilai) as total FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.status_kelulusan = 'lulus'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $total_lulus = mysqli_fetch_assoc(mysqli_query($conn, $query_lulus))['total'];

    $query_tidak_lulus = "SELECT COUNT(n.id_nilai) as total FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.status_kelulusan = 'tidak lulus'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $total_tidak_lulus = mysqli_fetch_assoc(mysqli_query($conn, $query_tidak_lulus))['total'];
    $total_dinilai = $total_lulus + $total_tidak_lulus;
    $tingkat_kelulusan = ($total_dinilai > 0) ? round(($total_lulus / $total_dinilai) * 100, 1) : 0;

    // NILAI RATA-RATA
    $query_avg = "SELECT AVG(n.rata_rata) as avg FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.rata_rata IS NOT NULL" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $avg_nilai = mysqli_fetch_assoc(mysqli_query($conn, $query_avg))['avg'];
    $rata_rata_keseluruhan = $avg_nilai ? round($avg_nilai, 2) : 0;

    // DATA KEHADIRAN SISWA
    $query_absen_siswa = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . ($filter_tahun != 'semua' ? " WHERE g.tahun = '$filter_tahun'" : "");
    $total_absen_siswa = mysqli_fetch_assoc(mysqli_query($conn, $query_absen_siswa))['total'];

    $query_siswa_hadir = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE abs.status = 'hadir'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $siswa_hadir = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_hadir))['total'];
    $tingkat_kehadiran_siswa = ($total_absen_siswa > 0) ? round(($siswa_hadir / $total_absen_siswa) * 100, 1) : 0;

    // DATA KEHADIRAN INSTRUKTUR
    $query_absen_instruktur = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_absen_instruktur = mysqli_fetch_assoc(mysqli_query($conn, $query_absen_instruktur))['total'];

    $query_instruktur_hadir = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE ai.status = 'hadir'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $instruktur_hadir = mysqli_fetch_assoc(mysqli_query($conn, $query_instruktur_hadir))['total'];
    $tingkat_kehadiran_instruktur = ($total_absen_instruktur > 0) ? round(($instruktur_hadir / $total_absen_instruktur) * 100, 1) : 0;

    // DATA EVALUASI
    $query_evaluasi_aktif = "SELECT COUNT(pe.id_periode) as total FROM periode_evaluasi pe JOIN gelombang g ON pe.id_gelombang = g.id_gelombang WHERE pe.status = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
    $evaluasi_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_evaluasi_aktif))['total'];

    // DATA MATERI & JADWAL
    $query_materi = "SELECT COUNT(m.id_materi) as total FROM materi m JOIN kelas k ON m.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_materi = mysqli_fetch_assoc(mysqli_query($conn, $query_materi))['total'];

    $query_jadwal = "SELECT COUNT(j.id_jadwal) as total FROM jadwal j JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
    $total_jadwal = mysqli_fetch_assoc(mysqli_query($conn, $query_jadwal))['total'];

    // RASIO & KONVERSI
    $rasio_instruktur_siswa = ($instruktur_aktif > 0) ? round($total_siswa_aktif / $instruktur_aktif, 1) : 0;
    $total_siswa_keseluruhan = $total_siswa_aktif + $total_alumni;
    $tingkat_konversi = ($total_pendaftar > 0) ? round(($total_siswa_keseluruhan / $total_pendaftar) * 100, 1) : 0;

    // DATA DETAIL PER GELOMBANG
    $data_gelombang = mysqli_query($conn, "
        SELECT 
            g.nama_gelombang, g.tahun, g.status,
            COUNT(DISTINCT k.id_kelas) as jumlah_kelas,
            COUNT(DISTINCT p.id_pendaftar) as jumlah_pendaftar,
            COUNT(DISTINCT s.id_siswa) as jumlah_siswa
        FROM gelombang g
        LEFT JOIN kelas k ON g.id_gelombang = k.id_gelombang
        LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
        LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
        " . $where_gelombang . "
        GROUP BY g.id_gelombang
        ORDER BY g.tahun DESC, g.gelombang_ke DESC
    ");

    // DATA DETAIL PER KELAS
    $data_kelas = mysqli_query($conn, "
        SELECT 
            k.nama_kelas, k.kapasitas,
            g.nama_gelombang,
            i.nama as nama_instruktur,
            COUNT(DISTINCT s.id_siswa) as jumlah_siswa,
            COUNT(DISTINCT m.id_materi) as jumlah_materi,
            COUNT(DISTINCT j.id_jadwal) as jumlah_jadwal
        FROM kelas k
        LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
        LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
        LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
        LEFT JOIN materi m ON k.id_kelas = m.id_kelas
        LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
        " . $where_gelombang . "
        GROUP BY k.id_kelas
        ORDER BY g.tahun DESC, k.nama_kelas ASC
    ");

    // DATA INSTRUKTUR DETAIL
    $instruktur_where = $filter_tahun != 'semua' ? "WHERE g.tahun = '$filter_tahun'" : "";
    $data_instruktur = mysqli_query($conn, "
        SELECT 
            i.nama, i.status_aktif, i.angkatan,
            COUNT(DISTINCT k.id_kelas) as jumlah_kelas,
            COUNT(DISTINCT m.id_materi) as jumlah_materi,
            COUNT(DISTINCT ai.id_absen) as total_absen,
            SUM(CASE WHEN ai.status = 'hadir' THEN 1 ELSE 0 END) as hadir
        FROM instruktur i
        LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
        LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
        LEFT JOIN materi m ON i.id_instruktur = m.id_instruktur
        LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
        LEFT JOIN absensi_instruktur ai ON i.id_instruktur = ai.id_instruktur AND j.id_jadwal = ai.id_jadwal
        " . $instruktur_where . "
        GROUP BY i.id_instruktur
        ORDER BY i.status_aktif DESC, i.nama ASC
    ");

    // DATA KELULUSAN PER KELAS
    $data_kelulusan = mysqli_query($conn, "
        SELECT 
            k.nama_kelas, g.nama_gelombang,
            COUNT(n.id_nilai) as total_dinilai,
            SUM(CASE WHEN n.status_kelulusan = 'lulus' THEN 1 ELSE 0 END) as lulus,
            SUM(CASE WHEN n.status_kelulusan = 'tidak lulus' THEN 1 ELSE 0 END) as tidak_lulus,
            AVG(n.rata_rata) as rata_rata_kelas
        FROM nilai n
        JOIN siswa s ON n.id_siswa = s.id_siswa
        JOIN kelas k ON n.id_kelas = k.id_kelas
        JOIN gelombang g ON k.id_gelombang = g.id_gelombang
        WHERE n.status_kelulusan IS NOT NULL
        " . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "") . "
        GROUP BY k.id_kelas
        ORDER BY g.tahun DESC, k.nama_kelas ASC
    ");

    // ========================================
    // GENERATE PDF
    // ========================================
    
    $pdf = new LKP_PDF('L', 'mm', 'A4'); // Landscape untuk tampung data lebar
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'LAPORAN MANAJEMEN LEMBAGA',
        'Executive Summary & Data Operasional',
        '../../assets/img/favicon.png',
        $filter_info,
        0, // tidak perlu total record untuk laporan manajemen
        $_SESSION['nama'] ?? 'Administrator Sistem'
    );
    
    $pdf->AddPage();
    
    // ========================================
    // I. RINGKASAN EKSEKUTIF
    // ========================================
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'I. RINGKASAN EKSEKUTIF', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    // A. Sistem Pendaftaran
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'A. Sistem Pendaftaran & Penerimaan', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    $ringkasan_pendaftaran = [
        ['Total Pendaftar', $total_pendaftar],
        ['Belum Diverifikasi', $pendaftar_belum_verif],
        ['Terverifikasi', $pendaftar_terverifikasi],
        ['Diterima', $pendaftar_diterima]
    ];
    
    foreach($ringkasan_pendaftaran as $item) {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(70, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $item[1] . ' orang', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
    }
    
    $pdf->Ln(3);
    
    // B. Data Peserta Didik
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'B. Data Peserta Didik', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    $ringkasan_siswa = [
        ['Siswa Aktif', $total_siswa_aktif],
        ['Alumni', $total_alumni],
        ['Laki-Laki', $siswa_laki],
        ['Perempuan', $siswa_perempuan],
        ['Tingkat Kehadiran', $tingkat_kehadiran_siswa . '%'],
        ['Tingkat Konversi', $tingkat_konversi . '%']
    ];
    
    foreach($ringkasan_siswa as $item) {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(70, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $item[1], 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
    }
    
    $pdf->Ln(3);
    
    // C. Sumber Daya Pengajar
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'C. Sumber Daya Pengajar', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    $ringkasan_instruktur = [
        ['Total Instruktur', $total_instruktur],
        ['Instruktur Aktif', $instruktur_aktif],
        ['Tingkat Kehadiran', $tingkat_kehadiran_instruktur . '%'],
        ['Rasio Instruktur:Siswa', '1:' . $rasio_instruktur_siswa]
    ];
    
    foreach($ringkasan_instruktur as $item) {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(70, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $item[1], 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
    }
    
    $pdf->Ln(3);
    
    // D. Fasilitas & Pembelajaran
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'D. Fasilitas & Pembelajaran', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    $ringkasan_fasilitas = [
        ['Total Kelas', $total_kelas],
        ['Gelombang Aktif', $gelombang_aktif],
        ['Utilisasi Kelas', $utilisasi_kelas . '%'],
        ['Total Materi', $total_materi],
        ['Total Jadwal', $total_jadwal],
        ['Total Kapasitas', $total_kapasitas]
    ];
    
    foreach($ringkasan_fasilitas as $item) {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(70, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $item[1], 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
    }
    
    $pdf->Ln(3);
    
    // E. Evaluasi & Pencapaian
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'E. Evaluasi & Pencapaian', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    $ringkasan_evaluasi = [
        ['Evaluasi Aktif', $evaluasi_aktif],
        ['Total Lulus', $total_lulus],
        ['Tingkat Kelulusan', $tingkat_kelulusan . '%'],
        ['Rata-rata Nilai', $rata_rata_keseluruhan]
    ];
    
    foreach($ringkasan_evaluasi as $item) {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(70, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $item[1], 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
    }
    
    // ========================================
    // II. DATA PER GELOMBANG
    // ========================================
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'II. DATA PER GELOMBANG', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $headers_gelombang = ['NO', 'NAMA GELOMBANG', 'TAHUN', 'STATUS', 'JML KELAS', 'JML PENDAFTAR', 'JML SISWA'];
    $widths_gelombang = [10, 70, 20, 30, 30, 35, 30];
    
    $table_data_gelombang = [];
    $no = 1;
    while($row = mysqli_fetch_assoc($data_gelombang)) {
        $table_data_gelombang[] = [
            $no++,
            $row['nama_gelombang'],
            $row['tahun'],
            ucfirst($row['status']),
            $row['jumlah_kelas'],
            $row['jumlah_pendaftar'],
            $row['jumlah_siswa']
        ];
    }
    
    if(!empty($table_data_gelombang)) {
        $pdf->createTable($headers_gelombang, $table_data_gelombang, $widths_gelombang, [
            'header_bg' => [102, 126, 234],
            'header_text' => [255, 255, 255],
            'font_size' => 8,
            'zebra' => true
        ]);
    } else {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 10, 'Tidak ada data gelombang', 0, 1, 'C');
    }
    
    // ========================================
    // III. DATA PER KELAS
    // ========================================
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'III. DATA PER KELAS', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $headers_kelas = ['NO', 'NAMA KELAS', 'GELOMBANG', 'INSTRUKTUR', 'KAPASITAS', 'TERISI', 'UTILISASI', 'MATERI', 'JADWAL'];
    $widths_kelas = [10, 45, 40, 45, 25, 20, 25, 20, 20];
    
    $table_data_kelas = [];
    $no = 1;
    while($row = mysqli_fetch_assoc($data_kelas)) {
        $utilisasi = ($row['kapasitas'] > 0) ? round(($row['jumlah_siswa'] / $row['kapasitas']) * 100, 1) : 0;
        $table_data_kelas[] = [
            $no++,
            $row['nama_kelas'],
            $row['nama_gelombang'],
            $row['nama_instruktur'] ?? '-',
            $row['kapasitas'],
            $row['jumlah_siswa'],
            $utilisasi . '%',
            $row['jumlah_materi'],
            $row['jumlah_jadwal']
        ];
    }
    
    if(!empty($table_data_kelas)) {
        $pdf->createTable($headers_kelas, $table_data_kelas, $widths_kelas, [
            'header_bg' => [102, 126, 234],
            'header_text' => [255, 255, 255],
            'font_size' => 7,
            'zebra' => true
        ]);
    } else {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 10, 'Tidak ada data kelas', 0, 1, 'C');
    }
    
    // ========================================
    // IV. DATA INSTRUKTUR
    // ========================================
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'IV. DATA INSTRUKTUR', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $headers_instruktur = ['NO', 'NAMA INSTRUKTUR', 'STATUS', 'ANGKATAN', 'JML KELAS', 'JML MATERI', 'TOTAL ABSEN', 'KEHADIRAN'];
    $widths_instruktur = [10, 50, 30, 40, 20, 20, 25, 25];
    
    $table_data_instruktur = [];
    $no = 1;
    while($row = mysqli_fetch_assoc($data_instruktur)) {
        $tingkat_hadir = ($row['total_absen'] > 0) ? round(($row['hadir'] / $row['total_absen']) * 100, 1) : 0;
        $table_data_instruktur[] = [
            $no++,
            $row['nama'],
            ucfirst($row['status_aktif']),
            $row['angkatan'] ?? '-',
            $row['jumlah_kelas'],
            $row['jumlah_materi'],
            $row['total_absen'],
            $tingkat_hadir . '%'
        ];
    }
    
    if(!empty($table_data_instruktur)) {
        $pdf->createTable($headers_instruktur, $table_data_instruktur, $widths_instruktur, [
            'header_bg' => [102, 126, 234],
            'header_text' => [255, 255, 255],
            'font_size' => 8,
            'zebra' => true
        ]);
    } else {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 10, 'Tidak ada data instruktur', 0, 1, 'C');
    }
    
    // ========================================
    // V. DATA KELULUSAN PER KELAS
    // ========================================
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'V. DATA KELULUSAN PER KELAS', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $headers_kelulusan = ['NO', 'NAMA KELAS', 'GELOMBANG', 'TOTAL DINILAI', 'LULUS', 'TDK LULUS', 'TK. KELULUSAN', 'RATA-RATA'];
    $widths_kelulusan = [10, 55, 50, 35, 25, 30, 35, 30];
    
    $table_data_kelulusan = [];
    $no = 1;
    while($row = mysqli_fetch_assoc($data_kelulusan)) {
        $persen_lulus = ($row['total_dinilai'] > 0) ? round(($row['lulus'] / $row['total_dinilai']) * 100, 1) : 0;
        $rata_nilai = $row['rata_rata_kelas'] ? round($row['rata_rata_kelas'], 2) : 0;
        $table_data_kelulusan[] = [
            $no++,
            $row['nama_kelas'],
            $row['nama_gelombang'],
            $row['total_dinilai'],
            $row['lulus'],
            $row['tidak_lulus'],
            $persen_lulus . '%',
            $rata_nilai
        ];
    }
    
    if(!empty($table_data_kelulusan)) {
        $pdf->createTable($headers_kelulusan, $table_data_kelulusan, $widths_kelulusan, [
            'header_bg' => [102, 126, 234],
            'header_text' => [255, 255, 255],
            'font_size' => 8,
            'zebra' => true
        ]);
    } else {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 10, 'Tidak ada data kelulusan', 0, 1, 'C');
    }
    
    // ========================================
    // VI. ANALISIS & KESIMPULAN
    // ========================================
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'VI. ANALISIS & KESIMPULAN', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
    
    // KINERJA POSITIF
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 240, 255);
    $pdf->Cell(0, 7, 'KINERJA POSITIF', 0, 1, 'L', true);
    $pdf->Ln(1);
    
    $pdf->SetFont('Arial', '', 8);
    $kinerja_positif = [];
    
    if($tingkat_kelulusan >= 80) {
        $kinerja_positif[] = "Tingkat kelulusan {$tingkat_kelulusan}% menunjukkan kualitas pembelajaran yang baik";
    }
    if($tingkat_kehadiran_instruktur >= 90) {
        $kinerja_positif[] = "Kehadiran instruktur {$tingkat_kehadiran_instruktur}% menandakan dedikasi pengajar yang tinggi";
    }
    if($tingkat_konversi >= 70) {
        $kinerja_positif[] = "Tingkat konversi pendaftar ke siswa {$tingkat_konversi}% cukup efektif";
    }
    if($utilisasi_kelas >= 70) {
        $kinerja_positif[] = "Utilisasi kelas {$utilisasi_kelas}% menunjukkan optimalisasi fasilitas yang baik";
    }
    
    if(!empty($kinerja_positif)) {
        foreach($kinerja_positif as $item) {
            $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
            $pdf->MultiCell(0, 5, $item, 0, 'L');
        }
    } else {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(0, 5, 'Semua indikator menunjukkan performa yang konsisten', 0, 1, 'L');
    }
    
    $pdf->Ln(3);
    
    // AREA YANG PERLU PERHATIAN
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 240, 220);
    $pdf->Cell(0, 7, 'AREA YANG PERLU PERHATIAN', 0, 1, 'L', true);
    $pdf->Ln(1);
    
    $pdf->SetFont('Arial', '', 8);
    $perlu_perhatian = [];
    
    if($tingkat_kelulusan < 80) {
        $perlu_perhatian[] = "Tingkat kelulusan {$tingkat_kelulusan}% perlu ditingkatkan melalui pembimbingan intensif";
    }
    if($tingkat_kehadiran_siswa < 85) {
        $perlu_perhatian[] = "Kehadiran siswa {$tingkat_kehadiran_siswa}% perlu ditingkatkan dengan sistem reward";
    }
    if($utilisasi_kelas < 70) {
        $perlu_perhatian[] = "Utilisasi kelas {$utilisasi_kelas}% masih bisa dioptimalkan dengan strategi marketing";
    }
    if($pendaftar_belum_verif > 0) {
        $perlu_perhatian[] = "Terdapat {$pendaftar_belum_verif} pendaftar yang belum diverifikasi, perlu tindak lanjut";
    }
    if($rasio_instruktur_siswa > 20) {
        $perlu_perhatian[] = "Rasio instruktur:siswa 1:{$rasio_instruktur_siswa} perlu penambahan instruktur";
    }
    
    if(!empty($perlu_perhatian)) {
        foreach($perlu_perhatian as $item) {
            $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
            $pdf->MultiCell(0, 5, $item, 0, 'L');
        }
    } else {
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell(0, 5, 'Tidak ada area kritis yang memerlukan perhatian segera', 0, 1, 'L');
    }
    
    $pdf->Ln(3);
    
    // RINGKASAN STATISTIK KUNCI
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 255, 220);
    $pdf->Cell(0, 7, 'RINGKASAN STATISTIK KUNCI', 0, 1, 'L', true);
    $pdf->Ln(1);
    
    $pdf->SetFont('Arial', '', 8);
    
    $statistik_kunci = [
        ['Total Pendaftar', $total_pendaftar . ' orang'],
        ['Siswa Aktif', $total_siswa_aktif . ' orang'],
        ['Alumni', $total_alumni . ' orang'],
        ['Instruktur Aktif', $instruktur_aktif . ' orang'],
        ['Total Kelas', $total_kelas . ' kelas'],
        ['Tingkat Konversi', $tingkat_konversi . '%'],
        ['Tingkat Kelulusan', $tingkat_kelulusan . '%'],
        ['Rata-rata Nilai', $rata_rata_keseluruhan],
        ['Utilisasi Kelas', $utilisasi_kelas . '%'],
        ['Rasio Instruktur:Siswa', '1:' . $rasio_instruktur_siswa]
    ];
    
    $col_width = ($pdf->GetPageWidth() - 20) / 2; // 2 kolom
    $x_start = $pdf->GetX();
    $y_start = $pdf->GetY();
    $current_col = 0;
    
    foreach($statistik_kunci as $index => $item) {
        if($current_col == 2) {
            $current_col = 0;
            $y_start += 5;
            $pdf->SetXY($x_start, $y_start);
        }
        
        $x_pos = $x_start + ($current_col * $col_width);
        $pdf->SetXY($x_pos, $y_start);
        
        $pdf->Cell(5, 5, chr(149), 0, 0, 'L');
        $pdf->Cell($col_width - 50, 5, $item[0], 0, 0, 'L');
        $pdf->Cell(5, 5, ':', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(40, 5, $item[1], 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        $current_col++;
    }
    
    $pdf->Ln(10);
    
    // ========================================
    // TANDA TANGAN
    // ========================================
    
    $pdf->Ln(5);
    $pdf->addSignature();
    
    // ========================================
    // OUTPUT PDF
    // ========================================
    
    $filename_parts = ['Laporan_Manajemen'];
    
    if($filter_tahun != 'semua') {
        $filename_parts[] = 'Tahun_' . $filter_tahun;
    } else {
        $filename_parts[] = 'Semua_Tahun';
    }
    
    $filename_parts[] = date('Y-m-d_H-i-s');
    $filename = implode('_', $filename_parts) . '.pdf';
    
    $pdf->Output('I', $filename);
    
} catch (Exception $e) {
    // Error handling yang user-friendly
    error_log("PDF generation error in laporan manajemen: " . $e->getMessage());
    
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
                                <h6 class='alert-heading'>Gagal Membuat PDF Laporan Manajemen</h6>
                                <p>Terjadi kesalahan saat membuat file PDF laporan manajemen lembaga.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <h6 class='mt-4'>Kemungkinan Penyebab:</h6>
                            <ul class='small'>
                                <li><strong>Library FPDF:</strong> File library tidak ditemukan atau rusak</li>
                                <li><strong>File Logo:</strong> Logo LKP tidak ditemukan di lokasi yang ditentukan</li>
                                <li><strong>Memory Limit:</strong> Data terlalu banyak untuk diproses sekaligus</li>
                                <li><strong>Database:</strong> Koneksi database bermasalah atau query error</li>
                                <li><strong>Permission:</strong> Tidak ada izin untuk menulis file</li>
                            </ul>
                            
                            <h6 class='mt-4'>Solusi yang Bisa Dicoba:</h6>
                            <ul class='small'>
                                <li>Gunakan filter tahun untuk mengurangi jumlah data</li>
                                <li>Refresh halaman dan coba lagi</li>
                                <li>Pastikan koneksi database stabil</li>
                                <li>Hubungi administrator sistem jika masalah berlanjut</li>
                            </ul>
                            
                            <hr>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='laporan_manajemen.php' class='btn btn-primary'>
                                    <i class='bi bi-file-earmark-bar-graph'></i> Laporan Manajemen
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
                                <strong>Filter Tahun:</strong> " . ($filter_tahun == 'semua' ? 'Semua Tahun' : 'Tahun ' . $filter_tahun) . "<br>
                                <strong>User:</strong> " . ($_SESSION['nama'] ?? 'Unknown') . "<br>
                                <strong>PHP Version:</strong> " . phpversion() . "<br>
                                <strong>Memory Usage:</strong> " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB
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