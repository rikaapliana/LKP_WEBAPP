<?php
session_start();  
require_once '../../../includes/auth.php';  
requireSiswaAuth();

include '../../../includes/db.php';

$user_id = $_SESSION['user_id'];

// Ambil data siswa dengan nilai lengkap untuk sertifikat
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang, g.tahun, g.gelombang_ke,
                 i.nama as nama_instruktur, n.*,
                 -- Status sertifikat
                 CASE WHEN n.rata_rata >= 60 AND 
                           (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                           (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                           (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                           (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                           (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0)
                      THEN 'eligible' ELSE 'not_eligible' END as sertifikat_status
          FROM siswa s
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas  
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN nilai n ON s.id_siswa = n.id_siswa
          WHERE s.id_user = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$siswaData = $result->fetch_assoc();

// Validasi data siswa
if (!$siswaData) {
    die("Data siswa tidak ditemukan!");
}

// Validasi eligibility sertifikat
if ($siswaData['sertifikat_status'] != 'eligible') {
    die("Anda belum memenuhi syarat untuk mencetak sertifikat!");
}

// Include FPDF
require_once '../../../vendor/fpdf/fpdf.php';

// Function untuk get grade
function getGrade($nilai) {
    if ($nilai >= 80) return 'A (Sangat Baik)';
    if ($nilai >= 70) return 'B (Baik)';
    if ($nilai >= 60) return 'C (Cukup)';
    return 'D (Kurang)';
}

// Generate nomor sertifikat
$nomorSertifikat = 'LKP/' . date('Y') . '/GEL' . ($siswaData['gelombang_ke'] ?? '1') . '/' . str_pad($siswaData['id_siswa'], 3, '0', STR_PAD_LEFT);

// Create PDF class dengan custom header/footer
class SertifikatPDF extends FPDF {
    private $siswaData;
    private $nomorSertifikat;
    
    public function __construct($siswaData, $nomorSertifikat) {
        parent::__construct('L', 'mm', 'A4'); // Landscape
        $this->siswaData = $siswaData;
        $this->nomorSertifikat = $nomorSertifikat;
    }
    
    function Header() {
        // Border decorative
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(1.5);
        $this->Rect(8, 8, 281, 194, 'D');
        
        $this->SetLineWidth(0.8);
        $this->Rect(12, 12, 273, 186, 'D');
        
        // Logo
        if (file_exists('../../../assets/img/favicon.png')) {
            $this->Image('../../../assets/img/favicon.png', 20, 20, 30);
        }
        
        // Header Institution
        $this->SetXY(60, 22);
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 10, 'LKP PRADATA KOMPUTER', 0, 1, 'L');
        
        $this->SetXY(60, 34);
        $this->SetFont('Arial', '', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 7, 'LEMBAGA KURSUS DAN PELATIHAN KABUPATEN TABALONG', 0, 1, 'L');
        
        
        // Decorative line
        $this->SetDrawColor(0, 102, 153);
        $this->SetLineWidth(1);
        $this->Line(20, 50, 277, 50);
    }
    
    // Function untuk membuat border dengan pattern
    function FancyBorder() {
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(2);
        // Outer border
        $this->Rect(8, 8, 281, 194, 'D');
        
        // Inner border
        $this->SetLineWidth(0.8);
        $this->SetDrawColor(0, 102, 153);
        $this->Rect(12, 12, 273, 186, 'D');
        
        // Corner decorations
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(1.5);
        
        // Top left corner
        $this->Line(18, 18, 35, 18);
        $this->Line(18, 18, 18, 35);
        
        // Top right corner  
        $this->Line(262, 18, 279, 18);
        $this->Line(279, 18, 279, 35);
        
        // Bottom left corner
        $this->Line(18, 184, 35, 184);
        $this->Line(18, 167, 18, 184);
        
        // Bottom right corner
        $this->Line(262, 184, 279, 184);
        $this->Line(279, 167, 279, 184);
    }
}

// Create PDF
$pdf = new SertifikatPDF($siswaData, $nomorSertifikat);
$pdf->AddPage();
$pdf->FancyBorder();

// Main Title
$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 18, 'SERTIFIKAT KELULUSAN', 0, 1, 'C');

// Nomor Sertifikat
$pdf->SetFont('Arial', 'I', 15);
$pdf->SetTextColor(0, 102, 153);
$pdf->Cell(0, 8, 'No. Sertifikat: ' . $nomorSertifikat, 0, 1, 'C');

$pdf->Ln(5);

// Decorative line under title
$pdf->SetDrawColor(0, 102, 153);
$pdf->SetLineWidth(0.8);
$pdf->Line(70, $pdf->GetY(), 227, $pdf->GetY());

$pdf->Ln(9);

// Introduction text
$pdf->SetFont('Arial', '', 15);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Dengan ini menyatakan bahwa:', 0, 1, 'C');

$pdf->Ln(6);

// Student name with decorative box
$pdf->SetDrawColor(0, 102, 153);
$pdf->SetFillColor(240, 248, 255);
$pdf->SetLineWidth(1);

$nameBoxY = $pdf->GetY();
$pdf->Rect(40, $nameBoxY, 217, 18, 'DF');

$pdf->SetXY(40, $nameBoxY + 5);
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetTextColor(0, 51, 50);
$pdf->Cell(210, 8, strtoupper($siswaData['nama']), 0, 1, 'C');

$pdf->Ln(10);

// Course completion text
$pdf->SetFont('Arial', '', 15);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'Telah Berhasil Menyelesaikan Program Pelatihan:', 0, 1, 'C');

$pdf->Ln(4);

// Course name
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(0, 102, 153);
$pdf->Cell(0, 8, 'APLIKASI PERKANTORAN (MICROSOFT OFFICE)', 0, 1, 'C');

$pdf->SetFont('Arial', '', 13);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, $siswaData['nama_gelombang'], 0, 1, 'C');

