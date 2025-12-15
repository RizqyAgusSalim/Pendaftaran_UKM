<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("<div class='alert alert-danger text-center mt-5'>Kegiatan tidak ditemukan.</div>");
}

$database = new Database();
$db = $database->getConnection();

// Ambil kegiatan + info UKM
$stmt = $db->prepare("
    SELECT k.*, u.nama_ukm, u.logo as ukm_logo, u.id as ukm_id
    FROM kegiatan_ukm k
    JOIN ukm u ON k.ukm_id = u.id
    WHERE k.id = ? AND k.status IN ('published', 'completed')
");
$stmt->execute([$id]);
$kegiatan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$kegiatan) {
    die("<div class='alert alert-warning text-center mt-5'>Kegiatan tidak ditemukan atau belum dipublikasikan.</div>");
}

// Ambil SEMUA foto kegiatan
$stmt_foto = $db->prepare("SELECT foto, keterangan FROM foto_kegiatan WHERE kegiatan_id = ? ORDER BY id ASC");
$stmt_foto->execute([$id]);
$foto_list = $stmt_foto->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?> | UKM <?= htmlspecialchars($kegiatan['nama_ukm']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 70px; /* Ruang untuk navbar fixed */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .detail-card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .detail-header {
            background: linear-gradient(135deg, #2c3e50, #e74c3c);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
        }
        .carousel-item img {
            height: 300px;
            object-fit: cover;
        }
        .single-photo {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 0 0 8px 8px;
            margin-bottom: 20px;
        }
        .card-body {
            padding: 1.5rem;
        }
    </style>
</head>
<body>

    <!-- ✅ Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../">
                <i class="fas fa-university"></i>
                <strong>UKM Polinela</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Daftar UKM</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="../#berita">Berita & Kegiatan</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard Admin
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../mahasiswa/dashboard.php">
                                    <i class="fas fa-user"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/register.php">
                                <i class="fas fa-user-plus"></i> Daftar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card detail-card">
                    <!-- Header UKM -->
                    <div class="detail-header">
                        <div class="d-flex align-items-center mb-2">
                            <?php if (!empty($kegiatan['ukm_logo'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($kegiatan['ukm_logo']) ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;" class="me-3">
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($kegiatan['nama_ukm']) ?></h5>
                                <small><i class="fas fa-calendar-alt"></i> Kegiatan UKM</small>
                            </div>
                        </div>
                        <h2 class="mt-2"><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-calendar"></i> 
                            <?= formatTanggal($kegiatan['tanggal_mulai']) ?>
                            <?php if (!empty($kegiatan['tanggal_selesai']) && $kegiatan['tanggal_selesai'] !== $kegiatan['tanggal_mulai']): ?>
                                – <?= formatTanggal($kegiatan['tanggal_selesai']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- ✅ GALERI DI ATAS (SEBELUM LOKASI & DESKRIPSI) -->
                    <?php if (!empty($foto_list)): ?>
                        <?php if (count($foto_list) === 1): ?>
                            <!-- Tampilkan gambar tunggal -->
                            <img src="../uploads/kegiatan/<?= htmlspecialchars($foto_list[0]['foto']) ?>" 
                                 class="single-photo"
                                 alt="<?= htmlspecialchars($foto_list[0]['keterangan'] ?? 'Dokumentasi kegiatan') ?>">
                        <?php else: ?>
                            <!-- Tampilkan carousel jika >1 foto -->
                            <div id="kegiatanCarousel" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner rounded-0">
                                    <?php foreach ($foto_list as $i => $foto): ?>
                                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                            <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>" 
                                                 class="d-block w-100" 
                                                 alt="<?= htmlspecialchars($foto['keterangan'] ?? 'Foto kegiatan') ?>">
                                            <?php if (!empty($foto['keterangan'])): ?>
                                                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 py-1" style="border-radius: 4px;">
                                                    <small><?= htmlspecialchars($foto['keterangan']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#kegiatanCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#kegiatanCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-image fa-2x mb-2"></i><br>
                            Tidak ada dokumentasi foto
                        </div>
                    <?php endif; ?>

                    <!-- Bagian Info & Deskripsi -->
                    <div class="card-body">
                        <!-- Info Detail -->
                        <div class="mb-3">
                            <?php if (!empty($kegiatan['lokasi'])): ?>
                                <p>
                                    <i class="fas fa-map-marker-alt me-2"></i> 
                                    <strong>Lokasi:</strong> <?= htmlspecialchars($kegiatan['lokasi']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($kegiatan['biaya'] > 0): ?>
                                <p>
                                    <i class="fas fa-money-bill me-2"></i> 
                                    <strong>Biaya:</strong> Rp <?= number_format($kegiatan['biaya'], 0, ',', '.') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Deskripsi -->
                        <?php if (!empty($kegiatan['deskripsi_kegiatan'])): ?>
                            <h5 class="mb-2"><i class="fas fa-info-circle"></i> Deskripsi</h5>
                            <div>
                                <?= nl2br(htmlspecialchars_decode($kegiatan['deskripsi_kegiatan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer text-center" style="padding: 1rem;">
                        <a href="../index.php#berita" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Berita & Kegiatan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>