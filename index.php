<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Lembaga Kursus dan Pelatihan Pradata Komputer Tabalong - Program pelatihan komputer profesional dengan instruktur bersertifikat." />
    <title>Portal Resmi LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/img/favicon.png" alt="Logo" height="30" class="me-2">
                LKP PRADATA KOMPUTER
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-1">
                    <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">Program</a></li>
                    <li class="nav-item"><a class="nav-link" href="pendaftaran.php">Pendaftaran</a></li>
                </ul>
                <a href="pages/auth/login.php" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Masuk
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1>Selamat Datang <br> di Website Pelayanan <br> LKP Pradata Komputer </h1>
                    <p class="lead">
                        Bergabunglah dengan Lembaga Kursus dan Pelatihan Pradata Komputer Tabalong. 
                        Tempat terbaik untuk mengembangkan keterampilan digital dengan program pelatihan 
                        berkualitas dan instruktur bersertifikat.
                    </p>
                    <div class="hero-buttons">
                        <a href="pendaftaran.php" class="btn-hero btn-hero-primary">
                            <i class="bi bi-pencil-square"></i>
                            Daftar Sekarang
                        </a>
                        <a href="#program" class="btn-hero btn-hero-secondary">
                            <i class="bi bi-info-circle"></i>
                            Cek Persyaratan
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 hero-image text-center">
                    <img src="assets/img/OPENING.jpg" alt="Opening LKP Pradata" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="section-title">
                <h2>Tentang LKP Pradata Komputer</h2>
                <p>Lembaga pelatihan komputer terdepan di Tabalong dengan komitmen menghasilkan lulusan yang siap kerja</p>
            </div>
            
            <div class="visi-misi-card">
                <div class="row g-0">
                    <div class="col-lg-6">
                        <div class="visi-section h-100">
                            <h3>Visi Kami</h3>
                            <p>Menjadikan suatu lembaga pendidikan yang menghasilkan sumber daya manusia yang memiliki “can do attitude” dan “problem solving orientation”.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="misi-section h-100">
                            <h3>Misi Kami</h3>
                            <ul class="misi-list">
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Menyelenggarakan pendidikan yang berkualitas berbasis “active learning” dan kreativitas. </span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Membangun karakter pribadi yang memiliki kemauan dan semangat belajar yang tinggi dan bersikap positif.</span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Membantu serta mengoptimalkan kemampuan peserta didik untuk melatih pola pikir dalam penyelesaian masalah.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">10,000+</div>
                    <div class="stat-label">Alumni Sukses</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">25+</div>
                    <div class="stat-label">Instruktur Bersertifikat</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">15+</div>
                    <div class="stat-label">Program Pelatihan</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Tahun Pengalaman</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements Section -->
    <section class="requirements" id="program">
        <div class="container">
            <div class="section-title">
                <h2>Persyaratan Program</h2>
                <p>Syarat dan ketentuan untuk mengikuti program pelatihan di LKP Pradata Komputer</p>
            </div>
            
            <div class="requirements-grid">
                <div class="requirement-card">
                    <h4><i class="bi bi-person-check"></i>Persyaratan Umum</h4>
                    <ul class="requirement-list">
                        <li><i class="bi bi-check-circle-fill"></i>Minimal lulusan SMP/MTs</li>
                        <li><i class="bi bi-check-circle-fill"></i>Usia minimal 17 tahun</li>
                        <li><i class="bi bi-check-circle-fill"></i>Sehat jasmani dan rohani</li>
                        <li><i class="bi bi-check-circle-fill"></i>Memiliki motivasi belajar yang tinggi</li>
                    </ul>
                </div>
                
                <div class="requirement-card">
                    <h4><i class="bi bi-file-earmark-text"></i>Dokumen</h4>
                    <ul class="requirement-list">
                        <li><i class="bi bi-check-circle-fill"></i>Fotocopy ijazah terakhir</li>
                        <li><i class="bi bi-check-circle-fill"></i>Fotocopy KTP/Kartu Pelajar</li>
                        <li><i class="bi bi-check-circle-fill"></i>Fotocopy Kartu Keluarga</li>
                        <li><i class="bi bi-check-circle-fill"></i>Pas foto 4x6 (3 lembar)</li>
                        <li><i class="bi bi-check-circle-fill"></i>Formulir pendaftaran</li>
                    </ul>
                </div>
                
                <div class="requirement-card">
                    <h4><i class="bi bi-clock"></i>Jadwal & Durasi</h4>
                    <ul class="requirement-list">
                        <li><i class="bi bi-check-circle-fill"></i>Kelas pagi: 08.00 - 12.00</li>
                        <li><i class="bi bi-check-circle-fill"></i>Kelas siang: 13.00 - 17.00</li>
                        <li><i class="bi bi-check-circle-fill"></i>Kelas malam: 18.00 - 21.00</li>
                        <li><i class="bi bi-check-circle-fill"></i>Durasi 1,5 bulan per program</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="gallery" id="gallery">
        <div class="container">
            <div class="section-title">
                <h2>Dokumentasi Kegiatan</h2>
                <p>Lihat suasana pembelajaran dan kegiatan di LKP Pradata Komputer</p>
            </div>
            
            <div class="gallery-grid">
                <div class="gallery-item">
                    <img src="assets/img/RUANG KELAS.JPG" alt="Ruang Kelas Modern">
                    <div class="gallery-overlay">
                        <h5>Ruang Kelas Nyaman</h5>
                        <p>Fasilitas pembelajaran dengan teknologi terkini</p>
                    </div>
                </div>
                
                <div class="gallery-item">
                    <img src="assets/img/INSTRUKTUR MENGAJAR.JPG" alt="Instruktur Mengajar">
                    <div class="gallery-overlay">
                        <h5>Instruktur Berpengalaman</h5>
                        <p>Pembelajaran dengan bimbingan ahli di bidangnya</p>
                    </div>
                </div>
                
                <div class="gallery-item">
                    <img src="assets/img/background.jpg" alt="Siswa di Kelas">
                    <div class="gallery-overlay">
                        <h5>Lingkungan Positif</h5>
                        <p>Interaksi aktif antara siswa dan instruktur</p>
                    </div>
                </div>
                
                <div class="gallery-item">
                    <img src="assets/img/KELAS SOFTSKILL.JPG" alt="Kelas Soft Skill">
                    <div class="gallery-overlay">
                        <h5>Pelatihan Soft Skill</h5>
                        <p>Pengembangan kemampuan komunikasi dan leadership</p>
                    </div>
                </div>
                
                <div class="gallery-item">
                    <img src="assets/img/PEMBAGIAN SERTIFIKAT.jpg" alt="Pembagian Sertifikat">
                    <div class="gallery-overlay">
                        <h5>Pembagian Sertifikat</h5>
                        <p>Moment kebanggaan lulusan LKP Pradata</p>
                    </div>
                </div>
                
                <div class="gallery-item">
                    <img src="assets/img/KEGIATAN.jpg" alt="Para Siswa">
                    <div class="gallery-overlay">
                        <h5>Kegiatan LKP</h5>
                        <p>Generasi muda siap menghadapi tantangan digital</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Prestasi Section -->
    <section class="prestasi" id="prestasi">
        <div class="container">
            <div class="section-title">
                <h2>Prestasi & Pencapaian</h2>
                <p>Berbagai prestasi yang telah diraih LKP Pradata Komputer</p>
            </div>
            
            <div class="prestasi-grid">
                <div class="prestasi-card">
                    <img src="assets/img/SERTIFIKAT.jpg" alt="Prestasi LKP">
                    <div class="prestasi-content">
                        <h4>Penghargaan Lembaga Terbaik</h4>
                        <p>LKP Pradata meraih penghargaan sebagai lembaga kursus dan pelatihan komputer terbaik tingkat kabupaten dengan standar kurikulum berkualitas.</p>
                    </div>
                </div>
                
                <div class="prestasi-card">
                    <img src="assets/img/PRESTASI.jpg" alt="Sertifikat ISO">
                    <div class="prestasi-content">
                        <h4>Sertifikasi Standar Mutu</h4>
                        <p>Telah memperoleh sertifikasi standar mutu pendidikan dan pelatihan vokasi dengan tingkat kepuasan peserta didik mencapai 98%.</p>
                    </div>
                </div>
                
                <div class="prestasi-card">
                    <img src="assets/img/ALUMNI.jpg" alt="Alumni Sukses">
                    <div class="prestasi-content">
                        <h4>Alumni Berprestasi</h4>
                        <p>Lebih dari 80% alumni berhasil mendapatkan pekerjaan atau memulai usaha mandiri dalam bidang teknologi informasi dan komputer setelah lulus.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5>Kontak Kami</h5>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Alamat Kantor</h6>
                            <p> Jl. Ketimun S. 21 No. 3A<br>Komplek Pertamina. Tanjung - Tabalong <br>Kalimantan Selatan 71571</p>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Telepon & WhatsApp</h6>
                            <p>(0526) 2023798 / 0822-1359-4215</p>
                        </div>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Informasi Layanan</h5>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Email</h6>
                            <p>info@lkppradata.com<br>awiekpradata@gmail.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Jam Operasional</h6>
                            <p>Senin - Jumat: 08.00 - 21.00<br>Sabtu - Minggu: Tutup</p>
                        </div>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Media Sosial</h5>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-facebook"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Facebook</h6>
                            <p>LKP Pradata Komputer Tabalong</p>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-instagram"></i>
                        </div>
                        <div class="contact-details">
                            <h6>Instagram</h6>
                            <p>@jikamaka_bisa</p>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <div class="contact-icon">
                            <i class="bi bi-youtube"></i>
                        </div>
                        <div class="contact-details">
                            <h6>YouTube</h6>
                            <p>LKP Pradata Computer</p>
                        </div>
                    </div>
                </div>
    </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 LKP Pradata Komputer Tabalong | Developed by Rika Apliana</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.15)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.08)';
            }
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.stat-item, .requirement-card, .gallery-item, .prestasi-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Counter animation for stats
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                const increment = target / 50;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = counter.textContent.replace(/[0-9,]+/, target.toLocaleString());
                        clearInterval(timer);
                    } else {
                        counter.textContent = counter.textContent.replace(/[0-9,]+/, Math.floor(current).toLocaleString());
                    }
                }, 20);
            });
        }

        // Trigger counter animation when stats section is visible
        const statsSection = document.querySelector('.stats-grid');
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        if (statsSection) {
            statsObserver.observe(statsSection);
        }
    </script>
</body>
</html>