// Pindah ke halaman baru untuk tabel nilai
$pdf->AddPage();
$pdf->FancyBorder();
$pdf->Ln(10); // Jarak dari atas halaman (di bawah header)

// Nilai section dengan tabel yang rapi
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'KOMPETENSI PENILAIAN:', 0, 1, 'C');

$pdf->Ln(6);

// Tabel nilai dengan border
$tableStartY = $pdf->GetY();
$tableWidth = 200;
$tableStartX = (297 - $tableWidth) / 2; // Center the table

$pdf->SetXY($tableStartX, $tableStartY);

// Table header
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);

$pdf->Cell(12, 9, 'No', 1, 0, 'C', true);
$pdf->Cell(100, 9, 'Mata Pelajaran', 1, 0, 'C', true);
$pdf->Cell(28, 9, 'Nilai', 1, 0, 'C', true);
$pdf->Cell(60, 9, 'Grade', 1, 1, 'C', true);

// Table content
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 11);

$nilaiData = [
    ['Microsoft Word', $siswaData['nilai_word']],
    ['Microsoft Excel', $siswaData['nilai_excel']],
    ['Microsoft PowerPoint', $siswaData['nilai_ppt']],
    ['Internet & Email', $siswaData['nilai_internet']],
    ['Pengembangan Softskill', $siswaData['nilai_pengembangan']]
];

$no = 1;
foreach ($nilaiData as $item) {
    $pdf->SetX($tableStartX);
    
    // Alternating row colors
    if ($no % 2 == 0) {
        $pdf->SetFillColor(248, 249, 250);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell(12, 8, $no, 1, 0, 'C', true);
    $pdf->Cell(100, 8, $item[0], 1, 0, 'L', true);
    $pdf->Cell(28, 8, $item[1], 1, 0, 'C', true);
    $pdf->Cell(60, 8, getGrade($item[1]), 1, 1, 'C', true);
    $no++;
}

// Total rata-rata row
$pdf->SetX($tableStartX);
$pdf->SetFillColor(0, 102, 153);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);

$pdf->Cell(112, 9, 'RATA-RATA KESELURUHAN', 1, 0, 'C', true);
$pdf->Cell(28, 9, number_format($siswaData['rata_rata'], 1), 1, 0, 'C', true);
$pdf->Cell(60, 9, getGrade($siswaData['rata_rata']), 1, 1, 'C', true);

$pdf->Ln(5);

// Date and location
$pdf->SetFont('Arial', '', 13);
$pdf->SetTextColor(0, 0, 0);
$bulanIndonesia = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$tanggalIndonesia = str_replace(array_keys($bulanIndonesia), array_values($bulanIndonesia), date('d F Y'));

$pdf->Cell(0, 6, 'Tabalong, ' . $tanggalIndonesia, 0, 1, 'C');

$pdf->Ln(2);

// Signature section - Centered layout
$signatureY = $pdf->GetY();

// Direktur section di tengah
$direktorX = 100; // Centered position
$pdf->SetXY($direktorX, $signatureY);
$pdf->SetFont('Arial', '', 13);
$pdf->Cell(97, 6, 'Direktur LKP Pradata Komputer', 0, 1, 'C');

$pdf->Ln(2);

// TTD di tengah, di atas nama
$qrX = $direktorX + 36; // Center the TTD (97/2 - 12.5)
if (file_exists('../../../assets/img/TTD.png')) {
    $pdf->Image('../../../assets/img/TTD.png', $qrX, $pdf->GetY(), 25, 25);
}

$pdf->Ln(28);

// Nama direktur langsung setelah TTD tanpa garis
$pdf->SetXY($direktorX, $pdf->GetY());
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(97, 6, 'Awiek Hadi Widodo', 0, 1, 'C');

// Output PDF
$filename = 'Sertifikat_' . str_replace(' ', '_', $siswaData['nama']) . '_' . date('Y') . '.pdf';
$pdf->Output('D', $filename); // D = Download
?>



