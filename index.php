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

// === Ambil BERITA ===
$query_berita = "
    SELECT 
        'berita' AS jenis,
        b.id,
        b.judul,
        b.konten,
        b.gambar,
        b.penulis,
        b.tanggal_publikasi AS tanggal,
        u.nama_ukm,
        u.id AS ukm_id,
        NULL AS foto_list
    FROM berita b
    JOIN ukm u ON b.ukm_id = u.id
    WHERE b.status = 'published'
";
$stmt_berita = $db->prepare($query_berita);
$stmt_berita->execute();
$berita_list = $stmt_berita->fetchAll(PDO::FETCH_ASSOC);

// === Ambil KEGIATAN + FOTO ===
$query_kegiatan = "
    SELECT 
        'kegiatan' AS jenis,
        k.id,
        k.nama_kegiatan AS judul,
        CONCAT(
            '<p><strong>Deskripsi:</strong> ', k.deskripsi_kegiatan, '</p>',
            '<p><strong>Tanggal:</strong> ', 
                DATE_FORMAT(k.tanggal_mulai, '%d %M %Y'),
                IF(k.tanggal_selesai IS NOT NULL AND k.tanggal_selesai != k.tanggal_mulai, 
                   CONCAT(' - ', DATE_FORMAT(k.tanggal_selesai, '%d %M %Y')), ''),
            '</p>',
            '<p><strong>Lokasi:</strong> ', k.lokasi, '</p>',
            IF(k.biaya > 0, CONCAT('<p><strong>Biaya:</strong> Rp ', FORMAT(k.biaya, 0)), '')
        ) AS konten,
        (
            SELECT f.foto 
            FROM foto_kegiatan f 
            WHERE f.kegiatan_id = k.id 
            ORDER BY f.id ASC 
            LIMIT 1
        ) AS gambar,
        u.nama_ukm AS penulis,
        k.created_at AS tanggal,
        u.nama_ukm,
        u.id AS ukm_id,
        GROUP_CONCAT(
            CONCAT('{\"id\":\"', f.id, '\",\"foto\":\"', f.foto, '\",\"keterangan\":\"', COALESCE(f.keterangan, ''), '\"}')
            ORDER BY f.id ASC
            SEPARATOR '|||'
        ) AS foto_list
    FROM kegiatan_ukm k
    JOIN ukm u ON k.ukm_id = u.id
    LEFT JOIN foto_kegiatan f ON f.kegiatan_id = k.id
    WHERE k.status = 'published'
    GROUP BY k.id, u.id, u.nama_ukm, k.nama_kegiatan, k.deskripsi_kegiatan, k.tanggal_mulai, k.tanggal_selesai, k.lokasi, k.biaya, k.created_at
";
$stmt_kegiatan = $db->prepare($query_kegiatan);
$stmt_kegiatan->execute();
$kegiatan_list = $stmt_kegiatan->fetchAll(PDO::FETCH_ASSOC);

