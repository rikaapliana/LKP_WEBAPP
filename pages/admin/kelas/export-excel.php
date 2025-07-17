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

// Hitung statistik
$totalKelas = $result ? mysqli_num_rows($result) : 0;
$kelasAktif = 0;
$kelasDibuka = 0;
$kelasSelesai = 0;
$kelasPenuh = 0;
$totalKapasitas = 0;
$totalTerisi = 0;
$gelombangStats = [];
$instrukturStats = [];
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dataArray[] = $row;
        
        // Statistik status gelombang
        if ($row['status_gelombang'] == 'aktif') {
            $kelasAktif++;
        } elseif ($row['status_gelombang'] == 'dibuka') {
            $kelasDibuka++;
        } elseif ($row['status_gelombang'] == 'selesai') {
            $kelasSelesai++;
        }
        
        // Statistik kapasitas
        $totalKapasitas += $row['kapasitas'];
        $totalTerisi += $row['total_siswa'];
        
        if ($row['total_siswa'] >= $row['kapasitas']) {
            $kelasPenuh++;
        }
        
        // Statistik per gelombang
        $namaGelombang = $row['nama_gelombang'] ?? 'Tidak ada gelombang';
        if (!isset($gelombangStats[$namaGelombang])) {
            $gelombangStats[$namaGelombang] = 0;
        }
        $gelombangStats[$namaGelombang]++;
        
        // Statistik per instruktur
        $namaInstruktur = $row['nama_instruktur'] ?? 'Belum ditentukan';
        if (!isset($instrukturStats[$namaInstruktur])) {
            $instrukturStats[$namaInstruktur] = 0;
        }
        $instrukturStats[$namaInstruktur]++;
    }
}

$totalSisa = $totalKapasitas - $totalTerisi;
$persentaseGlobal = $totalKapasitas > 0 ? round(($totalTerisi / $totalKapasitas) * 100) : 0;

// Set nama file dengan ekstensi .xls untuk auto-recognition Excel
$filename = "Laporan_Data_Kelas_" . date('Y-m-d_H-i-s') . ".xls";

// Header yang tepat untuk Excel format
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

// Tidak pakai DOCTYPE dan HTML5 tags - langsung table HTML
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="ProgId" content="Excel.Sheet">
<meta name="Generator" content="Microsoft Excel 15">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Data Kelas</x:Name>
<x:WorksheetOptions>
<x:DisplayGridlines/>
</x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml><![endif]-->

<style>
<!--
table {
    mso-displayed-decimal-separator:"\.";
    mso-displayed-thousand-separator:"\,";
}

.xl65 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#2E75B6;
    mso-pattern:auto none;
    color:white;
    font-size:16pt;
    font-weight:700;
    text-align:center;
}

.xl66 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#D5E4F7;
    mso-pattern:auto none;
    font-size:11pt;
    font-weight:700;
    text-align:center;
}

.xl67 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#4472C4;
    mso-pattern:auto none;
    color:white;
    font-size:10pt;
    font-weight:700;
    text-align:center;
}

.xl68 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:center;
    mso-number-format:"\@";
}

.xl69 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:left;
    mso-number-format:"\@";
}

.xl70 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#C6EFCE;
    mso-pattern:auto none;
    color:#006100;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl71 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFC7CE;
    mso-pattern:auto none;
    color:#9C0006;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl72 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DDEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl73 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FCE4EC;
    mso-pattern:auto none;
    color:#880E4F;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl74 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E7E6E6;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl75 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#F2F2F2;
    mso-pattern:auto none;
    font-size:10pt;
}

.xl76 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFF2CC;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl77 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DEEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl78 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E1F5FE;
    mso-pattern:auto none;
    color:#01579B;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl79 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#F3E5F5;
    mso-pattern:auto none;
    color:#4A148C;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}
-->
</style>
</head>

<body>

<!-- Header Institusi -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="40">
    <td height="40" colspan="8" class="xl65">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</td>
</tr>
<tr height="25">
    <td height="25" colspan="8" class="xl66">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</td>
