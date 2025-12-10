<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$database = new Database();
$db = $database->getConnection();

// Ambil data UKM aktif
$query = "SELECT u.*, k.nama_kategori FROM ukm u LEFT JOIN kategori_ukm k ON u.kategori_id = k.id WHERE u.status = 'aktif' ORDER BY u.nama_ukm";
$stmt = $db->prepare($query);
$stmt->execute();
$ukm_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil berita terbaru
$query_berita = "SELECT b.*, u.nama_ukm FROM berita b JOIN ukm u ON b.ukm_id = u.id WHERE b.status = 'published' ORDER BY b.tanggal_publikasi DESC LIMIT 5";
$stmt_berita = $db->prepare($query_berita);
$stmt_berita->execute();
$berita_list = $stmt_berita->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem UKM - Politeknik Negeri Lampung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-gray: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .ukm-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
        }
        
        .ukm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .ukm-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        
        .category-badge {
            background: var(--secondary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .stats-section {
            background: var(--light-gray);
            padding: 60px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .news-section {
            padding: 60px 0;
        }
        
        .news-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .btn-primary-custom {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        footer {
            background: var(--primary-color);
            color: white;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-university"></i>
                <strong>UKM Polinela</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ukm/index.php">Daftar UKM</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#berita">Berita</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard Admin
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="mahasiswa/dashboard.php">
                                    <i class="fas fa-user"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">
                                <i class="fas fa-user-plus"></i> Daftar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h1 class="display-4 fw-bold mb-4">
                        Selamat Datang di Sistem UKM<br>
                        <span class="text-warning">Politeknik Negeri Lampung</span>
                    </h1>
                    <p class="lead mb-4">
                        Bergabunglah dengan berbagai Unit Kegiatan Mahasiswa dan kembangkan potensi diri Anda!
                        Temukan komunitas yang sesuai dengan minat dan bakat Anda.
                    </p>
                    <?php if (!isLoggedIn()): ?>
                        <a href="auth/register.php" class="btn btn-warning btn-lg btn-primary-custom me-3">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </a>
                        <a href="ukm/index.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-list"></i> Lihat UKM
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <?php
                // Hitung statistik
                $stmt_total_ukm = $db->query("SELECT COUNT(*) FROM ukm WHERE status = 'aktif'");
                $total_ukm = $stmt_total_ukm->fetchColumn();

                $stmt_total_mahasiswa = $db->query("SELECT COUNT(*) FROM mahasiswa");
                $total_mahasiswa = $stmt_total_mahasiswa->fetchColumn();

                $stmt_total_pendaftar = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'diterima'");
                $total_pendaftar = $stmt_total_pendaftar->fetchColumn();
                ?>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_ukm ?></div>
                        <h5>UKM Aktif</h5>
                        <p class="text-muted">Unit Kegiatan Mahasiswa yang tersedia</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_mahasiswa ?></div>
                        <h5>Mahasiswa Terdaftar</h5>
                        <p class="text-muted">Total mahasiswa dalam sistem</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_pendaftar ?></div>
                        <h5>Anggota Aktif</h5>
                        <p class="text-muted">Mahasiswa yang bergabung dengan UKM</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- UKM List Section -->
    <section id="ukm" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Daftar Unit Kegiatan Mahasiswa</h2>
                    <p class="lead text-muted">Temukan dan bergabung dengan UKM yang sesuai dengan minat Anda</p>
                </div>
            </div>
            <div class="row">
                <?php foreach ($ukm_list as $ukm): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card ukm-card h-100">
                        <div class="card-body text-center">
                            <?php if ($ukm['logo']): ?>
                                <img src="uploads/<?= $ukm['logo'] ?>" alt="Logo <?= $ukm['nama_ukm'] ?>" class="ukm-logo">
                            <?php else: ?>
                                <div class="ukm-logo bg-secondary d-flex align-items-center justify-content-center mx-auto">
                                    <i class="fas fa-users text-white fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-badge"><?= $ukm['nama_kategori'] ?: 'Umum' ?></div>
                            <h5 class="card-title fw-bold"><?= $ukm['nama_ukm'] ?></h5>
                            <p class="card-text text-muted"><?= substr($ukm['deskripsi'], 0, 100) ?>...</p>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> Ketua: <?= $ukm['ketua_umum'] ?>
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="ukm/index.php?= $ukm['id'] ?>" class="btn btn-primary btn-primary-custom">
                                    <i class="fas fa-info-circle"></i> Lihat Detail
                                </a>
                                <?php if (isMahasiswa()): ?>
                                    <a href="mahasiswa/daftar_ukm.php?ukm_id=<?= $ukm['id'] ?>" class="btn btn-outline-success">
                                        <i class="fas fa-plus"></i> Daftar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <?php if (!empty($berita_list)): ?>
    <section id="berita" class="news-section bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Berita & Pengumuman</h2>
                    <p class="lead text-muted">Informasi terbaru dari Unit Kegiatan Mahasiswa</p>
                </div>
            </div>
            <div class="row">
                <?php foreach (array_slice($berita_list, 0, 3) as $berita): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card news-card h-100">
                        <?php if ($berita['gambar']): ?>
                            <img src="uploads/<?= $berita['gambar'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body">
                            <small class="text-primary fw-bold"><?= $berita['nama_ukm'] ?></small>
                            <h5 class="card-title mt-2"><?= $berita['judul'] ?></h5>
                            <p class="card-text text-muted"><?= substr(strip_tags($berita['konten']), 0, 120) ?>...</p>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> <?= formatTanggal($berita['tanggal_publikasi']) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-university"></i> UKM Polinela</h5>
                    <p>Sistem informasi Unit Kegiatan Mahasiswa Politeknik Negeri Lampung untuk memfasilitasi mahasiswa dalam bergabung dengan berbagai organisasi.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>Kontak</h5>
                    <p><i class="fas fa-map-marker-alt"></i> Jl. Soekarno-Hatta No.10, Bandar Lampung</p>
                    <p><i class="fas fa-phone"></i> (0721) 703995</p>
                    <p><i class="fas fa-envelope"></i> info@polinela.ac.id</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Tentang Polinela</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Akademik</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Kemahasiswaan</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Alumni</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> Sistem UKM Politeknik Negeri Lampung. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling untuk anchor links
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

        // Active navbar on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>