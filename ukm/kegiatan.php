<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Ambil ukm_id dari query string
$ukm_id = isset($_GET['ukm_id']) ? (int)$_GET['ukm_id'] : 0;

if ($ukm_id <= 0) {
    die("<div class='alert alert-danger text-center mt-5'>UKM tidak ditemukan.</div>");
}

$database = new Database();
$db = $database->getConnection();

// Ambil info UKM
$stmt_ukm = $db->prepare("SELECT id, nama_ukm, logo FROM ukm WHERE id = ? AND status = 'aktif'");
$stmt_ukm->execute([$ukm_id]);
$ukm = $stmt_ukm->fetch(PDO::FETCH_ASSOC);

if (!$ukm) {
    die("<div class='alert alert-warning text-center mt-5'>UKM tidak aktif atau tidak ditemukan.</div>");
}

// Ambil daftar kegiatan (hanya yang 'published' atau 'completed')
$stmt_keg = $db->prepare("
    SELECT * FROM kegiatan_ukm 
    WHERE ukm_id = ? AND status IN ('published', 'completed')
    ORDER BY tanggal_mulai DESC
");
$stmt_keg->execute([$ukm_id]);
$kegiatan_list = $stmt_keg->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kegiatan - <?= htmlspecialchars($ukm['nama_ukm']) ?> | UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .header-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .kegiatan-card {
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            margin-bottom: 25px;
        }
        .kegiatan-card:hover {
            transform: translateY(-3px);
        }
        .foto-thumb {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        .badge-status {
            font-size: 0.75em;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header UKM -->
        <div class="header-card p-4 text-center">
            <div class="mb-3">
                <?php if (!empty($ukm['logo'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($ukm['logo']) ?>" alt="Logo <?= htmlspecialchars($ukm['nama_ukm']) ?>" 
                         style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto"
                         style="width: 80px; height: 80px; font-size: 2rem;">
                        <i class="fas fa-users"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h2><?= htmlspecialchars($ukm['nama_ukm']) ?></h2>
            <p class="mb-0">Daftar Kegiatan Terkini</p>
        </div>

        <!-- Daftar Kegiatan -->
        <?php if (empty($kegiatan_list)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5>Belum ada kegiatan yang dipublikasikan</h5>
                <p class="text-muted">Silakan kunjungi kembali lain waktu.</p>
                <a href="../index.php" class="btn btn-outline-primary">Kembali ke Beranda</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($kegiatan_list as $keg): ?>
                    <div class="col-lg-6">
                        <div class="card kegiatan-card h-100">
                            <!-- Gambar Utama (ambil foto pertama) -->
                            <?php
                            $foto_utama = '';
                            $stmt_foto = $db->prepare("SELECT foto FROM foto_kegiatan WHERE kegiatan_id = ? ORDER BY id ASC LIMIT 1");
                            $stmt_foto->execute([$keg['id']]);
                            $foto_row = $stmt_foto->fetch();
                            if ($foto_row) {
                                $foto_utama = $foto_row['foto'];
                            }
                            ?>

                            <?php if ($foto_utama): ?>
                                <img src="../uploads/kegiatan/<?= htmlspecialchars($foto_utama) ?>" 
                                     class="foto-thumb" 
                                     alt="Foto kegiatan">
                            <?php else: ?>
                                <div class="bg-light text-center py-5">
                                    <i class="fas fa-calendar-alt fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($keg['nama_kegiatan']) ?></h5>
                                    <span class="badge <?= $keg['status'] === 'completed' ? 'bg-success' : 'bg-info' ?> text-white badge-status">
                                        <?= ucfirst($keg['status']) ?>
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> 
                                        <?= formatTanggal($keg['tanggal_mulai']) ?>
                                        <?php if (!empty($keg['tanggal_selesai']) && $keg['tanggal_selesai'] !== $keg['tanggal_mulai']): ?>
                                            – <?= formatTanggal($keg['tanggal_selesai']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <?php if (!empty($keg['lokasi'])): ?>
                                    <small class="text-muted d-block mb-2">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($keg['lokasi']) ?>
                                    </small>
                                <?php endif; ?>

                                <?php if (!empty($keg['deskripsi_kegiatan'])): ?>
                                    <p class="card-text small text-muted flex-grow-1">
                                        <?= nl2br(htmlspecialchars(substr($keg['deskripsi_kegiatan'], 0, 150))) ?>...
                                    </p>
                                <?php endif; ?>

                                <?php if ($keg['biaya'] > 0): ?>
                                    <small class="text-muted d-block mb-2">
                                        <i class="fas fa-money-bill"></i> Biaya: Rp <?= number_format($keg['biaya'], 0, ',', '.') ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>