</tr>
<tr height="20">
    <td height="20" colspan="8" class="xl66">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</td>
</tr>
<tr height="10">
    <td height="10" colspan="8" style="border:none;"></td>
</tr>
</table>

<!-- Info Dokumen -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="30">
    <td height="30" colspan="8" class="xl66">LAPORAN DATA KELAS - PER TANGGAL <?= strtoupper(formatTanggalIndonesia()) ?></td>
</tr>
<tr>
    <td class="xl74" width="150">Nomor Dokumen:</td>
    <td class="xl75" colspan="7"><?= date('Y') ?>/LKP-PC/KELAS/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
</tr>
<tr>
    <td class="xl74">Tanggal Export:</td>
    <td class="xl75" colspan="7"><?= formatTanggalIndonesia(null, true) ?> WIB</td>
</tr>
<tr>
    <td class="xl74">Total Record:</td>
    <td class="xl75" colspan="7"><?= $totalKelas ?> (<?= terbilang($totalKelas) ?>) kelas</td>
</tr>
<tr>
    <td class="xl74">Dicetak Oleh:</td>
    <td class="xl75" colspan="7">Administrator Sistem</td>
</tr>
<tr height="10">
    <td height="10" colspan="8" style="border:none;"></td>
</tr>
</table>

<!-- Data Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<thead>
<tr>
    <td class="xl67" width="40">No</td>
    <td class="xl67" width="200">Nama Kelas</td>
    <td class="xl67" width="150">Gelombang</td>
    <td class="xl67" width="80">Kapasitas</td>
    <td class="xl67" width="80">Terisi</td>
    <td class="xl67" width="80">Sisa</td>
    <td class="xl67" width="180">Instruktur</td>
    <td class="xl67" width="100">Status</td>
</tr>
</thead>
<tbody>
<?php if (!empty($dataArray)): ?>
    <?php $no = 1; ?>
    <?php foreach ($dataArray as $kelas): ?>
        <?php
        $sisa_kapasitas = $kelas['kapasitas'] - $kelas['total_siswa'];
        $persentase_terisi = $kelas['kapasitas'] > 0 ? round(($kelas['total_siswa'] / $kelas['kapasitas']) * 100) : 0;
        
        // Tentukan status class berdasarkan kondisi
        $statusClass = 'xl77'; // default
        if ($kelas['status_gelombang'] == 'aktif') {
            $statusClass = 'xl70'; // hijau
        } elseif ($kelas['status_gelombang'] == 'dibuka') {
            $statusClass = 'xl78'; // biru
        } elseif ($kelas['status_gelombang'] == 'selesai') {
            $statusClass = 'xl72'; // abu-abu biru
        }
        
        $kapasitasClass = ($kelas['total_siswa'] >= $kelas['kapasitas']) ? 'xl71' : 'xl70'; // merah jika penuh, hijau jika tersedia
        ?>
    <tr>
        <td class="xl68"><?= $no++ ?></td>
        <td class="xl69"><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
        <td class="xl69">
            <?php if($kelas['nama_gelombang']): ?>
                <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                <?php if($kelas['tahun']): ?>
                    (<?= htmlspecialchars($kelas['tahun']) ?>)
                <?php endif; ?>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td class="xl68"><?= $kelas['kapasitas'] ?></td>
        <td class="xl68"><?= $kelas['total_siswa'] ?> (<?= $persentase_terisi ?>%)</td>
        <td class="xl68"><?= max(0, $sisa_kapasitas) ?></td>
        <td class="xl69">
            <?= htmlspecialchars($kelas['nama_instruktur'] ?? 'Belum ditentukan') ?>
            <?php if($kelas['nik_instruktur']): ?>
                (NIK: <?= htmlspecialchars($kelas['nik_instruktur']) ?>)
            <?php endif; ?>
        </td>
        <td class="<?= $statusClass ?>">
            <?php if($kelas['status_gelombang'] == 'aktif'): ?>
                AKTIF
            <?php elseif($kelas['status_gelombang'] == 'dibuka'): ?>
                DIBUKA
            <?php elseif($kelas['status_gelombang'] == 'selesai'): ?>
                SELESAI
            <?php else: ?>
                -
            <?php endif; ?>
            <?php if($kelas['total_siswa'] >= $kelas['kapasitas']): ?>
                / PENUH
            <?php else: ?>
                / TERSEDIA
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="8" class="xl68" style="font-style:italic; color:#999;">
            Tidak ada data kelas yang tersedia
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<!-- Summary -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="8" style="border:none;"></td>
</tr>
<tr height="30">
    <td height="30" colspan="8" class="xl67">RINGKASAN DATA KELAS</td>
