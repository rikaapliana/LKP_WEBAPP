-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Jul 2025 pada 10.15
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lkp_webapp`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_instruktur`
--

CREATE TABLE `absensi_instruktur` (
  `id_absen` int(11) NOT NULL,
  `id_instruktur` int(11) DEFAULT NULL,
  `id_jadwal` int(11) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `waktu` datetime DEFAULT NULL,
  `status` enum('hadir','izin','sakit','tanpa keterangan') DEFAULT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi_instruktur`
--

INSERT INTO `absensi_instruktur` (`id_absen`, `id_instruktur`, `id_jadwal`, `tanggal`, `waktu`, `status`, `keterangan`) VALUES
(1, 2, 25, '2025-07-23', '2025-07-23 08:46:42', 'hadir', ''),
(2, 2, 72, '2025-07-25', '2025-07-25 06:42:48', 'hadir', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_siswa`
--

CREATE TABLE `absensi_siswa` (
  `id_absen` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_jadwal` int(11) DEFAULT NULL,
  `status` enum('hadir','izin','sakit','tanpa keterangan') DEFAULT NULL,
  `waktu_absen` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi_siswa`
--

INSERT INTO `absensi_siswa` (`id_absen`, `id_siswa`, `id_jadwal`, `status`, `waktu_absen`) VALUES
(4, 12, 72, 'sakit', '2025-07-25 16:27:18'),
(5, 8, 72, 'hadir', '2025-07-25 16:27:18'),
(6, 14, 72, 'hadir', '2025-07-25 16:27:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `id_user`, `nama`, `no_hp`, `email`, `foto`) VALUES
(1, 6, 'Rika Apliana', '082213594210', 'rikaapliana02@gmail.com', '1752636015_admin_68771a6fcbb4a.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `evaluasi`
--

CREATE TABLE `evaluasi` (
  `id_evaluasi` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `tanggal_evaluasi` datetime DEFAULT current_timestamp(),
  `status_evaluasi` enum('mulai','selesai') DEFAULT 'mulai',
  `id_periode` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `evaluasi`
--

INSERT INTO `evaluasi` (`id_evaluasi`, `id_siswa`, `id_kelas`, `tanggal_evaluasi`, `status_evaluasi`, `id_periode`) VALUES
(32, 14, 1, '2025-07-26 13:18:55', 'selesai', 17),
(33, 14, 1, '2025-07-26 13:24:30', 'selesai', 19);

-- --------------------------------------------------------

--
-- Struktur dari tabel `gelombang`
--

CREATE TABLE `gelombang` (
  `id_gelombang` int(11) NOT NULL,
  `tahun` year(4) NOT NULL,
  `gelombang_ke` int(11) NOT NULL,
  `nama_gelombang` varchar(100) DEFAULT NULL,
  `status` enum('aktif','selesai','dibuka') DEFAULT 'dibuka'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `gelombang`
--

INSERT INTO `gelombang` (`id_gelombang`, `tahun`, `gelombang_ke`, `nama_gelombang`, `status`) VALUES
(1, '2025', 1, 'Gelombang 1 Tahun 2025', 'aktif'),
(2, '2025', 2, 'Gelombang 2 Tahun 2025', 'aktif');

-- --------------------------------------------------------

--
-- Struktur dari tabel `instruktur`
--

CREATE TABLE `instruktur` (
  `id_instruktur` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `jenis_kelamin` enum('Laki-Laki','Perempuan') DEFAULT NULL,
  `angkatan` varchar(100) DEFAULT NULL,
  `status_aktif` enum('aktif','nonaktif') DEFAULT 'aktif',
  `pas_foto` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `instruktur`
--

INSERT INTO `instruktur` (`id_instruktur`, `id_user`, `nik`, `nama`, `jenis_kelamin`, `angkatan`, `status_aktif`, `pas_foto`, `email`) VALUES
(1, 2, '6309077107050001', 'Fithri Mutiya ', 'Perempuan', 'Gelombang 2 Tahun 2021', 'aktif', '', 'mutiya@gmail.com'),
(2, 3, '6309077107050089', 'Fety Fatimah', 'Perempuan', 'Gelombang 1 Tahun 2020', 'aktif', '1753344356_instruktur_6881e9648f312.jpg', 'fety@gmail.com'),
(3, NULL, '6309077107050002', 'Muhammad Rizki ', 'Laki-Laki', 'Gelombang 5 Tahun 2021', 'aktif', '', NULL),
(5, NULL, '6309077107050080', 'Muhammad Alfiansyah', 'Perempuan', 'Gelombang 3 Tahun 2021', 'aktif', '', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal`
--

CREATE TABLE `jadwal` (
  `id_jadwal` int(11) NOT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `id_instruktur` int(11) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `waktu_selesai` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal`
--

INSERT INTO `jadwal` (`id_jadwal`, `id_kelas`, `id_instruktur`, `tanggal`, `waktu_mulai`, `waktu_selesai`) VALUES
(71, 1, 2, '2025-07-24', '08:00:00', '09:00:00'),
(72, 1, 2, '2025-07-25', '08:00:00', '09:00:00'),
(73, 1, 2, '2025-07-28', '08:00:00', '09:00:00'),
(74, 1, 2, '2025-07-29', '08:00:00', '09:00:00'),
(75, 1, 2, '2025-07-30', '08:00:00', '09:00:00'),
(76, 1, 2, '2025-07-31', '08:00:00', '09:00:00'),
(77, 1, 2, '2025-08-01', '08:00:00', '09:00:00'),
(78, 1, 2, '2025-08-04', '08:00:00', '09:00:00'),
(79, 1, 2, '2025-08-05', '08:00:00', '09:00:00'),
(80, 1, 2, '2025-08-06', '08:00:00', '09:00:00'),
(81, 1, 2, '2025-08-07', '08:00:00', '09:00:00'),
(82, 1, 2, '2025-08-08', '08:00:00', '09:00:00'),
(83, 1, 2, '2025-08-11', '08:00:00', '09:00:00'),
(84, 1, 2, '2025-08-12', '08:00:00', '09:00:00'),
(85, 1, 2, '2025-08-13', '08:00:00', '09:00:00'),
(86, 1, 2, '2025-08-14', '08:00:00', '09:00:00'),
(87, 1, 2, '2025-08-15', '08:00:00', '09:00:00'),
(88, 1, 2, '2025-08-18', '08:00:00', '09:00:00'),
(89, 1, 2, '2025-08-19', '08:00:00', '09:00:00'),
(90, 1, 2, '2025-08-20', '08:00:00', '09:00:00'),
(91, 1, 2, '2025-08-21', '08:00:00', '09:00:00'),
(92, 1, 2, '2025-08-22', '08:00:00', '09:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jawaban_evaluasi`
--

CREATE TABLE `jawaban_evaluasi` (
  `id_jawaban` int(11) NOT NULL,
  `id_evaluasi` int(11) DEFAULT NULL,
  `id_pertanyaan` int(11) DEFAULT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `jawaban` text DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jawaban_evaluasi`
--

INSERT INTO `jawaban_evaluasi` (`id_jawaban`, `id_evaluasi`, `id_pertanyaan`, `id_siswa`, `jawaban`, `answered_at`) VALUES
(520, 32, 171, 14, '4', '2025-07-26 05:18:55'),
(521, 32, 83, 14, 'Font dan Size', '2025-07-26 05:18:55'),
(522, 32, 173, 14, 'Sangat Mudah', '2025-07-26 05:18:55'),
(523, 32, 84, 14, 'Numbering', '2025-07-26 05:18:55'),
(524, 32, 174, 14, 'Kurang', '2025-07-26 05:18:55'),
(525, 32, 85, 14, 'Page Number', '2025-07-26 05:18:55'),
(526, 32, 175, 14, 'Tidak Jelas', '2025-07-26 05:18:55'),
(527, 32, 86, 14, 'Drag and drop', '2025-07-26 05:18:55'),
(528, 32, 176, 14, '3', '2025-07-26 05:18:55'),
(529, 32, 87, 14, 'Sort Data', '2025-07-26 05:18:55'),
(530, 32, 177, 14, '3', '2025-07-26 05:18:55'),
(531, 32, 88, 14, 'Format > Paragraph', '2025-07-26 05:18:55'),
(532, 32, 178, 14, '1', '2025-07-26 05:18:55'),
(533, 32, 89, 14, 'Cek ejaan', '2025-07-26 05:18:55'),
(534, 32, 179, 14, 'cukup', '2025-07-26 05:18:55'),
(535, 32, 90, 14, 'Semua benar', '2025-07-26 05:18:55'),
(536, 32, 180, 14, 'cukup', '2025-07-26 05:18:55'),
(537, 32, 91, 14, 'Lihat layout sebelum print', '2025-07-26 05:18:55'),
(538, 32, 92, 14, 'Format manual', '2025-07-26 05:18:55'),
(539, 32, 93, 14, 'Semua penting', '2025-07-26 05:18:55'),
(540, 32, 94, 14, 'Format sendiri', '2025-07-26 05:18:55'),
(541, 32, 95, 14, 'Koreksi typo', '2025-07-26 05:18:55'),
(542, 32, 96, 14, 'Semua benar', '2025-07-26 05:18:55'),
(543, 32, 97, 14, 'Semua situasi', '2025-07-26 05:18:55'),
(544, 32, 98, 14, '4', '2025-07-26 05:18:55'),
(545, 32, 99, 14, '4', '2025-07-26 05:18:55'),
(546, 32, 100, 14, '4', '2025-07-26 05:18:55'),
(547, 32, 101, 14, '4', '2025-07-26 05:18:55'),
(548, 32, 102, 14, '4', '2025-07-26 05:18:55'),
(549, 32, 103, 14, 'cukup', '2025-07-26 05:18:55'),
(550, 32, 104, 14, 'cukup', '2025-07-26 05:18:55'),
(551, 33, 105, 14, 'SUM (penjumlahan)', '2025-07-26 05:24:30'),
(552, 33, 106, 14, 'Ketik langsung di cell', '2025-07-26 05:24:30'),
(553, 33, 107, 14, '=AVERAGE(A1:A10)', '2025-07-26 05:24:30'),
(554, 33, 108, 14, 'Recommended Charts', '2025-07-26 05:24:30'),
(555, 33, 109, 14, 'Line Chart', '2025-07-26 05:24:30'),
(556, 33, 110, 14, 'Accounting format', '2025-07-26 05:24:30'),
(557, 33, 111, 14, 'Semua benar', '2025-07-26 05:24:30'),
(558, 33, 112, 14, 'Format as Table', '2025-07-26 05:24:30'),
(559, 33, 113, 14, 'Ctrl+Z (Undo)', '2025-07-26 05:24:30'),
(560, 33, 114, 14, 'Save As dengan nama jelas', '2025-07-26 05:24:30'),
(561, 33, 115, 14, 'Page Layout > Print Area', '2025-07-26 05:24:30'),
(562, 33, 116, 14, 'Data karyawan', '2025-07-26 05:24:30'),
(563, 33, 117, 14, '=(nilai/total)*100', '2025-07-26 05:24:30'),
(564, 33, 118, 14, 'Berubah vs tetap saat copy', '2025-07-26 05:24:30'),
(565, 33, 119, 14, 'Tugas sekolah/kuliah', '2025-07-26 05:24:30'),
(566, 33, 120, 14, '3', '2025-07-26 05:24:30'),
(567, 33, 121, 14, '3', '2025-07-26 05:24:30'),
(568, 33, 122, 14, '2', '2025-07-26 05:24:30'),
(569, 33, 123, 14, '2', '2025-07-26 05:24:30'),
(570, 33, 124, 14, '3', '2025-07-26 05:24:30'),
(571, 33, 125, 14, 'ckup', '2025-07-26 05:24:30'),
(572, 33, 126, 14, 'cukup', '2025-07-26 05:24:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id_kelas` int(11) NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `id_gelombang` int(11) NOT NULL,
  `kapasitas` int(11) DEFAULT NULL,
  `id_instruktur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id_kelas`, `nama_kelas`, `id_gelombang`, `kapasitas`, `id_instruktur`) VALUES
(1, '08.00 - 09.00 A', 1, 10, 2),
(2, '08.00 - 09.00 B', 1, 10, 1),
(10, '08.00 - 09.00 A', 2, 10, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `materi`
--

CREATE TABLE `materi` (
  `id_materi` int(11) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `id_instruktur` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `judul` varchar(100) DEFAULT NULL,
  `file_materi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `materi`
--

INSERT INTO `materi` (`id_materi`, `deskripsi`, `id_instruktur`, `id_kelas`, `judul`, `file_materi`) VALUES
(2, 'Materi Word Bagian 1', 2, 1, 'SISTEM INFORMASI MANAJEMEN', '1751525348_RikaSlipBimbinganSkripsi_compressed.pdf'),
(3, 'Materi Microsoft Persiapan Ujian', 2, 1, 'SISTEM INFORMASI MANAJEMEN 2', '1753242070_lkp_webapp4.doc'),
(4, 'Materi Word Pertemuan 1', 1, 2, 'SISTEM INFORMASI AKADEMIK', '1753242174_lkp_webapp4.doc');

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai`
--

CREATE TABLE `nilai` (
  `id_nilai` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `nilai_word` int(11) DEFAULT NULL,
  `nilai_excel` int(11) DEFAULT NULL,
  `nilai_ppt` int(11) DEFAULT NULL,
  `nilai_internet` int(11) DEFAULT NULL,
  `nilai_pengembangan` int(11) DEFAULT NULL,
  `rata_rata` float DEFAULT NULL,
  `status_kelulusan` enum('lulus','tidak lulus') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `nilai`
--

INSERT INTO `nilai` (`id_nilai`, `id_siswa`, `id_kelas`, `nilai_word`, `nilai_excel`, `nilai_ppt`, `nilai_internet`, `nilai_pengembangan`, `rata_rata`, `status_kelulusan`) VALUES
(1, 14, 1, 80, 90, 80, 80, 80, 82, 'lulus'),
(4, 12, 1, 90, 70, NULL, NULL, NULL, 80, NULL),
(5, 8, 1, 85, 75, NULL, NULL, NULL, 80, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pendaftar`
--

CREATE TABLE `pendaftar` (
  `id_pendaftar` int(11) NOT NULL,
  `id_gelombang` int(11) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `nama_pendaftar` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('Laki-Laki','Perempuan') DEFAULT NULL,
  `pendidikan_terakhir` enum('SD','SLTP','SLTA','D1','D2','S1','S2','S3') DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat_lengkap` text DEFAULT NULL,
  `jam_pilihan` enum('08.00 - 09.00','09.00 - 10.00','10.00 - 11.00','11.00 - 12.00','13.00 - 14.00','14.00 - 15.00','15.00 - 16.00','16.00 - 17.00','17.00 - 18.00','19.00 - 20.00','20.00 - 21.00','21.00 - 22.00') DEFAULT NULL,
  `pas_foto` varchar(255) DEFAULT NULL,
  `ktp` varchar(255) DEFAULT NULL,
  `kk` varchar(255) DEFAULT NULL,
  `ijazah` varchar(255) DEFAULT NULL,
  `status_pendaftaran` enum('Belum di Verifikasi','Terverifikasi','Diterima') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pendaftar`
--

INSERT INTO `pendaftar` (`id_pendaftar`, `id_gelombang`, `nik`, `nama_pendaftar`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `pendidikan_terakhir`, `no_hp`, `email`, `alamat_lengkap`, `jam_pilihan`, `pas_foto`, `ktp`, `kk`, `ijazah`, `status_pendaftaran`) VALUES
(1, 2, '3171012101950001', 'Ahmad Rizki Pratama', 'Jakarta', '1995-01-21', 'Laki-Laki', 'SLTA', '081234567890', 'ahmad.rizki@email.com', 'Jl. Merdeka No. 123, RT 05/RW 03, Kelurahan Menteng, Jakarta Pusat', '09.00 - 10.00', '', 'ktp_ahmad.jpg', '', '', 'Belum di Verifikasi'),
(6, 2, '6309077107050800', 'Almanida Zahra', 'Tabalong', '2002-09-09', 'Perempuan', 'S1', '082213594215', 'almanidazahra@gmail.com', 'JL. Adhyaksa VI, NO. 20A, RT. 26', '13.00 - 14.00', '1752668517_pas_foto_68779965159ce.jpg', '1752668517_ktp_6877996517790.jpg', '1752668517_kk_687799651830d.jpg', '1752668517_ijazah_6877996518a6a.jpg', 'Diterima'),
(7, 2, '6309077107057000', 'Riki Ramadhan', 'Tabalong', '2000-09-09', 'Laki-Laki', 'SLTA', '082213594219', 'rikaapliana0@gmail.com', 'Kupang Nunding', '08.00 - 09.00', '1752741448_pas_foto_6878b64891df8.jpg', '1752741448_ktp_6878b64892390.jpg', '1752741448_kk_6878b64892914.jpg', '1752741448_ijazah_6878b64893355.jpg', 'Terverifikasi'),
(8, 2, '6309077107050900', 'Muhammad Fadilah', 'Tabalong', '2002-07-07', 'Laki-Laki', 'D1', '082213594215', 'rikaapliana02@gmail.com', 'Kupang Nunding', '08.00 - 09.00', '1753167311_6309077107050900_pasfoto.jpg', '1753167311_6309077107050900_ktp.pdf', '1753167311_6309077107050900_kk.pdf', '1753167311_6309077107050900_ijazah.pdf', 'Terverifikasi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_pendaftaran`
--

CREATE TABLE `pengaturan_pendaftaran` (
  `id_pengaturan` int(11) NOT NULL,
  `id_gelombang` int(11) NOT NULL,
  `status_pendaftaran` enum('dibuka','ditutup') DEFAULT 'ditutup',
  `kuota_maksimal` int(11) DEFAULT 50,
  `tanggal_buka` datetime DEFAULT NULL,
  `tanggal_tutup` datetime DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan_pendaftaran`
--

INSERT INTO `pengaturan_pendaftaran` (`id_pengaturan`, `id_gelombang`, `status_pendaftaran`, `kuota_maksimal`, `tanggal_buka`, `tanggal_tutup`, `keterangan`, `dibuat_oleh`, `created_at`, `updated_at`) VALUES
(1, 1, 'ditutup', 10, NULL, '2025-07-17 00:00:00', 'Pendaftaran Gelombang 1 Tahun 2025', 1, '2025-07-16 11:47:59', '2025-07-17 12:41:51'),
(2, 2, 'dibuka', 10, NULL, NULL, '', NULL, '2025-07-17 07:38:18', '2025-07-17 12:42:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode_evaluasi`
--

CREATE TABLE `periode_evaluasi` (
  `id_periode` int(11) NOT NULL,
  `nama_evaluasi` varchar(100) NOT NULL,
  `jenis_evaluasi` enum('per_materi','akhir_kursus') NOT NULL,
  `materi_terkait` enum('word','excel','ppt','internet') DEFAULT NULL,
  `id_gelombang` int(11) NOT NULL,
  `tanggal_buka` datetime NOT NULL,
  `tanggal_tutup` datetime NOT NULL,
  `status` enum('draft','aktif','selesai') DEFAULT 'draft',
  `deskripsi` text DEFAULT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pertanyaan_terpilih` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pertanyaan_terpilih`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `periode_evaluasi`
--

INSERT INTO `periode_evaluasi` (`id_periode`, `nama_evaluasi`, `jenis_evaluasi`, `materi_terkait`, `id_gelombang`, `tanggal_buka`, `tanggal_tutup`, `status`, `deskripsi`, `dibuat_oleh`, `created_at`, `pertanyaan_terpilih`) VALUES
(17, 'Evaluasi Materi Microsoft Word', 'per_materi', 'word', 1, '2025-07-14 20:34:00', '2025-07-31 20:34:00', 'aktif', '', 1, '2025-07-14 12:35:00', '[171,83,173,84,174,85,175,86,176,87,177,88,178,89,179,90,180,91,92,93,94,95,96,97,98,99,100,101,102,103,104]'),
(19, 'Evaluasi Materi Microsoft Excel', 'per_materi', 'excel', 1, '2025-07-26 13:02:00', '2025-08-02 13:02:00', 'aktif', '', 1, '2025-07-26 05:02:38', '[105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126]');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pertanyaan_evaluasi`
--

CREATE TABLE `pertanyaan_evaluasi` (
  `id_pertanyaan` int(11) NOT NULL,
  `pertanyaan` text DEFAULT NULL,
  `aspek_dinilai` varchar(100) DEFAULT NULL,
  `jenis_evaluasi` enum('per_materi','akhir_kursus') DEFAULT 'akhir_kursus',
  `materi_terkait` enum('word','excel','ppt','internet') DEFAULT NULL,
  `tipe_jawaban` enum('skala','isian','pilihan_ganda') NOT NULL,
  `pilihan_jawaban` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pilihan_jawaban`)),
  `question_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pertanyaan_evaluasi`
--

INSERT INTO `pertanyaan_evaluasi` (`id_pertanyaan`, `pertanyaan`, `aspek_dinilai`, `jenis_evaluasi`, `materi_terkait`, `tipe_jawaban`, `pilihan_jawaban`, `question_order`, `is_active`, `created_at`, `updated_at`) VALUES
(56, 'Materi yang paling bermanfaat dalam program LKP ini?', 'Preferensi Materi', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Microsoft Word\", \"Microsoft Excel\", \"Microsoft PowerPoint\", \"Internet & Email\"]', 1, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(57, 'Tingkat kesulitan program LKP secara keseluruhan?', 'Tingkat Kesulitan', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Sangat Mudah\", \"Mudah\", \"Sedang\", \"Sulit\"]', 2, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(58, 'Durasi program LKP yang ideal menurut Anda?', 'Durasi Program', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"1-2 bulan\", \"3-4 bulan\", \"5-6 bulan\", \"Sudah sesuai sekarang\"]', 3, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(59, 'Metode pembelajaran yang paling efektif?', 'Metode Pembelajaran', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Teori lalu praktik\", \"Langsung praktik\", \"Kombinasi teori-praktik\", \"Belajar mandiri dengan panduan\"]', 4, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(60, 'Fasilitas LKP yang paling memuaskan?', 'Fasilitas Terbaik', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Komputer dan hardware\", \"Software yang lengkap\", \"Ruang kelas yang nyaman\", \"Koneksi internet\"]', 5, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(61, 'Aspek instruktur yang paling Anda hargai?', 'Kualitas Instruktur', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Keahlian teknis\", \"Cara mengajar yang jelas\", \"Kesabaran dan bantuan\", \"Motivasi dan dukungan\"]', 6, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(62, 'Setelah lulus, skill mana yang paling akan Anda gunakan?', 'Aplikasi Skill', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Word untuk dokumen\", \"Excel untuk data\", \"PowerPoint untuk presentasi\", \"Internet untuk komunikasi\"]', 7, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(63, 'Cara Anda mengetahui tentang LKP Pradata Computer?', 'Sumber Informasi', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Rekomendasi teman/keluarga\", \"Media sosial\", \"Brosur/iklan\", \"Pencarian internet\"]', 8, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(64, 'Alasan utama memilih LKP Pradata Computer?', 'Motivasi Memilih', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Reputasi yang baik\", \"Harga terjangkau\", \"Lokasi strategis\", \"Program yang lengkap\"]', 9, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(65, 'Jadwal kelas yang paling cocok untuk Anda?', 'Preferensi Jadwal', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Pagi (08.00-12.00)\", \"Siang (13.00-17.00)\", \"Sore (17.00-21.00)\", \"Weekend\"]', 10, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(66, 'Ukuran kelas yang ideal?', 'Ukuran Kelas', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"5-8 orang\", \"9-12 orang\", \"13-15 orang\", \"Lebih dari 15 orang\"]', 11, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(67, 'Program lanjutan yang Anda minati?', 'Program Lanjutan', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Design Grafis\", \"Programming/Coding\", \"Digital Marketing\", \"Accounting Software\"]', 12, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(68, 'Cara pembayaran yang paling mudah?', 'Metode Pembayaran', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Cash/Tunai\", \"Transfer Bank\", \"Cicilan/Installment\", \"E-wallet\"]', 13, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(69, 'Media yang efektif untuk info update LKP?', 'Media Komunikasi', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"WhatsApp Group\", \"Facebook/Instagram\", \"Email Newsletter\", \"Website resmi\"]', 14, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(70, 'Kemungkinan merekomendasikan LKP ini?', 'Tingkat Rekomendasi', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Pasti akan merekomendasikan\", \"Kemungkinan besar ya\", \"Mungkin\", \"Tidak akan merekomendasikan\"]', 15, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(71, 'Program LKP Pradata Computer secara keseluruhan memuaskan', 'Kepuasan Program', 'akhir_kursus', NULL, 'skala', NULL, 16, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(72, 'Instruktur menguasai materi dan mengajar dengan profesional', 'Kompetensi Instruktur', 'akhir_kursus', NULL, 'skala', NULL, 17, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(73, 'Instruktur sabar dan membantu ketika siswa mengalami kesulitan', 'Sikap Instruktur', 'akhir_kursus', NULL, 'skala', NULL, 18, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(74, 'Fasilitas komputer dan software mendukung pembelajaran', 'Kualitas Fasilitas', 'akhir_kursus', NULL, 'skala', NULL, 19, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(75, 'Ruang kelas nyaman dan kondusif untuk belajar', 'Lingkungan Belajar', 'akhir_kursus', NULL, 'skala', NULL, 20, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(76, 'Materi pembelajaran sesuai dengan kebutuhan dan ekspektasi', 'Relevansi Materi', 'akhir_kursus', NULL, 'skala', NULL, 21, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(77, 'Tempo dan jadwal pembelajaran sesuai dengan kemampuan saya', 'Kesesuaian Tempo', 'akhir_kursus', NULL, 'skala', NULL, 22, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(78, 'Program ini memberikan nilai tambah untuk karir/pekerjaan saya', 'Manfaat Karir', 'akhir_kursus', NULL, 'skala', NULL, 23, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(79, 'Saya merasa percaya diri menggunakan komputer setelah program ini', 'Peningkatan Kepercayaan', 'akhir_kursus', NULL, 'skala', NULL, 24, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(80, 'Secara keseluruhan, saya puas dengan investasi waktu dan biaya', 'Kepuasan Investasi', 'akhir_kursus', NULL, 'skala', NULL, 25, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(81, 'Kritik dan saran untuk perbaikan program LKP Pradata Computer', 'Feedback Perbaikan', 'akhir_kursus', NULL, 'isian', NULL, 26, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(82, 'Testimoni atau pesan untuk calon peserta LKP', 'Testimoni', 'akhir_kursus', NULL, 'isian', NULL, 27, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(83, 'Fitur formatting text yang paling sering Anda gunakan?', 'Preferensi Fitur', 'per_materi', 'word', 'pilihan_ganda', '[\"Bold dan Italic\", \"Font dan Size\", \"Text Color\", \"Alignment (rata kiri/kanan/tengah)\"]', 1, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(84, 'Cara termudah membuat daftar dalam Word?', 'Teknik Dasar', 'per_materi', 'word', 'pilihan_ganda', '[\"Bullet Points\", \"Numbering\", \"Manual dengan tanda (-)\", \"Copy paste dari web\"]', 2, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(85, 'Fitur Word yang paling membantu untuk dokumen panjang?', 'Fitur Lanjutan', 'per_materi', 'word', 'pilihan_ganda', '[\"Page Break\", \"Header dan Footer\", \"Table of Contents\", \"Page Number\"]', 3, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(86, 'Cara terbaik menambahkan gambar ke dokumen Word?', 'Insert Object', 'per_materi', 'word', 'pilihan_ganda', '[\"Insert > Pictures\", \"Copy paste dari browser\", \"Drag and drop\", \"Screenshots\"]', 4, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(87, 'Fitur tabel Word yang paling berguna?', 'Tabel', 'per_materi', 'word', 'pilihan_ganda', '[\"Insert Table\", \"Table Design\", \"Merge Cells\", \"Sort Data\"]', 5, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(88, 'Cara mengatur margin halaman yang benar?', 'Page Layout', 'per_materi', 'word', 'pilihan_ganda', '[\"Page Layout > Margins\", \"File > Page Setup\", \"Ruler di atas dokumen\", \"Format > Paragraph\"]', 6, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(89, 'Fitur spell check Word membantu untuk?', 'Koreksi Dokumen', 'per_materi', 'word', 'pilihan_ganda', '[\"Cek ejaan\", \"Grammar check\", \"Suggest synonyms\", \"Semua benar\"]', 7, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(90, 'Cara menyimpan dokumen Word yang aman?', 'File Management', 'per_materi', 'word', 'pilihan_ganda', '[\"Save As dengan nama jelas\", \"Auto Save aktif\", \"Backup di cloud\", \"Semua benar\"]', 8, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(91, 'Print preview berguna untuk?', 'Printing', 'per_materi', 'word', 'pilihan_ganda', '[\"Lihat layout sebelum print\", \"Menghemat kertas\", \"Cek page break\", \"Semua benar\"]', 9, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(92, 'Cara membuat surat resmi dengan Word?', 'Dokumen Resmi', 'per_materi', 'word', 'pilihan_ganda', '[\"Template surat\", \"Format manual\", \"Mail merge\", \"Copy template dari internet\"]', 10, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(93, 'Shortcut keyboard yang paling berguna di Word?', 'Efisiensi Kerja', 'per_materi', 'word', 'pilihan_ganda', '[\"Ctrl+C, Ctrl+V (Copy Paste)\", \"Ctrl+Z (Undo)\", \"Ctrl+S (Save)\", \"Semua penting\"]', 11, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(94, 'Cara membuat CV yang baik dengan Word?', 'Aplikasi Praktis', 'per_materi', 'word', 'pilihan_ganda', '[\"Template CV Word\", \"Format sendiri\", \"Table untuk layout\", \"Kombinasi template dan edit\"]', 12, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(95, 'Fitur Find & Replace berguna untuk?', 'Editing Efisien', 'per_materi', 'word', 'pilihan_ganda', '[\"Mencari kata tertentu\", \"Mengganti kata secara massal\", \"Koreksi typo\", \"Semua benar\"]', 13, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(96, 'Cara membuat dokumen Word yang rapi?', 'Document Design', 'per_materi', 'word', 'pilihan_ganda', '[\"Konsisten font dan spacing\", \"Gunakan heading styles\", \"Alignment yang tepat\", \"Semua benar\"]', 14, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(97, 'Kapan Anda akan menggunakan Microsoft Word?', 'Aplikasi Kehidupan', 'per_materi', 'word', 'pilihan_ganda', '[\"Tugas sekolah/kuliah\", \"Laporan kerja\", \"Surat dan dokumen pribadi\", \"Semua situasi\"]', 15, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(98, 'Saya memahami dasar-dasar penggunaan Microsoft Word', 'Pemahaman Materi', 'per_materi', 'word', 'skala', NULL, 16, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(99, 'Instruktur menjelaskan materi Word dengan jelas dan mudah dipahami', 'Kejelasan Penyampaian', 'per_materi', 'word', 'skala', NULL, 17, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(100, 'Tempo dan durasi pembelajaran Word sesuai dengan kemampuan saya', 'Kesesuaian Pembelajaran', 'per_materi', 'word', 'skala', NULL, 18, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(101, 'Praktik dan latihan Word membantu pemahaman materi', 'Kualitas Praktik', 'per_materi', 'word', 'skala', NULL, 19, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(102, 'Materi Word yang dipelajari akan berguna untuk masa depan saya', 'Manfaat Praktis', 'per_materi', 'word', 'skala', NULL, 20, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(103, 'Kritik dan saran untuk perbaikan pembelajaran Microsoft Word', 'Feedback', 'per_materi', 'word', 'isian', NULL, 21, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(104, 'Hal lain yang ingin ditambahkan terkait materi Word', 'Feedback', 'per_materi', 'word', 'isian', NULL, 22, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(105, 'Fungsi dasar Excel yang paling sering digunakan?', 'Fungsi Dasar', 'per_materi', 'excel', 'pilihan_ganda', '[\"SUM (penjumlahan)\", \"AVERAGE (rata-rata)\", \"COUNT (menghitung)\", \"MAX/MIN (nilai tertinggi/terendah)\"]', 1, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(106, 'Cara terbaik untuk input data angka di Excel?', 'Input Data', 'per_materi', 'excel', 'pilihan_ganda', '[\"Ketik langsung di cell\", \"Copy paste dari sumber lain\", \"Import dari file lain\", \"Semua cara bisa digunakan\"]', 2, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(107, 'Formula Excel yang paling berguna untuk perhitungan?', 'Formula Excel', 'per_materi', 'excel', 'pilihan_ganda', '[\"=SUM(A1:A10)\", \"=AVERAGE(A1:A10)\", \"=IF(kondisi, true, false)\", \"=VLOOKUP untuk mencari data\"]', 3, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(108, 'Cara membuat grafik/chart di Excel?', 'Visualisasi Data', 'per_materi', 'excel', 'pilihan_ganda', '[\"Insert > Chart\", \"Pilih data lalu Insert Chart\", \"Recommended Charts\", \"Semua benar\"]', 4, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(109, 'Jenis chart yang paling cocok untuk data penjualan?', 'Jenis Chart', 'per_materi', 'excel', 'pilihan_ganda', '[\"Bar Chart\", \"Line Chart\", \"Pie Chart\", \"Tergantung jenis analisis\"]', 5, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(110, 'Cara memformat angka sebagai mata uang (Rupiah)?', 'Format Cell', 'per_materi', 'excel', 'pilihan_ganda', '[\"Format Cells > Currency\", \"Ketik Rp manual\", \"Number format\", \"Accounting format\"]', 6, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(111, 'Fitur filter Excel berguna untuk?', 'Filter Data', 'per_materi', 'excel', 'pilihan_ganda', '[\"Menyaring data tertentu\", \"Mencari data spesifik\", \"Mengurutkan data\", \"Semua benar\"]', 7, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(112, 'Cara membuat tabel yang rapi di Excel?', 'Format Tabel', 'per_materi', 'excel', 'pilihan_ganda', '[\"Insert > Table\", \"Format as Table\", \"Border dan shading\", \"Semua teknik bisa digunakan\"]', 8, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(113, 'Shortcut keyboard Excel yang paling berguna?', 'Efisiensi Kerja', 'per_materi', 'excel', 'pilihan_ganda', '[\"Ctrl+C, Ctrl+V (Copy Paste)\", \"F2 untuk edit cell\", \"Ctrl+Z (Undo)\", \"Semua penting\"]', 9, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(114, 'Cara menyimpan file Excel yang aman?', 'File Management', 'per_materi', 'excel', 'pilihan_ganda', '[\"Save As dengan nama jelas\", \"Auto Save aktif\", \"Backup file\", \"Password protect\"]', 10, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(115, 'Cara print Excel agar tidak terpotong?', 'Printing', 'per_materi', 'excel', 'pilihan_ganda', '[\"Page Layout > Print Area\", \"Scale to fit\", \"Print Preview dulu\", \"Semua benar\"]', 11, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(116, 'Excel berguna untuk mengelola data?', 'Aplikasi Data', 'per_materi', 'excel', 'pilihan_ganda', '[\"Keuangan pribadi\", \"Inventory barang\", \"Data karyawan\", \"Semua jenis data\"]', 12, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(117, 'Cara menghitung persentase di Excel?', 'Perhitungan Persentase', 'per_materi', 'excel', 'pilihan_ganda', '[\"=(nilai/total)*100\", \"=nilai/total, format %\", \"Fungsi PERCENTAGE\", \"A dan B benar\"]', 13, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(118, 'Perbedaan relative dan absolute reference?', 'Cell Reference', 'per_materi', 'excel', 'pilihan_ganda', '[\"A1 vs $A$1\", \"Berubah vs tetap saat copy\", \"Relative fleksibel, absolute tetap\", \"Semua benar\"]', 14, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(119, 'Kapan Anda akan menggunakan Microsoft Excel?', 'Aplikasi Kehidupan', 'per_materi', 'excel', 'pilihan_ganda', '[\"Mengelola keuangan\", \"Laporan dan analisis kerja\", \"Tugas sekolah/kuliah\", \"Bisnis dan inventory\"]', 15, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(120, 'Saya memahami konsep dasar Excel seperti cell, formula, dan fungsi', 'Pemahaman Materi', 'per_materi', 'excel', 'skala', NULL, 16, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(121, 'Penjelasan formula dan fungsi Excel mudah saya pahami', 'Kejelasan Penyampaian', 'per_materi', 'excel', 'skala', NULL, 17, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(122, 'Tempo pembelajaran Excel sesuai dengan kemampuan saya', 'Kesesuaian Pembelajaran', 'per_materi', 'excel', 'skala', NULL, 18, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(123, 'Praktik membuat spreadsheet dan chart berjalan dengan baik', 'Kualitas Praktik', 'per_materi', 'excel', 'skala', NULL, 19, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(124, 'Skill Excel akan membantu saya dalam mengelola data di masa depan', 'Manfaat Praktis', 'per_materi', 'excel', 'skala', NULL, 20, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(125, 'Kritik dan saran untuk perbaikan pembelajaran Microsoft Excel', 'Feedback', 'per_materi', 'excel', 'isian', NULL, 21, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(126, 'Hal lain yang ingin ditambahkan terkait materi Excel', 'Feedback', 'per_materi', 'excel', 'isian', NULL, 22, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(127, 'Elemen paling penting dalam slide presentasi?', 'Design Slide', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Judul yang jelas\", \"Isi yang ringkas\", \"Visual yang menarik\", \"Semua penting\"]', 1, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(128, 'Cara membuat slide yang tidak membosankan?', 'Engagement', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Banyak teks\", \"Banyak gambar dan visual\", \"Animasi yang banyak\", \"Balance text dan visual\"]', 2, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(129, 'Template PowerPoint berguna untuk?', 'Template Usage', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Menghemat waktu\", \"Design yang konsisten\", \"Tampilan profesional\", \"Semua benar\"]', 3, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(130, 'Cara menambahkan gambar yang tepat di slide?', 'Insert Media', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Insert > Pictures\", \"Online Pictures\", \"Screenshots\", \"Semua cara bisa digunakan\"]', 4, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(131, 'Animation di PowerPoint sebaiknya?', 'Animation Usage', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Digunakan seperlunya\", \"Banyak agar menarik\", \"Tidak usah digunakan\", \"Simple dan konsisten\"]', 5, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(132, 'Transition antar slide yang baik?', 'Slide Transition', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Fade\", \"Slide\", \"No transition\", \"Konsisten saja\"]', 6, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(133, 'Font yang baik untuk presentasi?', 'Typography', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Arial atau Calibri\", \"Times New Roman\", \"Font yang unik\", \"Yang mudah dibaca\"]', 7, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(134, 'Ukuran font minimal untuk presentasi?', 'Font Size', 'per_materi', 'ppt', 'pilihan_ganda', '[\"18 point\", \"24 point\", \"32 point\", \"Tergantung ruangan\"]', 8, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(135, 'Warna background slide yang baik?', 'Color Scheme', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Putih atau terang\", \"Gelap dengan text terang\", \"Sesuai tema\", \"Kontras yang jelas\"]', 9, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(136, 'Cara membuat slide handout?', 'Handout', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Print > Handouts\", \"Export to PDF\", \"Copy slide ke Word\", \"A dan B benar\"]', 10, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(137, 'Slide master berguna untuk?', 'Slide Master', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Konsistensi design\", \"Logo di semua slide\", \"Format yang sama\", \"Semua benar\"]', 11, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(138, 'Cara present yang baik dengan PowerPoint?', 'Presentation Skills', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Slideshow mode\", \"Presenter view\", \"Notes untuk panduan\", \"Semua teknik berguna\"]', 12, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(139, 'Video di PowerPoint sebaiknya?', 'Video Integration', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Embedded dalam file\", \"Linked dari file lain\", \"Streaming online\", \"Tergantung kebutuhan\"]', 13, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(140, 'Cara backup presentasi penting?', 'File Management', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Save di multiple lokasi\", \"Export ke PDF\", \"Upload ke cloud\", \"Semua cara backup\"]', 14, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(141, 'Kapan Anda akan menggunakan PowerPoint?', 'Aplikasi Kehidupan', 'per_materi', 'ppt', 'pilihan_ganda', '[\"Presentasi di tempat kerja\", \"Tugas sekolah/kuliah\", \"Proposal bisnis\", \"Presentasi personal\"]', 15, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(142, 'Saya dapat membuat presentasi PowerPoint yang menarik dan efektif', 'Pemahaman Materi', 'per_materi', 'ppt', 'skala', NULL, 16, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(143, 'Materi tentang design dan layout slide dijelaskan dengan baik', 'Kejelasan Penyampaian', 'per_materi', 'ppt', 'skala', NULL, 17, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(144, 'Tempo pembelajaran PowerPoint sesuai dengan kemampuan saya', 'Kesesuaian Pembelajaran', 'per_materi', 'ppt', 'skala', NULL, 18, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(145, 'Praktik membuat presentasi dan animasi berjalan dengan lancar', 'Kualitas Praktik', 'per_materi', 'ppt', 'skala', NULL, 19, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(146, 'Skill PowerPoint akan berguna untuk presentasi di masa depan', 'Manfaat Praktis', 'per_materi', 'ppt', 'skala', NULL, 20, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(147, 'Kritik dan saran untuk perbaikan pembelajaran Microsoft PowerPoint', 'Feedback', 'per_materi', 'ppt', 'isian', NULL, 21, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(148, 'Hal lain yang ingin ditambahkan terkait materi PowerPoint', 'Feedback', 'per_materi', 'ppt', 'isian', NULL, 22, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(149, 'Browser internet yang paling Anda sukai?', 'Browser Preference', 'per_materi', 'internet', 'pilihan_ganda', '[\"Google Chrome\", \"Mozilla Firefox\", \"Microsoft Edge\", \"Yang paling cepat\"]', 1, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(150, 'Cara mencari informasi efektif di Google?', 'Search Skills', 'per_materi', 'internet', 'pilihan_ganda', '[\"Kata kunci spesifik\", \"Tanda kutip untuk frasa\", \"Filter hasil pencarian\", \"Semua teknik berguna\"]', 2, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(151, 'Email yang baik untuk keperluan resmi?', 'Email Etiquette', 'per_materi', 'internet', 'pilihan_ganda', '[\"Gmail\", \"Yahoo\", \"Outlook\", \"Yang profesional dan mudah diingat\"]', 3, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(152, 'Subject email yang baik?', 'Email Subject', 'per_materi', 'internet', 'pilihan_ganda', '[\"Jelas dan spesifik\", \"Singkat tapi informatif\", \"Mencerminkan isi email\", \"Semua benar\"]', 4, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(153, 'Cara mengirim file attachment yang aman?', 'Email Attachment', 'per_materi', 'internet', 'pilihan_ganda', '[\"Scan virus dulu\", \"Compress jika besar\", \"Cek ukuran file\", \"Semua langkah penting\"]', 5, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(154, 'Password yang kuat untuk email?', 'Password Security', 'per_materi', 'internet', 'pilihan_ganda', '[\"Kombinasi huruf dan angka\", \"Minimal 8 karakter\", \"Tidak mudah ditebak\", \"Semua kriteria penting\"]', 6, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(155, 'Cara menghindari spam email?', 'Spam Protection', 'per_materi', 'internet', 'pilihan_ganda', '[\"Jangan buka email mencurigakan\", \"Gunakan spam filter\", \"Jangan share email sembarangan\", \"Semua benar\"]', 7, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(156, 'Download file dari internet yang aman?', 'Safe Download', 'per_materi', 'internet', 'pilihan_ganda', '[\"Dari situs terpercaya\", \"Scan dengan antivirus\", \"Cek ekstensi file\", \"Semua langkah penting\"]', 8, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(157, 'Social media yang paling berguna untuk networking?', 'Social Media', 'per_materi', 'internet', 'pilihan_ganda', '[\"Facebook\", \"Instagram\", \"LinkedIn\", \"Tergantung tujuan\"]', 9, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(158, 'Cara backup data penting?', 'Data Backup', 'per_materi', 'internet', 'pilihan_ganda', '[\"Cloud storage (Google Drive)\", \"External hard disk\", \"Email ke diri sendiri\", \"Multiple backup location\"]', 10, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(159, 'E-commerce yang aman untuk belanja online?', 'Online Shopping', 'per_materi', 'internet', 'pilihan_ganda', '[\"Yang ada sertifikat keamanan\", \"Review dan rating baik\", \"Payment gateway terpercaya\", \"Semua kriteria penting\"]', 11, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(160, 'Cara mengecek berita hoax di internet?', 'Digital Literacy', 'per_materi', 'internet', 'pilihan_ganda', '[\"Cek sumber berita\", \"Cross-check di situs lain\", \"Lihat tanggal publikasi\", \"Semua cara verification\"]', 12, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(161, 'Internet banking yang aman?', 'Online Banking', 'per_materi', 'internet', 'pilihan_ganda', '[\"Gunakan jaringan pribadi\", \"Logout setelah selesai\", \"Jangan save password\", \"Semua praktik keamanan\"]', 13, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(162, 'Cara mengatur privacy di social media?', 'Privacy Settings', 'per_materi', 'internet', 'pilihan_ganda', '[\"Setting profile private\", \"Limit info personal\", \"Review friend/follower\", \"Semua setting privacy\"]', 14, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(163, 'Kapan Anda akan menggunakan internet?', 'Internet Usage', 'per_materi', 'internet', 'pilihan_ganda', '[\"Komunikasi dan email\", \"Mencari informasi\", \"Belanja dan hiburan\", \"Semua aktivitas digital\"]', 15, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(164, 'Saya memahami cara browsing internet dengan aman dan efektif', 'Pemahaman Materi', 'per_materi', 'internet', 'skala', NULL, 16, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(165, 'Materi tentang keamanan internet dan email dijelaskan dengan jelas', 'Kejelasan Penyampaian', 'per_materi', 'internet', 'skala', NULL, 17, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(166, 'Tempo pembelajaran internet sesuai dengan kemampuan saya', 'Kesesuaian Pembelajaran', 'per_materi', 'internet', 'skala', NULL, 18, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(167, 'Praktik menggunakan browser dan email mudah diikuti', 'Kualitas Praktik', 'per_materi', 'internet', 'skala', NULL, 19, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(168, 'Skill internet dan email akan membantu komunikasi dan kerja saya', 'Manfaat Praktis', 'per_materi', 'internet', 'skala', NULL, 20, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(169, 'Kritik dan saran untuk perbaikan pembelajaran Internet & Email', 'Feedback', 'per_materi', 'internet', 'isian', NULL, 21, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(170, 'Hal lain yang ingin ditambahkan terkait materi Internet', 'Feedback', 'per_materi', 'internet', 'isian', NULL, 22, 1, '2025-07-10 12:26:11', '2025-07-10 12:26:11'),
(171, 'Apa saran Anda untuk meningkatkan kelengkapan pembahasan pada materi ini?', 'Kelengkapan Pembahasan', 'per_materi', 'word', 'skala', NULL, 0, 1, '2025-07-10 14:38:49', '2025-07-10 14:38:49'),
(173, 'Menurut Anda, tingkat kesulitan materi Microsoft Word yang telah dipelajari adalah?', 'Tingkat Kesulitan', 'per_materi', 'word', 'pilihan_ganda', '[\"Sangat Mudah\", \"Mudah\", \"Sedang\", \"Sulit\", \"Sangat Sulit\"]', 1, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(174, 'Bagaimana kualitas contoh dan latihan yang diberikan dalam materi Microsoft Word?', 'Kualitas Contoh/Latihan', 'per_materi', 'word', 'pilihan_ganda', '[\"Sangat Baik\", \"Baik\", \"Cukup\", \"Kurang\", \"Sangat Kurang\"]', 2, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(175, 'Seberapa jelas penjelasan fitur-fitur Microsoft Word yang diberikan?', 'Kejelasan Materi', 'per_materi', 'word', 'pilihan_ganda', '[\"Sangat Jelas\", \"Jelas\", \"Cukup Jelas\", \"Kurang Jelas\", \"Tidak Jelas\"]', 3, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(176, 'Berikan rating untuk kemudahan pembelajaran materi Microsoft Word (1=Sangat Sulit, 5=Sangat Mudah)', 'Kemudahan Pembelajaran', 'per_materi', 'word', 'skala', NULL, 4, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(177, 'Seberapa bermanfaat materi Microsoft Word untuk kebutuhan praktis Anda? (1=Tidak Bermanfaat, 5=Sangat Bermanfaat)', 'Manfaat Praktis', 'per_materi', 'word', 'skala', NULL, 5, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(178, 'Rating kepuasan Anda terhadap durasi pembelajaran Microsoft Word (1=Sangat Tidak Puas, 5=Sangat Puas)', 'Durasi Pembelajaran', 'per_materi', 'word', 'skala', NULL, 6, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(179, 'Tuliskan saran Anda untuk meningkatkan kualitas pembelajaran Microsoft Word di LKP ini', 'Kualitas Contoh/Latihan', 'per_materi', 'word', 'isian', NULL, 7, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(180, 'Apa fitur Microsoft Word yang paling sulit dipahami dan mengapa?', 'Tingkat Kesulitan', 'per_materi', 'word', 'isian', NULL, 8, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(181, 'Bagaimana penilaian Anda terhadap fasilitas komputer di LKP ini?', 'Fasilitas LKP', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Sangat Baik\",\"Baik\",\"Cukup\",\"Kurang\"]', 1, 1, '2025-07-10 16:47:50', '2025-07-13 08:07:10'),
(182, 'Apakah Anda akan merekomendasikan LKP ini kepada orang lain?', 'Rekomendasi ke Orang Lain', 'akhir_kursus', NULL, 'pilihan_ganda', '[\"Sangat Merekomendasikan\", \"Merekomendasikan\", \"Mungkin\", \"Tidak Merekomendasikan\", \"Sangat Tidak Merekomendasikan\"]', 2, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(183, 'Rating kepuasan Anda terhadap kualitas instruktur secara keseluruhan (1=Sangat Tidak Puas, 5=Sangat Puas)', 'Kualitas Instruktur', 'akhir_kursus', NULL, 'skala', NULL, 3, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(184, 'Seberapa puas Anda dengan pelayanan administrasi LKP? (1=Sangat Tidak Puas, 5=Sangat Puas)', 'Administrasi/Pelayanan', 'akhir_kursus', NULL, 'skala', NULL, 4, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(185, 'Rating kepuasan keseluruhan Anda terhadap LKP ini (1=Sangat Tidak Puas, 5=Sangat Puas)', 'Kepuasan Keseluruhan', 'akhir_kursus', NULL, 'skala', NULL, 5, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(186, 'Tuliskan kesan dan saran Anda untuk perbaikan LKP ke depannya', 'Kepuasan Keseluruhan', 'akhir_kursus', NULL, 'isian', NULL, 6, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50'),
(187, 'Apa yang paling berkesan dari pengalaman belajar Anda di LKP ini?', 'Pencapaian Tujuan', 'akhir_kursus', NULL, 'isian', NULL, 7, 1, '2025-07-10 16:47:50', '2025-07-10 16:47:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id_siswa` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('Laki-Laki','Perempuan') DEFAULT NULL,
  `pendidikan_terakhir` enum('SD','SLTP','SLTA','D1','D2','S1','S2','S3') DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat_lengkap` text DEFAULT NULL,
  `pas_foto` varchar(255) DEFAULT NULL,
  `ktp` varchar(255) DEFAULT NULL,
  `kk` varchar(255) DEFAULT NULL,
  `ijazah` varchar(255) DEFAULT NULL,
  `status_aktif` enum('aktif','nonaktif') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id_siswa`, `id_user`, `id_kelas`, `nik`, `nama`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `pendidikan_terakhir`, `no_hp`, `email`, `alamat_lengkap`, `pas_foto`, `ktp`, `kk`, `ijazah`, `status_aktif`) VALUES
(8, NULL, 1, '6309077107050002', 'Norlaila Hasanah', 'Tabalong', '2002-08-07', 'Perempuan', 'S1', '082213594215', 'lailahasanah02@gmail.com', 'Ds. Kupang Nunding', '1750252872_6852bd48d28ef.jpg', '1751585653_ktp_686713755fd67.pdf', '', '', 'aktif'),
(12, NULL, 1, '6309077107050009', 'Muhammad Rizki Nugraha', 'Tabalong', '2001-09-08', 'Laki-Laki', 'D2', '082213592100', 'rikzkinugraha02@gmail.com', 'Kupang Nunding', '', '', '', '', 'aktif'),
(14, 8, 1, '6309077107050008', 'Almanida ', 'Banjarmasin', '2002-08-08', 'Perempuan', 'D1', '0822135942000', 'almanida@gmail.com', 'Tanjung Selatan', '1753450407_siswa_14_688387a78cca1.jpg', '', '', '', 'aktif');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','instruktur','siswa') NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expire` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`id_user`, `username`, `password`, `role`, `remember_token`, `reset_token`, `reset_token_expire`, `created_at`) VALUES
(2, 'mutiyarahmah', '$2y$10$wFZSXpfKhde7LH8NAVKFAueHxdL4YH2fbYxkVYSXFH0MTZej2bQqq', 'instruktur', NULL, NULL, NULL, '2025-06-28 13:21:28'),
(3, 'fetyfatimah', '$2y$10$hu37l/sRprS7vIRI7IgND.owVORrYvdz5MWxVcdnR9fOA/Ai12VJe', 'instruktur', NULL, NULL, NULL, '2025-06-28 13:21:28'),
(6, 'Rika Apliana', '$2y$10$4cWKCyb0QwpzpOSFm9uHgOQZSVugqDzB1KqJc1gE.FJQGZQeW2tfG', 'admin', NULL, '14d21782598889a82a6d61ea6859121d9f25afca70b20579f7a75b831d34608d', '2025-06-28 19:58:55', '2025-06-28 13:49:54'),
(8, 'almanida', '$2y$10$u5dlP1sxhobmb5K9LsGMvOcoDHnpHMID.A76P8uxs4DoN8uS/DJze', 'siswa', NULL, NULL, NULL, '2025-06-28 18:15:11'),
(20, 'muhammadfa_2025', '$2y$10$.92FSwPsZpO.MlzvH6L7muWcf6qRJPQcZOFGTtT.4CFQKUhgmYe7C', 'siswa', NULL, NULL, NULL, '2025-07-22 07:16:41');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi_instruktur`
--
ALTER TABLE `absensi_instruktur`
  ADD PRIMARY KEY (`id_absen`),
  ADD KEY `id_instruktur` (`id_instruktur`);

--
-- Indeks untuk tabel `absensi_siswa`
--
ALTER TABLE `absensi_siswa`
  ADD PRIMARY KEY (`id_absen`),
  ADD KEY `id_siswa` (`id_siswa`),
  ADD KEY `id_jadwal` (`id_jadwal`);

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `evaluasi`
--
ALTER TABLE `evaluasi`
  ADD PRIMARY KEY (`id_evaluasi`),
  ADD KEY `id_siswa` (`id_siswa`),
  ADD KEY `id_periode` (`id_periode`);

--
-- Indeks untuk tabel `gelombang`
--
ALTER TABLE `gelombang`
  ADD PRIMARY KEY (`id_gelombang`);

--
-- Indeks untuk tabel `instruktur`
--
ALTER TABLE `instruktur`
  ADD PRIMARY KEY (`id_instruktur`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD KEY `id_kelas` (`id_kelas`),
  ADD KEY `id_instruktur` (`id_instruktur`);

--
-- Indeks untuk tabel `jawaban_evaluasi`
--
ALTER TABLE `jawaban_evaluasi`
  ADD PRIMARY KEY (`id_jawaban`),
  ADD KEY `id_evaluasi` (`id_evaluasi`),
  ADD KEY `id_pertanyaan` (`id_pertanyaan`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id_kelas`),
  ADD KEY `id_gelombang` (`id_gelombang`),
  ADD KEY `id_instruktur` (`id_instruktur`);

--
-- Indeks untuk tabel `materi`
--
ALTER TABLE `materi`
  ADD PRIMARY KEY (`id_materi`),
  ADD KEY `id_instruktur` (`id_instruktur`),
  ADD KEY `id_kelas` (`id_kelas`);

--
-- Indeks untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD PRIMARY KEY (`id_nilai`),
  ADD KEY `id_siswa` (`id_siswa`),
  ADD KEY `id_kelas` (`id_kelas`);

--
-- Indeks untuk tabel `pendaftar`
--
ALTER TABLE `pendaftar`
  ADD PRIMARY KEY (`id_pendaftar`),
  ADD KEY `idx_id_gelombang` (`id_gelombang`);

--
-- Indeks untuk tabel `pengaturan_pendaftaran`
--
ALTER TABLE `pengaturan_pendaftaran`
  ADD PRIMARY KEY (`id_pengaturan`),
  ADD KEY `id_gelombang` (`id_gelombang`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indeks untuk tabel `periode_evaluasi`
--
ALTER TABLE `periode_evaluasi`
  ADD PRIMARY KEY (`id_periode`),
  ADD KEY `id_gelombang` (`id_gelombang`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indeks untuk tabel `pertanyaan_evaluasi`
--
ALTER TABLE `pertanyaan_evaluasi`
  ADD PRIMARY KEY (`id_pertanyaan`),
  ADD KEY `idx_pertanyaan_jenis_materi` (`jenis_evaluasi`,`materi_terkait`),
  ADD KEY `idx_pertanyaan_active` (`is_active`),
  ADD KEY `idx_pertanyaan_order` (`question_order`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id_siswa`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_kelas` (`id_kelas`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi_instruktur`
--
ALTER TABLE `absensi_instruktur`
  MODIFY `id_absen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `absensi_siswa`
--
ALTER TABLE `absensi_siswa`
  MODIFY `id_absen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `evaluasi`
--
ALTER TABLE `evaluasi`
  MODIFY `id_evaluasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `gelombang`
--
ALTER TABLE `gelombang`
  MODIFY `id_gelombang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `instruktur`
--
ALTER TABLE `instruktur`
  MODIFY `id_instruktur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT untuk tabel `jawaban_evaluasi`
--
ALTER TABLE `jawaban_evaluasi`
  MODIFY `id_jawaban` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=573;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id_kelas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `materi`
--
ALTER TABLE `materi`
  MODIFY `id_materi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `nilai`
--
ALTER TABLE `nilai`
  MODIFY `id_nilai` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pendaftar`
--
ALTER TABLE `pendaftar`
  MODIFY `id_pendaftar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_pendaftaran`
--
ALTER TABLE `pengaturan_pendaftaran`
  MODIFY `id_pengaturan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `periode_evaluasi`
--
ALTER TABLE `periode_evaluasi`
  MODIFY `id_periode` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `pertanyaan_evaluasi`
--
ALTER TABLE `pertanyaan_evaluasi`
  MODIFY `id_pertanyaan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id_siswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi_instruktur`
--
ALTER TABLE `absensi_instruktur`
  ADD CONSTRAINT `absensi_instruktur_ibfk_1` FOREIGN KEY (`id_instruktur`) REFERENCES `instruktur` (`id_instruktur`);

--
-- Ketidakleluasaan untuk tabel `absensi_siswa`
--
ALTER TABLE `absensi_siswa`
  ADD CONSTRAINT `absensi_siswa_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  ADD CONSTRAINT `absensi_siswa_ibfk_2` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal` (`id_jadwal`);

--
-- Ketidakleluasaan untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `evaluasi`
--
ALTER TABLE `evaluasi`
  ADD CONSTRAINT `evaluasi_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  ADD CONSTRAINT `evaluasi_ibfk_2` FOREIGN KEY (`id_periode`) REFERENCES `periode_evaluasi` (`id_periode`),
  ADD CONSTRAINT `evaluasi_ibfk_3` FOREIGN KEY (`id_periode`) REFERENCES `periode_evaluasi` (`id_periode`);

--
-- Ketidakleluasaan untuk tabel `instruktur`
--
ALTER TABLE `instruktur`
  ADD CONSTRAINT `instruktur_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`),
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`id_instruktur`) REFERENCES `instruktur` (`id_instruktur`);

--
-- Ketidakleluasaan untuk tabel `jawaban_evaluasi`
--
ALTER TABLE `jawaban_evaluasi`
  ADD CONSTRAINT `jawaban_evaluasi_ibfk_1` FOREIGN KEY (`id_evaluasi`) REFERENCES `evaluasi` (`id_evaluasi`),
  ADD CONSTRAINT `jawaban_evaluasi_ibfk_2` FOREIGN KEY (`id_pertanyaan`) REFERENCES `pertanyaan_evaluasi` (`id_pertanyaan`),
  ADD CONSTRAINT `jawaban_evaluasi_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`);

--
-- Ketidakleluasaan untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `kelas_ibfk_1` FOREIGN KEY (`id_gelombang`) REFERENCES `gelombang` (`id_gelombang`),
  ADD CONSTRAINT `kelas_ibfk_2` FOREIGN KEY (`id_instruktur`) REFERENCES `instruktur` (`id_instruktur`);

--
-- Ketidakleluasaan untuk tabel `materi`
--
ALTER TABLE `materi`
  ADD CONSTRAINT `materi_ibfk_1` FOREIGN KEY (`id_instruktur`) REFERENCES `instruktur` (`id_instruktur`),
  ADD CONSTRAINT `materi_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`);

--
-- Ketidakleluasaan untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD CONSTRAINT `nilai_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  ADD CONSTRAINT `nilai_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`);

--
-- Ketidakleluasaan untuk tabel `pendaftar`
--
ALTER TABLE `pendaftar`
  ADD CONSTRAINT `fk_pendaftar_gelombang` FOREIGN KEY (`id_gelombang`) REFERENCES `gelombang` (`id_gelombang`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengaturan_pendaftaran`
--
ALTER TABLE `pengaturan_pendaftaran`
  ADD CONSTRAINT `pengaturan_pendaftaran_ibfk_1` FOREIGN KEY (`id_gelombang`) REFERENCES `gelombang` (`id_gelombang`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengaturan_pendaftaran_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `periode_evaluasi`
--
ALTER TABLE `periode_evaluasi`
  ADD CONSTRAINT `periode_evaluasi_ibfk_1` FOREIGN KEY (`id_gelombang`) REFERENCES `gelombang` (`id_gelombang`),
  ADD CONSTRAINT `periode_evaluasi_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `admin` (`id_admin`);

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`),
  ADD CONSTRAINT `siswa_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