// Gabung dan urutkan
$all_news = array_merge($berita_list, $kegiatan_list);
usort($all_news, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});
$berita_list = array_slice($all_news, 0, 6);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipadu UKM - Politeknik Negeri Lampung</title>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            padding-top: 70px;
            line-height: 1.6;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
        }
        
        .ukm-card, .news-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 24px;
            height: 100%;
            background: white;
            opacity: 0;
            transform: translateY(20px);
        }
        .ukm-card.animate-on-scroll, .news-card.animate-on-scroll {
            opacity: 1;
            transform: translateY(0);
        }
        .ukm-card:hover, .news-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .ukm-logo {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 auto 12px;
            border: 3px solid var(--light-bg);
        }
        
        .category-badge {
            background: var(--secondary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }
        
        .stats-section {
            background-color: white;
            padding: 50px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            background: var(--light-bg);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            opacity: 0;
            transform: translateY(20px);
        }
        .stat-card.animate-on-scroll {
            opacity: 1;
            transform: translateY(0);
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .news-section {
            padding: 50px 0;
        }
        .news-img {
            height: 160px;
            object-fit: cover;
            width: 100%;
        }
        
        /* RESPONSIF GRID */
        .ukm-grid, .news-grid {
            display: grid;
            gap: 1.5rem;
        }
        @media (min-width: 576px) { .ukm-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 768px) { .ukm-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 992px) { .ukm-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (min-width: 1200px) { .ukm-grid { grid-template-columns: repeat(5, 1fr); } }
        @media (min-width: 576px) { .news-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 992px) { .news-grid { grid-template-columns: repeat(3, 1fr); } }

        /* MOBILE OPTIMIZATION */
        @media (max-width: 575.98px) {
            .hero-section {
                padding: 60px 15px;
            }
            .hero-section h1 {
                font-size: 1.8rem !important;
            }
            .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            .ukm-card, .news-card {
                margin-bottom: 20px;
            }
        }

        footer {
            background: var(--primary-color);
            color: white;
            padding: 40px 0;
        }
        
        /* SECTION DIVIDER */
        .section-divider {
            position: relative;
            margin: 40px 0;
        }
        .section-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--secondary-color);
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color); position: fixed; top: 0; width: 100%; z-index: 1030;">
        <div class="container">
            <a class="navbar-brand" href="./">
                <i class="fas fa-university"></i>
                <strong>Sipadu UKM</strong>
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
                        <a class="nav-link" href="#berita">Berita & Kegiatan</a>
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

    <!-- HERO SECTION -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <span class="badge bg-warning text-dark mb-3">✨ Sistem Pendaftaran UKM Terpadu</span>
                    <h1 class="display-5 fw-bold mb-3">
                        Selamat Datang di <span style="color: #f1c40f;">Sipadu UKM</span>
                    </h1>
                    <p class="lead mb-4">
                        Platform resmi pendaftaran dan administrasi Unit Kegiatan Mahasiswa<br>
                        <strong>Politeknik Negeri Lampung</strong>
                    </p>
                    <?php if (!isLoggedIn()): ?>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                            <a href="auth/register.php" class="btn btn-lg" style="background-color: var(--accent-color); border-color: var(--accent-color);">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </a>
                            <a href="ukm/index.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-list me-2"></i>Lihat UKM
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS SECTION -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <?php
                $stmt_total_ukm = $db->query("SELECT COUNT(*) FROM ukm WHERE status = 'aktif'");
                $total_ukm = $stmt_total_ukm->fetchColumn();

                $stmt_total_mahasiswa = $db->query("SELECT COUNT(*) FROM mahasiswa");
                $total_mahasiswa = $stmt_total_mahasiswa->fetchColumn();

                $stmt_total_pendaftar = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'diterima'");
                $total_pendaftar = $stmt_total_pendaftar->fetchColumn();
                ?>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_ukm ?></div>
                        <h5 class="fs-5">UKM Aktif</h5>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_mahasiswa ?></div>
                        <h5 class="fs-5">Mahasiswa Terdaftar</h5>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_pendaftar ?></div>
                        <h5 class="fs-5">Anggota Aktif</h5>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- UKM SECTION -->
    <div class="section-divider"></div>
    <section id="ukm" class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="fw-bold">Daftar Unit Kegiatan Mahasiswa</h2>
                <p class="text-muted">Temukan UKM yang sesuai minat Anda</p>
            </div>
            
            <div class="ukm-grid">
                <?php foreach ($ukm_list as $ukm): ?>
                <div class="ukm-card">
                    <div class="card-body text-center">
                        <?php if ($ukm['logo']): ?>
                            <img src="uploads/<?= htmlspecialchars($ukm['logo']) ?>" 
                                 alt="<?= htmlspecialchars($ukm['nama_ukm']) ?>" 
                                 class="ukm-logo"
                                 onerror="this.src='assets/img/logo-placeholder.png'">
                        <?php else: ?>
                            <div class="ukm-logo bg-secondary d-flex align-items-center justify-content-center mx-auto">
                                <i class="fas fa-users text-white"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="category-badge"><?= htmlspecialchars($ukm['nama_kategori'] ?: 'Umum') ?></div>
                        <h6 class="card-title fw-bold mb-2"><?= htmlspecialchars($ukm['nama_ukm']) ?></h6>
                        <p class="card-text text-muted small"><?= substr(htmlspecialchars($ukm['deskripsi']), 0, 80) ?>...</p>
                        
                        <div class="mt-2">
                            <small class="text-muted d-block"><?= htmlspecialchars($ukm['ketua_umum']) ?></small>
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="ukm/detail.php?id=<?= $ukm['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-info-circle"></i> Detail
                            </a>
                            <?php if (isMahasiswa()): ?>
                                <a href="mahasiswa/daftar_ukm.php?ukm_id=<?= $ukm['id'] ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($ukm_list) > 10): ?>
            <div class="text-center mt-4">
                <a href="ukm/index.php" class="btn btn-outline-primary">Lihat Semua UKM</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

<!-- BERITA SECTION -->
<?php if (!empty($berita_list)): ?>
<div class="section-divider"></div>
<section id="berita" class="news-section">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Berita & Kegiatan Terbaru</h2>
            <p class="text-muted">Informasi dari Unit Kegiatan Mahasiswa</p>
        </div>
        
        <div class="news-grid">
            <?php foreach ($berita_list as $berita): ?>
            <div class="news-card">
                <?php if (!empty($berita['gambar'])): ?>
                    <?php if ($berita['jenis'] === 'berita'): ?>
                        <img src="uploads/<?= htmlspecialchars($berita['gambar']) ?>" 
                             class="news-img"
                             loading="lazy"
                             onerror="this.src='assets/img/news-placeholder.png'">
                    <?php else: ?>
                        <img src="uploads/kegiatan/<?= htmlspecialchars($berita['gambar']) ?>" 
                             class="news-img"
                             loading="lazy"
                             onerror="this.src='assets/img/event-placeholder.png'">
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-secondary d-flex align-items-center justify-content-center news-img">
                        <i class="fas <?= $berita['jenis'] === 'kegiatan' ? 'fa-calendar-alt' : 'fa-newspaper' ?> fa-2x text-white"></i>
                    </div>
                <?php endif; ?>

                <div class="card-body p-3">
                    <span class="badge <?= $berita['jenis'] === 'kegiatan' ? 'bg-info' : 'bg-primary' ?> text-white mb-1">
                        <?= ucfirst($berita['jenis']) ?>
                    </span>
                    <small class="text-primary fw-bold d-block mb-1"><?= htmlspecialchars($berita['nama_ukm']) ?></small>
                    <h6 class="card-title mt-1 fs-6" style="font-weight: 500; line-height: 1.4; word-wrap: break-word;">
                        <?= htmlspecialchars($berita['judul']) ?>
                    </h6>
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($berita['penulis']) ?> • 
                        <i class="fas fa-calendar"></i> <?= formatTanggal($berita['tanggal']) ?>
                    </small>
                    
                    <a href="ukm/detail_<?= $berita['jenis'] ?>.php?id=<?= $berita['id'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i> Selengkapnya
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h6><i class="fas fa-university"></i> Sipadu UKM</h6>
                    <p class="small">Sistem Informasi Pendaftaran dan Administrasi Unit Kegiatan Mahasiswa.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h6>Kontak</h6>
                    <p class="small">
                        <i class="fas fa-map-marker-alt"></i> Jl. Soekarno-Hatta No.10, Bandar Lampung<br>
                        <i class="fas fa-phone"></i> (0721) 703995<br>
                        <i class="fas fa-envelope"></i> info@polinela.ac.id
                    </p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-light text-decoration-none">Tentang Polinela</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Akademik</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Kemahasiswaan</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center small">
                &copy; <?= date('Y') ?> Sipadu UKM - Politeknik Negeri Lampung.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Animation on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-on-scroll');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.ukm-card, .news-card, .stat-card').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>