</tr>
<tr>
    <td class="xl74" width="200">Total Kelas:</td>
    <td class="xl75"><?= $totalKelas ?> kelas</td>
    <td class="xl74">Kelas Aktif:</td>
    <td class="xl70"><?= $kelasAktif ?> kelas</td>
    <td class="xl74">Kelas Dibuka:</td>
    <td class="xl78"><?= $kelasDibuka ?> kelas</td>
    <td class="xl74">Kelas Selesai:</td>
    <td class="xl72"><?= $kelasSelesai ?> kelas</td>
</tr>
<tr>
    <td class="xl74">Total Kapasitas:</td>
    <td class="xl75"><?= $totalKapasitas ?> siswa</td>
    <td class="xl74">Total Terisi:</td>
    <td class="xl70"><?= $totalTerisi ?> siswa</td>
    <td class="xl74">Sisa Kapasitas:</td>
    <td class="xl78"><?= $totalSisa ?> siswa</td>
    <td class="xl74">Persentase Terisi:</td>
    <td class="xl79"><?= $persentaseGlobal ?>%</td>
</tr>
<tr>
    <td class="xl74">Kelas Penuh:</td>
    <td class="xl71"><?= $kelasPenuh ?> kelas</td>
    <td class="xl74">Kelas Tersedia:</td>
    <td class="xl70"><?= ($totalKelas - $kelasPenuh) ?> kelas</td>
    <td class="xl74">Gelombang Teraktif:</td>
    <td class="xl75" colspan="3"><?php 
        if (!empty($gelombangStats)) {
            arsort($gelombangStats);
            $topGelombang = array_key_first($gelombangStats);
            echo htmlspecialchars($topGelombang) . ' (' . $gelombangStats[$topGelombang] . ' kelas)';
        } else {
            echo '-';
        }
    ?></td>
</tr>
<tr>
    <td class="xl74">Instruktur Teraktif:</td>
    <td class="xl75" colspan="7"><?php 
        if (!empty($instrukturStats)) {
            arsort($instrukturStats);
            $topInstruktur = array_key_first($instrukturStats);
            echo htmlspecialchars($topInstruktur) . ' (' . $instrukturStats[$topInstruktur] . ' kelas)';
        } else {
            echo '-';
        }
    ?></td>
</tr>
</table>

<!-- Footer -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:30px;">
<tr height="10">
    <td height="10" colspan="8" style="border:none;"></td>
</tr>
<tr>
    <td colspan="5" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold;">Tabalong, <?= formatTanggalIndonesia() ?></td>
</tr>
<tr>
    <td colspan="5" style="border:none;"></td>
    <td colspan="3" style="border:none;">Mengetahui,</td>
</tr>
<tr height="60">
    <td height="60" colspan="5" style="border:none;"></td>
    <td colspan="3" style="border:none;"></td>
</tr>
<tr>
    <td colspan="5" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold; text-decoration:underline;">Awiek Hadi Widodo</td>
</tr>
<tr>
    <td colspan="5" style="border:none;"></td>
    <td colspan="3" style="border:none;">Direktur</td>
</tr>
<tr height="20">
    <td height="20" colspan="8" style="border:none;"></td>
</tr>
<tr>
    <td colspan="8" style="border:none; text-align:center; font-size:10pt; color:#666; font-style:italic;">
        Laporan ini dibuat secara otomatis oleh Sistem Informasi LKP Pradata Komputer
    </td>
</tr>
</table>

</body>
</html>