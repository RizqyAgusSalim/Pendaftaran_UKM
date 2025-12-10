<?php
// ukm/index.php
require_once '../config/database.php';
require_once '../config/functions.php';

$database = new Database();
$db = $database->getConnection();

// Filter kategori
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Query untuk mengambil semua UKM
$query = "SELECT u.*, k.nama_kategori 
          FROM ukm u 
          LEFT JOIN kategori_ukm k ON u.kategori_id = k.id";

if (!empty($kategori_filter)) {
    $query .= " WHERE u.kategori_id = :kategori_id";
}

$query .= " ORDER BY u.nama_ukm ASC";

$stmt = $db->prepare($query);
if (!empty($kategori_filter)) {
    $stmt->bindParam(':kategori_id', $kategori_filter);
}
$stmt->execute();
$ukm_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua kategori
$query_kategori = "SELECT * FROM kategori_ukm ORDER BY nama_kategori ASC";
$stmt_kategori = $db->prepare($query_kategori);
$stmt_kategori->execute();
$kategori_list = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar UKM - Politeknik Negeri Lampung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .ukm-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .ukm-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .ukm-logo {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .ukm-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 20px;
        }
        .filter-btn {
            border-radius: 50px;
            padding: 10px 25px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        .filter-btn:hover, .filter-btn.active {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">Unit Kegiatan Mahasiswa</h1>
            <p class="lead mb-4">Politeknik Negeri Lampung</p>
            <p class="mb-0">Temukan dan bergabunglah dengan UKM sesuai minat dan bakatmu!</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="container my-5">
        <div class="text-center mb-4">
            <h4 class="mb-3">Filter Berdasarkan Kategori</h4>
            <div class="d-flex flex-wrap justify-content-center">
                <a href="index.php" class="btn btn-outline-primary filter-btn <?= empty($kategori_filter) ? 'active' : '' ?>">
                    <i class="fas fa-th"></i> Semua
                </a>
                <?php foreach ($kategori_list as $kategori): ?>
                    <a href="index.php?kategori=<?= $kategori['id'] ?>" 
                        class="btn btn-outline-primary filter-btn <?= $kategori_filter == $kategori['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- UKM Cards -->
        <div class="row">
            <?php if (empty($ukm_list)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>Belum ada UKM tersedia</h4>
                        <p>Saat ini belum ada UKM yang terdaftar dalam kategori ini.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($ukm_list as $ukm): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="ukm-card">
                            <div class="ukm-logo">
                                <?php if (!empty($ukm['logo'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($ukm['logo']) ?>" 
                                            alt="<?= htmlspecialchars($ukm['nama_ukm']) ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <i class="fas fa-users fa-4x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($ukm['nama_ukm']) ?></h5>
                                <p class="text-muted mb-2">
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars($ukm['nama_kategori'] ?? 'Umum') ?>
                                    </span>
                                </p>
                                <p class="card-text text-muted" style="min-height: 60px;">
                                    <?= substr(htmlspecialchars($ukm['deskripsi']), 0, 100) ?>...
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($ukm['ketua_umum']) ?>
                                    </small>
                                    <a href="detail.php?id=<?= $ukm['id'] ?>" class="btn btn-primary btn-sm">
                                        Lihat Detail <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Back to Home -->
        <div class="text-center mt-5">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Sistem Pendaftaran UKM - Politeknik Negeri Lampung</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>