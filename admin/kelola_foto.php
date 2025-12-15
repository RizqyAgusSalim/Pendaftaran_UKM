<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan login sebagai admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
$ukm_id = $is_superadmin ? null : (int)($_SESSION['ukm_id'] ?? 0);

// Validasi akses
if (!$is_superadmin && empty($_SESSION['ukm_id'])) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

// ----------------------------------------------------
// HAPUS FOTO
// ----------------------------------------------------
if (isset($_GET['hapus_foto'])) {
    $foto_id = (int)$_GET['hapus_foto'];

    if ($is_superadmin) {
        // Superadmin: hapus foto apa pun
        $stmt = $db->prepare("
            SELECT fk.foto, k.nama_kegiatan, u.nama_ukm
            FROM foto_kegiatan fk
            JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
            JOIN ukm u ON k.ukm_id = u.id
            WHERE fk.id = ?
        ");
        $stmt->execute([$foto_id]);
        $foto_data = $stmt->fetch();
    } else {
        // Admin UKM: hanya hapus foto dari UKM-nya
        $stmt = $db->prepare("
            SELECT fk.foto, k.nama_kegiatan, u.nama_ukm
            FROM foto_kegiatan fk
            JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
            JOIN ukm u ON k.ukm_id = u.id
            WHERE fk.id = ? AND u.id = ?
        ");
        $stmt->execute([$foto_id, $ukm_id]);
        $foto_data = $stmt->fetch();
    }

    if ($foto_data) {
        $file_path = '../uploads/kegiatan/' . $foto_data['foto'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $db->prepare("DELETE FROM foto_kegiatan WHERE id = ?")->execute([$foto_id]);
        $kegiatan_info = '"' . htmlspecialchars($foto_data['nama_kegiatan']) . '" (' . htmlspecialchars($foto_data['nama_ukm']) . ')';
        showAlert("Foto dari kegiatan {$kegiatan_info} berhasil dihapus.", 'success');
    } else {
        showAlert('Foto tidak ditemukan atau Anda tidak memiliki akses.', 'danger');
    }
    redirect('kelola_foto.php');
}

// ----------------------------------------------------
// AMBIL DATA FOTO
// ----------------------------------------------------
if ($is_superadmin) {
    // Superadmin: lihat semua foto
    $stmt = $db->prepare("
        SELECT 
            fk.id AS foto_id,
            fk.foto,
            fk.keterangan,
            fk.created_at,
            k.id AS kegiatan_id,
            k.nama_kegiatan,
            u.nama_ukm,
            u.id AS ukm_id
        FROM foto_kegiatan fk
        JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
        JOIN ukm u ON k.ukm_id = u.id
        ORDER BY fk.created_at DESC
    ");
    $stmt->execute();
} else {
    // Admin UKM: hanya lihat foto dari UKM-nya
    $stmt = $db->prepare("
        SELECT 
            fk.id AS foto_id,
            fk.foto,
            fk.keterangan,
            fk.created_at,
            k.id AS kegiatan_id,
            k.nama_kegiatan,
            u.nama_ukm,
            u.id AS ukm_id
        FROM foto_kegiatan fk
        JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
        JOIN ukm u ON k.ukm_id = u.id
        WHERE u.id = ?
        ORDER BY fk.created_at DESC
    ");
    $stmt->execute([$ukm_id]);
}

$foto_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Galeri Foto - Admin UKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            margin-bottom: 2px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .foto-preview {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .foto-card {
            transition: transform 0.2s;
        }
        .foto-card:hover {
            transform: translateY(-3px);
        }
        .badge-ukm {
            font-size: 0.75em;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar terpusat -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>

            <!-- Konten Utama -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-images me-2"></i>Galeri Foto Kegiatan</h2>
                        <?php if (!$is_superadmin): ?>
                            <a href="kelola_kegiatan.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Kegiatan
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php displayAlert(); ?>

                    <?php if (empty($foto_list)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-images fa-3x text-muted mb-3"></i>
                            <h5>Tidak ada foto kegiatan</h5>
                            <?php if ($is_superadmin): ?>
                                <p class="text-muted">Belum ada UKM yang mengupload foto kegiatan.</p>
                            <?php else: ?>
                                <p class="text-muted">Upload foto melalui halaman <strong>Kelola Kegiatan</strong>.</p>
                                <a href="kelola_kegiatan.php" class="btn btn-primary">Kelola Kegiatan</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($foto_list as $foto): ?>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                    <div class="card foto-card h-100">
                                        <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>" 
                                             class="foto-preview" 
                                             alt="Foto">
                                        <div class="card-body p-2">
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars(substr($foto['nama_kegiatan'], 0, 25)) ?>...</h6>
                                            <?php if ($is_superadmin): ?>
                                                <span class="badge badge-ukm bg-primary"><?= htmlspecialchars($foto['nama_ukm']) ?></span>
                                            <?php endif; ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-calendar"></i> <?= formatTanggal($foto['created_at']) ?>
                                            </small>
                                            <?php if (!empty($foto['keterangan'])): ?>
                                                <p class="text-muted small mb-2"><?= htmlspecialchars($foto['keterangan']) ?></p>
                                            <?php endif; ?>
                                            <div class="d-grid">
                                                <a href="?hapus_foto=<?= $foto['foto_id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Yakin hapus foto ini?')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>