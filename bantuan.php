<?php
session_start();
include 'includes/db.php';

// MENGGUNAKAN CARA MODERN YANG BENAR UNTUK VERSI 6
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Penting untuk SMTPDebug

// Cukup panggil satu file ini dari Composer
require_once __DIR__ . '/vendor/autoload.php';

// Variabel untuk menampung pesan notifikasi
$success_msg = '';
$error_msg = '';

// Logika untuk memproses form
if (isset($_POST['form_kontak_dikirim'])) {

    $mail = new PHPMailer(true);

    try {
        // Aktifkan mode debug untuk melihat proses
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Mematikan log debug
        // Konfigurasi Server SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rikaapliana02@gmail.com';
        $mail->Password   = 'ejit psog kjzn yfhf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Penerima dan Pengirim
        $mail->setFrom('no-reply@lkp-pradata.com', 'Form Kontak Website');
        $mail->addAddress('rikaapliana02@gmail.com', 'Admin LKP Pradata');
        $mail->addReplyTo($_POST['email'], $_POST['nama']);

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = 'Pesan Baru dari Form Kontak: ' . htmlspecialchars($_POST['subjek']);
        $mail->Body    = "Anda telah menerima pesan baru dari form kontak.<br><br>" .
                       "<b>Nama:</b> " . htmlspecialchars($_POST['nama']) . "<br>" .
                       "<b>Email:</b> " . htmlspecialchars($_POST['email']) . "<br><br>" .
                       "<b>Pesan:</b><br>" . nl2br(htmlspecialchars($_POST['pesan']));

        $mail->send();
        $success_msg = 'Pesan Anda berhasil terkirim. Terima kasih!';
        // Nonaktifkan debug setelah sukses agar tidak menampilkan log
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

    } catch (Exception $e) {
        $error_msg = "Pesan gagal terkirim. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bantuan & Tanya Jawab - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png"/>
    
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/fonts.css" />
    <link rel="stylesheet" href="assets/css/styles.css" />

    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-dark: #357ABD;
            --primary-darker: #2868A3;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --bg-light: #f8f9fa;
            --border-color: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
        }
        
        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            padding: 15px 0;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.4rem;
        }
        
        .navbar-nav .nav-link {
            color: var(--text-dark) !important;
            font-weight: 500;
            margin: 0 15px;
            transition: color 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        /* ========== STYLE UNTUK SECTION BENEFIT & IKON BANTUAN ========== */

        .benefits-section {
            padding: 80px 0;
            background-color: var(--bg-light);
        }

        .benefit-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            text-align: left;
            height: 100%;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .benefit-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
        }

        .benefit-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .benefit-card h4 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .benefit-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Floating Help Icon */
        .floating-help-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-help-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.4);
            color: white;
}
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 70%, var(--primary-darker) 100%);
            color: white;
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            border: 2px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 2.8rem !important;
            font-weight: 800 !important;
            line-height: 1.2 !important;
            margin-bottom: 1rem !important;
        }
                
        .hero .lead {
            font-size: 1.2rem !important;
            opacity: 0.9 !important;
            margin-bottom: 1.5rem !important;
        }
        
        .hero-buttons {
            display: flex !important;
            gap: 15px !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
        }
        
        .btn-hero {
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
            font-size: 0.9rem !important;
        }
        
        .btn-hero-primary {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
        }
        
        .btn-hero-primary:hover {
            background: rgba(255, 255, 255, 0.9) !important;
            color: var(--primary-color) !important;
            transform: translateY(-2px) !important;
        }
        
        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.9) !important;
            color: var(--primary-color) !important;
            border: 2px solid transparent !important;
        }
        
        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 1) !important;
            transform: translateY(-2px) !important;
        }
        
        .hero-image {
            position: relative;
            z-index: 2;
        }
        
        .hero-image img {
            width: 100%;
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        /* About Section */
        .about {
            padding: 80px 0;
            background: var(--bg-light);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .visi-misi-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 50px;
        }
        
        .visi-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px;
            position: relative;
        }
        
        .visi-section::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .visi-section h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .visi-section p {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .misi-section {
            padding: 40px;
        }
        
        .misi-section h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        
        .misi-list {
            list-style: none;
            padding: 0;
        }
        
        .misi-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .misi-list li i {
            color: var(--primary-color);
            font-size: 1rem;
            margin-top: 3px;
            flex-shrink: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .stat-item {
            text-align: center;
            background: white;
            border-radius: 12px;
            padding: 30px 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        /* Requirements Section */
        .requirements {
            padding: 80px 0;
            background: white;
        }
        
        .requirements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .requirement-card {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 30px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .requirement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .requirement-card h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .requirement-list {
            list-style: none;
            padding: 0;
        }
        
        .requirement-list li {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .requirement-list li i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        /* Gallery Section */
        .gallery {
            padding: 80px 0;
            background: var(--bg-light);
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
        }
        
        .gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover img {
            transform: scale(1.05);
        }
        
        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover .gallery-overlay {
            transform: translateY(0);
        }

        /* ========== STYLE UNTUK SECTION KURIKULUM (VERSI DISEMPURNAKAN) ========== */

        .kurikulum-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 80px 0;
        }

        .kurikulum-section .section-title h2,
        .kurikulum-section .section-title p {
            color: white;
        }

        /* KARTU: Diubah menjadi putih solid dengan bayangan yang jelas */
        .kurikulum-card {
            background: #ffffff; /* Latar belakang KARTU diubah menjadi PUTIH SOLID */
            border: 1px solid var(--border-color); /* Garis tepi tipis */
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); 
        }

        .kurikulum-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        /* IKON UTAMA: Warnanya kita balik, background biru ikon putih */
        .kurikulum-icon {
            font-size: 2.5rem;
            width: 80px;
            height: 80px;
            margin: 0 auto 25px auto;
            background: var(--primary-color); /* Latar belakang IKON diubah menjadi BIRU */
            color: #ffffff; /* Ikon di dalamnya (W, X) diubah menjadi PUTIH */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* JUDUL KARTU: Warna teks diubah menjadi gelap */
        .kurikulum-card h4 {
            color: var(--text-dark); /* Warna judul diubah menjadi HITAM/GELAP */
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* LIST MATERI: Warna teks diubah menjadi abu-abu */
        .kurikulum-card ul {
            list-style: none;
            padding: 0;
            text-align: left;
            color: #555; /* Warna teks list diubah menjadi ABU-ABU TUA */
            font-weight: 500;
        }

        .kurikulum-card li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* IKON CHECKLIST: Warna diubah menjadi biru */
        .kurikulum-card li i {
            color: var(--primary-color); /* Warna ikon checklist diubah menjadi BIRU */
            font-size: 1.2rem;
        }

        /* TOMBOL CTA: Tidak ada perubahan, sudah bagus */
        .btn-cta-kurikulum {
            background: white;
            color: var(--primary-color);
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-cta-kurikulum:hover {
            background: var(--bg-light);
            color: var(--primary-dark);
            transform: scale(1.05);
        }
        
        /* Prestasi Section */
        .prestasi {
            padding: 80px 0;
            background: white;
        }
        
        .prestasi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .prestasi-card {
            background: var(--bg-light);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .prestasi-card:hover {
            transform: translateY(-5px);
        }
        
        .prestasi-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .prestasi-content {
            padding: 25px;
        }
        
        .prestasi-content h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .prestasi-content p {
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            background: var(--text-dark);
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h5 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .contact-info {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .contact-details h6 {
            margin: 0 0 5px 0;
            color: white;
            font-weight: 600;
        }
        
        .contact-details p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem !important;
            }
            
            .hero-buttons {
                flex-direction: column !important;
                gap: 10px !important;
            }
            
            .btn-hero {
                text-align: center !important;
                justify-content: center !important;
                width: 100% !important;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .visi-misi-card .row {
                flex-direction: column;
            }
            
            .requirements-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 100px 0 60px;
            }
            
            .hero h1 {
                font-size: 1.8rem !important;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .gallery-grid,
            .prestasi-grid {
                grid-template-columns: 1fr;
            }
        }

                /* Styling untuk brand di navbar */
        .navbar-brand-custom {
            /* Mengambil warna biru dari gambar Anda */
            color: #3A82EE !important; 
            
            /* Membuat font menjadi tebal */
            font-weight: bold; 
            
            /* Sedikit memperbesar ukuran font agar lebih terbaca */
            font-size: 1.1rem; 
            
            /* Menghilangkan garis bawah pada link */
            text-decoration: none; 
            
            /* Menambahkan transisi halus untuk efek hover */
            transition: color 0.3s ease-in-out; 
        }

        /* Efek saat kursor mouse diarahkan ke brand */
        .navbar-brand-custom:hover {
            /* Membuat warna sedikit lebih gelap saat disentuh mouse */
            color: #2F69C6 !important; 
        }
    </style>
</head>
<body>
    
    <?php include 'includes/navbar.php'; ?>

    <div class="container" style="margin-top: 100px; margin-bottom: 50px;">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold">Pusat Bantuan</h1>
                    <p class="lead text-muted">Temukan jawaban atas pertanyaan Anda di sini, atau hubungi kami langsung.</p>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= $success_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= $error_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <h3 class="mb-4">Pertanyaan yang Sering Diajukan (FAQ)</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                Apa saja persyaratan untuk mendaftar kursus?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Untuk mendaftar, Anda hanya perlu menyiapkan dokumen digital (format PDF untuk dokumen, dan JPG/PNG untuk pas foto) sebagai berikut: Pas Foto, KTP, Kartu Keluarga, dan Ijazah Pendidikan Terakhir. Pastikan ukuran file tidak lebih dari 5MB.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                Apakah saya akan mendapatkan sertifikat setelah selesai?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Ya, tentu saja. Setiap peserta yang berhasil menyelesaikan seluruh rangkaian kursus dan memenuhi standar kelulusan akan mendapatkan sertifikat resmi dari LKP Pradata Komputer yang dapat digunakan untuk melamar pekerjaan.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                Bagaimana alur selanjutnya setelah saya mendaftar online?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Setelah Anda berhasil mendaftar, tim kami akan melakukan verifikasi berkas. Jika berkas Anda lengkap dan valid, Anda akan menerima email konfirmasi bahwa Anda telah diterima sebagai calon peserta. Informasi mengenai jadwal kelas akan diumumkan lebih lanjut mendekati tanggal dimulainya gelombang kursus.
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-5"> 

                <div class="text-center">
                    <h3 class="mb-2">Masih Punya Pertanyaan?</h3>
                    <p class="lead text-muted mb-4">Kirimkan pertanyaan Anda melalui form di bawah ini.</p>
                </div>
                <form method="POST" action="bantuan.php">
                    <input type="hidden" name="form_kontak_dikirim" value="1">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama" id="nama" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Alamat Email Anda</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subjek" class="form-label">Subjek Pertanyaan</label>
                        <input type="text" class="form-control" name="subjek" id="subjek" required>
                    </div>
                    <div class="mb-3">
                        <label for="pesan" class="form-label">Isi Pesan Anda</label>
                        <textarea class="form-control" name="pesan" id="pesan" rows="5" required></textarea>
                    </div>
                    <div class="text-center">
                       <button type="submit" name="kirim_pesan" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-send me-2"></i>Kirim Pesan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>