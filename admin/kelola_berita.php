<?php
session_start(); // ← WAJIB DI AWAL
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Hanya admin UKM (bukan superadmin) yang boleh akses
if ($_SESSION['user_role'] === 'superadmin') {
    showAlert('Akses ditolak: Superadmin tidak dapat mengelola berita UKM.', 'danger');
    redirect('dashboard.php');
}

if (!isset($_SESSION['ukm_id'])) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('../auth/logout.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];

// --- HAPUS BERITA ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $berita_id = (int)$_GET['id'];

    // Ambil nama file gambar sebelum dihapus
    $stmt_check = $db->prepare("SELECT gambar FROM berita WHERE id = ? AND ukm_id = ?");
    $stmt_check->execute([$berita_id, $ukm_id]);
    $gambar = $stmt_check->fetchColumn();

    // Hapus dari database
    $stmt_del = $db->prepare("DELETE FROM berita WHERE id = ? AND ukm_id = ?");
    if ($stmt_del->execute([$berita_id, $ukm_id])) {
        // Hapus file gambar lama jika ada
        if ($gambar && file_exists('../uploads/' . $gambar)) {
            unlink('../uploads/' . $gambar);
        }
        showAlert('Berita berhasil dihapus.', 'success');
    } else {
        showAlert('Gagal menghapus berita.', 'danger');
    }
    redirect('kelola_berita.php');
}

// --- AMBIL SEMUA BERITA DARI UKM INI ---
$stmt = $db->prepare("
    SELECT * FROM berita 
    WHERE ukm_id = ? 
    ORDER BY tanggal_publikasi DESC
");
$stmt->execute([$ukm_id]);
$berita_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Berita - Admin UKM</title>
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
            border-radius: 0;
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
        .news-thumb {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar dari file terpisah -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>

            <!-- Konten Utama -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-newspaper me-2"></i>Kelola Berita</h2>
                        <a href="tambah_berita.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Berita Baru
                        </a>
                    </div>

                    <?php displayAlert(); ?>

                    <?php if (empty($berita_list)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h5>Belum ada berita</h5>
                            <p class="text-muted">Buat berita pertama Anda untuk UKM ini.</p>
                            <a href="tambah_berita.php" class="btn btn-primary">Buat Berita Sekarang</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Judul</th>
                                        <th>Penulis</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($berita_list as $berita): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($berita['gambar'])): ?>
                                                    <img src="../uploads/<?= htmlspecialchars($berita['gambar']) ?>" class="news-thumb">
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars(substr($berita['judul'], 0, 50)) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($berita['penulis']) ?></td>
                                            <td><?= formatTanggal($berita['tanggal_publikasi']) ?></td>
                                            <td>
                                                <?php if ($berita['status'] === 'published'): ?>
                                                    <span class="status-badge bg-success text-white">Publikasi</span>
                                                <?php else: ?>
                                                    <span class="status-badge bg-warning text-dark">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="edit_berita.php?id=<?= $berita['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="kelola_berita.php?action=delete&id=<?= $berita['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   title="Hapus"
                                                   onclick="return confirm(<?= json_encode('Yakin hapus berita "' . addslashes($berita['judul']) . '"?') ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>