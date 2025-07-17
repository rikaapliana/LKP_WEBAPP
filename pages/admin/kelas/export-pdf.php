<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Set timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($date = null, $withTime = false) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    // Jika date kosong atau null, gunakan tanggal hari ini
    if (empty($date)) {
        $timestamp = time();
    } else {
        $timestamp = is_string($date) ? strtotime($date) : $date;
    }
    
    $hari = date('d', $timestamp);
    $bulanNum = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    if ($withTime) {
        $jam = date('H:i:s', $timestamp);
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun . ', ' . $jam;
    } else {
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun;
    }
}

// Function helper untuk terbilang
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $terbilang = "";
    
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    }
    
    return $terbilang;
}

// Query semua data kelas tanpa filter
$query = "SELECT k.*, 
          g.nama_gelombang, g.tahun, g.status as status_gelombang,
          i.nama as nama_instruktur, i.nik as nik_instruktur,
          COUNT(DISTINCT s.id_siswa) as total_siswa
          FROM kelas k 
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
          GROUP BY k.id_kelas
          ORDER BY k.nama_kelas ASC, g.tahun DESC";

$result = mysqli_query($conn, $query);

// Hitung total kelas
$totalKelas = $result ? mysqli_num_rows($result) : 0;
$dataArray = [];

// Ambil semua data dan simpan dalam array
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dataArray[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Kelas - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body onload="window.print(); window.close();">
    <!-- Watermark -->
    <div class="report-watermark report-no-print">LKP PRADATA</div>

    <div class="report-main-container">
        <!-- Letterhead Resmi -->
        <div class="report-letterhead report-page-break-avoid">
            <div class="report-institution-logo">
                <img src="../../../assets/img/favicon.png" alt="Logo LKP Pradata Komputer">
            </div>
            <div class="report-institution-name">
                LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER
            </div>
            <div class="report-institution-address">
                Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571
            </div>
            <div class="report-institution-contact">
                Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id
            </div>
        </div>

        <!-- Document Header -->
        <div class="report-document-header report-page-break-avoid">
            <h1 class="report-document-title">Laporan Data Kelas</h1>
            <p class="report-document-subtitle">Per Tanggal <?= formatTanggalIndonesia() ?></p>
        </div>

        <!-- Document Meta -->
        <div class="report-document-meta report-page-break-avoid">
            <table class="report-meta-table">
                <tr>
                    <td class="report-meta-label">Nomor Dokumen</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= date('Y') ?>/LKP-PC/KELAS/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
                </tr>
                <tr>
                    <td class="report-meta-label">Tanggal Cetak</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= formatTanggalIndonesia(null, true) ?> WIB</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Dicetak Oleh</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value">Administrator Sistem</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Total Record</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= $totalKelas ?> (<?= terbilang($totalKelas) ?>) kelas</td>
                </tr>
            </table>
        </div>

        <!-- Data Table -->
        <table class="report-data-table">
            <thead>
                <tr>
                    <th class="report-col-no">NO</th>
                    <th style="width: 25%;">NAMA KELAS</th>
                    <th style="width: 20%;">GELOMBANG</th>
                    <th style="width: 8%;">KAPASITAS</th>
                    <th style="width: 8%;">TERISI</th>
                    <th style="width: 8%;">SISA</th>
                    <th style="width: 20%;">INSTRUKTUR</th>
                    <th style="width: 10%;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dataArray)): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($dataArray as $kelas): ?>
                        <?php
                        $sisa_kapasitas = $kelas['kapasitas'] - $kelas['total_siswa'];
                        $persentase_terisi = $kelas['kapasitas'] > 0 ? round(($kelas['total_siswa'] / $kelas['kapasitas']) * 100) : 0;
                        ?>
                        <tr>
                            <td class="report-text-center"><?= $no++ ?></td>
                            <td class="report-text-left">
                                <strong><?= htmlspecialchars($kelas['nama_kelas']) ?></strong>
                            </td>
                            <td class="report-text-left" style="line-height: 1.3;">
                                <?php if($kelas['nama_gelombang']): ?>
                                    <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                                    <?php if($kelas['tahun']): ?>
                                        <br><small style="color: #666;">(<?= htmlspecialchars($kelas['tahun']) ?>)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="report-text-center"><?= $kelas['kapasitas'] ?></td>
                            <td class="report-text-center">
                                <?= $kelas['total_siswa'] ?>
                                <br><small style="color: #666;">(<?= $persentase_terisi ?>%)</small>
                            </td>
                            <td class="report-text-center"><?= max(0, $sisa_kapasitas) ?></td>
                            <td class="report-text-left">
                                <?= htmlspecialchars($kelas['nama_instruktur'] ?? 'Belum ditentukan') ?>
                                <?php if($kelas['nik_instruktur']): ?>
                                    <br><small style="color: #666;">NIK: <?= htmlspecialchars($kelas['nik_instruktur']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="report-text-center" style="font-size: 8px;">
                                <?php if($kelas['status_gelombang'] == 'aktif'): ?>
                                    Aktif
                                <?php elseif($kelas['status_gelombang'] == 'dibuka'): ?>
                                    Dibuka
                                <?php elseif($kelas['status_gelombang'] == 'selesai'): ?>
                                    Selesai
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                                <br>
                                <?php if($kelas['total_siswa'] >= $kelas['kapasitas']): ?>
                                    <small style="color: #d63384;">PENUH</small>
                                <?php else: ?>
                                    <small style="color: #198754;">TERSEDIA</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="report-text-center" style="padding: 20px; font-style: italic; color: #666;">
                            <i class="bi bi-info-circle"></i> Tidak ada data kelas yang tersedia
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="report-document-footer report-page-break-avoid">
            <div class="report-closing-statement">
                <strong>Catatan:</strong><br>
                • Laporan ini dicetak secara otomatis dari sistem informasi LKP Pradata Komputer<br>
                • Total record yang ditampilkan: <?= $totalKelas ?> kelas<br>
                • Data diurutkan berdasarkan nama kelas<br>
                • Status kapasitas: PENUH (siswa ≥ kapasitas), TERSEDIA (masih ada sisa kapasitas)<br>
                • Persentase terisi dihitung dari jumlah siswa dibagi kapasitas kelas<br>
            </div>

            <!-- Signature Section -->
            <div class="report-signature-section">
                <div class="report-signature-right">
                    <div class="report-signature-location">Tabalong, <?= formatTanggalIndonesia() ?></div>
                    <div class="report-signature-title">Mengetahui,</div>
                    <div class="report-signature-name">Awiek Hadi Widodo</div>
                    <div class="report-signature-position">Direktur</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Optimize for print
        window.onbeforeprint = function() {
            // Adjust font sizes for print
            document.body.style.fontSize = '11px';
            
            // Ensure table cells don't break
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '9px';
                cell.style.lineHeight = '1.2';
            });
            
            // Hide any remaining screen elements
            document.querySelectorAll('.report-no-print').forEach(function(element) {
                element.style.display = 'none';
            });
        }
        
        window.onafterprint = function() {
            // Reset styles after print
            document.body.style.fontSize = '';
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '';
                cell.style.lineHeight = '';
            });
        }
        
        // Add print date automatically
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Laporan Data Kelas - Format Resmi Instansi');
            console.log('Total Records: <?= $totalKelas ?>');
        });
    </script>
</body>
</html>