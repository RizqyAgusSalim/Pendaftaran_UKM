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
    JOIN ukm u ON b.ukm_count = u.id
    WHERE b.status = 'published'
";
// Perbaiki typo: 'ukm_count' → 'ukm_id'
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
            padding-top: 70px;
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

        .foto-mini {
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .card-text-info {
            font-size: 0.85rem;
            line-height: 1.5;
            margin-top: 0.5rem;
        }
        .card-text-info div {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="./">
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
                                <img src="uploads/<?= htmlspecialchars($ukm['logo']) ?>" alt="Logo <?= htmlspecialchars($ukm['nama_ukm']) ?>" class="ukm-logo">
                            <?php else: ?>
                                <div class="ukm-logo bg-secondary d-flex align-items-center justify-content-center mx-auto">
                                    <i class="fas fa-users text-white fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-badge"><?= htmlspecialchars($ukm['nama_kategori'] ?: 'Umum') ?></div>
                            <h5 class="card-title fw-bold"><?= htmlspecialchars($ukm['nama_ukm']) ?></h5>
                            <p class="card-text text-muted"><?= substr(htmlspecialchars($ukm['deskripsi']), 0, 100) ?>...</p>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> Ketua: <?= htmlspecialchars($ukm['ketua_umum']) ?>
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="ukm/detail.php?id=<?= $ukm['id'] ?>" class="btn btn-primary btn-primary-custom">
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

<!-- ✅ BERITA & KEGIATAN TERBARU -->
<?php if (!empty($berita_list)): ?>
<section id="berita" class="news-section bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="display-5 fw-bold">Berita & Kegiatan Terbaru</h2>
                <p class="lead text-muted">Informasi dari Unit Kegiatan Mahasiswa</p>
            </div>
        </div>
        <div class="row">
            <?php foreach ($berita_list as $berita): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card news-card h-100 shadow-sm border-0">
                    <!-- Gambar -->
                    <?php if (!empty($berita['gambar'])): ?>
                        <?php if ($berita['jenis'] === 'berita'): ?>
                            <img src="uploads/<?= htmlspecialchars($berita['gambar']) ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover; object-position: center;">
                        <?php else: ?>
                            <img src="uploads/kegiatan/<?= htmlspecialchars($berita['gambar']) ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover; object-position: center;">
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                            <?php if ($berita['jenis'] === 'kegiatan'): ?>
                                <i class="fas fa-calendar-alt fa-3x text-white"></i>
                            <?php else: ?>
                                <i class="fas fa-newspaper fa-3x text-white"></i>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Body -->
                    <div class="card-body d-flex flex-column">
                        <!-- Badge Jenis -->
                        <span class="badge <?= $berita['jenis'] === 'kegiatan' ? 'bg-info' : 'bg-primary' ?> text-white mb-2">
                            <?= ucfirst($berita['jenis']) ?>
                        </span>

                        <!-- Nama UKM -->
                        <small class="text-primary fw-bold mb-1"><?= htmlspecialchars($berita['nama_ukm']) ?></small>

                        <!-- Judul -->
                        <h5 class="card-title mt-1"><?= htmlspecialchars($berita['judul']) ?></h5>

                        <!-- ✅ HAPUS: Deskripsi singkat di sini (tidak ditampilkan) -->

                        <!-- Info Penulis & Tanggal -->
                        <small class="text-muted mb-2">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($berita['penulis']) ?> • 
                            <i class="fas fa-calendar"></i> <?= formatTanggal($berita['tanggal']) ?>
                        </small>

                        <!-- Detail Kegiatan (Jika Kegiatan) -->
                        <?php if ($berita['jenis'] === 'kegiatan'): ?>
                            <div class="card-text-info mt-2">
                                <?php
                                $desc = $tanggal = $lokasi = $biaya = '';
                                $dom = new DOMDocument();
                                @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $berita['konten']);
                                $paragraphs = $dom->getElementsByTagName('p');

                                foreach ($paragraphs as $p) {
                                    $text = trim($p->nodeValue);
                                    if (strpos($text, 'Deskripsi:') !== false) {
                                        $desc = str_replace('Deskripsi:', '', $text);
                                    } elseif (strpos($text, 'Tanggal:') !== false) {
                                        $tanggal = str_replace('Tanggal:', '', $text);
                                    } elseif (strpos($text, 'Lokasi:') !== false) {
                                        $lokasi = str_replace('Lokasi:', '', $text);
                                    } elseif (strpos($text, 'Biaya:') !== false) {
                                        $biaya = str_replace('Biaya:', '', $text);
                                    }
                                }
                                ?>
                                <?php if (!empty($desc)): ?>
                                    <div><strong>Deskripsi:</strong> <?= htmlspecialchars(substr(trim($desc), 0, 60)) ?>...</div>
                                <?php endif; ?>
                                <?php if (!empty($tanggal)): ?>
                                    <div><strong>Tanggal:</strong> <?= htmlspecialchars(trim($tanggal)) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($lokasi)): ?>
                                    <div><strong>Lokasi:</strong> <?= htmlspecialchars(trim($lokasi)) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($biaya)): ?>
                                    <div><strong>Biaya:</strong> <?= htmlspecialchars(trim($biaya)) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Untuk BERITA: tampilkan cuplikan singkat di bawah tanggal -->
                            <p class="card-text text-muted mt-2" style="font-size: 0.9rem; line-height: 1.4;">
                                <?= substr(strip_tags($berita['konten']), 0, 100) ?>...
                            </p>
                        <?php endif; ?>

                        <!-- Tombol Lihat Selengkapnya -->
                        <a href="ukm/detail_<?= $berita['jenis'] ?>.php?id=<?= $berita['id'] ?>" 
                           class="btn btn-outline-primary mt-auto py-2">
                            <i class="fas fa-eye me-1"></i> Lihat Selengkapnya
                        </a>

                        <!-- Galeri Foto Kegiatan -->
                        <?php if ($berita['jenis'] === 'kegiatan' && !empty($berita['foto_list'])): ?>
                            <?php
                            $foto_array = [];
                            if (!empty($berita['foto_list'])) {
                                $parts = explode('|||', $berita['foto_list']);
                                foreach ($parts as $part) {
                                    $json_str = str_replace(['{', '}'], '', $part);
                                    $obj_str = '{' . $json_str . '}';
                                    $foto = json_decode($obj_str, true, 512, JSON_UNESCAPED_SLASHES);
                                    if ($foto && isset($foto['foto'])) {
                                        $foto_array[] = $foto;
                                    }
                                }
                            }
                            ?>
                            <hr class="my-3">
                            <div>
                                <small class="fw-bold">Foto Kegiatan:</small>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <?php foreach (array_slice($foto_array, 0, 2) as $foto): ?>
                                        <div style="width: 60px; height: 60px; overflow: hidden; border-radius: 4px; flex-shrink: 0;">
                                            <img src="uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>"
                                                 style="width: 100%; height: 100%; object-fit: cover;"
                                                 alt="<?= htmlspecialchars($foto['keterangan'] ?? 'Foto kegiatan') ?>"
                                                 onerror="this.src='assets/img/no-image.png'">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($foto_array) > 2): ?>
                                    <small class="text-muted mt-2 d-block">+<?= count($foto_array) - 2 ?> foto lainnya</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
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
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>