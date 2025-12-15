<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("<div class='alert alert-danger text-center mt-5'>Berita tidak ditemukan.</div>");
}

$database = new Database();
$db = $database->getConnection();

// Ambil berita + info UKM
$stmt = $db->prepare("
    SELECT b.*, u.nama_ukm, u.logo as ukm_logo
    FROM berita b
    JOIN ukm u ON b.ukm_id = u.id
    WHERE b.id = ? AND b.status = 'published'
");
$stmt->execute([$id]);
$berita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$berita) {
    die("<div class='alert alert-warning text-center mt-5'>Berita tidak ditemukan atau belum dipublikasikan.</div>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($berita['judul']) ?> | UKM <?= htmlspecialchars($berita['nama_ukm']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding-top: 20px; }
        .detail-card { border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .detail-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; border-radius: 12px 12px 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card detail-card">
                    <!-- Header UKM -->
                    <div class="detail-header p-4">
                        <div class="d-flex align-items-center mb-2">
                            <?php if (!empty($berita['ukm_logo'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($berita['ukm_logo']) ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;" class="me-3">
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($berita['nama_ukm']) ?></h5>
                                <small><i class="fas fa-newspaper"></i> Berita UKM</small>
                            </div>
                        </div>
                        <h2 class="mt-2"><?= htmlspecialchars($berita['judul']) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($berita['penulis']) ?> •
                            <i class="fas fa-calendar"></i> <?= formatTanggal($berita['tanggal_publikasi']) ?>
                        </p>
                    </div>

                    <!-- Gambar berita (jika ada) -->
                    <?php if (!empty($berita['gambar'])): ?>
                        <div class="p-3 text-center">
                            <img src="../uploads/<?= htmlspecialchars($berita['gambar']) ?>" 
                                 class="img-fluid rounded" 
                                 style="max-height: 500px; object-fit: cover;"
                                 alt="Gambar Berita">
                        </div>
                    <?php endif; ?>

                    <!-- Konten lengkap -->
                    <div class="card-body">
                        <div class="content-text">
                            <?= nl2br(htmlspecialchars_decode($berita['konten'])) ?>
                        </div>
                    </div>

                    <div class="card-footer text-center">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>