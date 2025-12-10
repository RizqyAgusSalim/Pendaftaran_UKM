<?php
// admin/dashboard.php — KHUSUS ADMIN UKM (DIPERBAIKI UNTUK SUPERADMIN)
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan sudah login dan punya akses admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// --- ✅ PERBAIKAN UTAMA: Dukungan untuk Superadmin ---
$nama_ukm = "UKM Anda";
$ukm_id = null;

if ($_SESSION['user_role'] === 'superadmin') {
    // Superadmin: tidak perlu ukm_id, lihat semua data
    $nama_ukm = "Semua UKM (Mode Admin - Super Admin)";
} else {
    // Admin biasa: harus punya ukm_id
    if (!isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
        showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
        redirect('../auth/logout.php');
    }
    $ukm_id = (int)$_SESSION['ukm_id'];
    
    // Ambil nama UKM untuk admin biasa
    $stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = :ukm_id");
    $stmt_ukm->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt_ukm->execute();
    $nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";
}

// ----------------------------------------------------
// STATISTIK
// ----------------------------------------------------
$stats = [];

// Total UKM (semua)
$stats['total_ukm'] = $db->query("SELECT COUNT(*) FROM ukm")->fetchColumn();

// Total mahasiswa (semua)
$stats['total_mahasiswa'] = $db->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();

// Total pendaftaran
if ($ukm_id !== null) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE ukm_id = :ukm_id");
    $stmt->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_pendaftaran'] = $stmt->fetchColumn();

    $stmt2 = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE ukm_id = :ukm_id AND status = 'pending'");
    $stmt2->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt2->execute();
    $stats['pending_pendaftaran'] = $stmt2->fetchColumn();
} else {
    // Superadmin: lihat semua
    $stats['total_pendaftaran'] = $db->query("SELECT COUNT(*) FROM pendaftaran")->fetchColumn();
    $stats['pending_pendaftaran'] = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'pending'")->fetchColumn();
}

// ----------------------------------------------------
// PENDAFTARAN TERBARU
// ----------------------------------------------------
if ($ukm_id !== null) {
    $query = "
        SELECT 
            p.id, p.status, p.created_at, m.nama, m.nim, u.nama_ukm 
        FROM pendaftaran p 
        JOIN mahasiswa m ON p.mahasiswa_id = m.id 
        JOIN ukm u ON p.ukm_id = u.id 
        WHERE p.ukm_id = :ukm_id
        ORDER BY p.created_at DESC LIMIT 10
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
} else {
    // Superadmin: lihat semua UKM
    $query = "
        SELECT 
            p.id, p.status, p.created_at, m.nama, m.nim, u.nama_ukm 
        FROM pendaftaran p 
        JOIN mahasiswa m ON p.mahasiswa_id = m.id 
        JOIN ukm u ON p.ukm_id = u.id 
        ORDER BY p.created_at DESC LIMIT 10
    ";
    $stmt = $db->prepare($query);
}
$stmt->execute();
$recent_pendaftaran = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi Helper untuk Badge Status
function getStatusBadge(string $status): string
{
    $status = strtolower($status);
    $class = match($status){
        'pending' => 'badge-pending text-dark',
        'diterima' => 'badge-diterima text-white',
        'ditolak' => 'badge-ditolak text-white',
        default => 'bg-secondary text-white'
    };
    return "<span class=\"badge {$class}\">" . ucfirst($status) . "</span>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="timezone" content="Asia/Jakarta">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?= htmlspecialchars($nama_ukm) ?></title>
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
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
        .badge-pending { background: #ffc107 !important; color: #212529 !important; }
        .badge-diterima { background: #28a745 !important; color: #fff !important; }
        .badge-ditolak { background: #dc3545 !important; color: #fff !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="p-3 text-center border-bottom border-secondary">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-university"></i> Admin UKM
                        </h5>
                        <small class="text-white-50">Politeknik Negeri Lampung</small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="kelola_ukm.php">
                            <i class="fas fa-users me-2"></i> Kelola UKM
                        </a>
                        <a class="nav-link" href="kelola_kategori.php">
                            <i class="fas fa-tags me-2"></i> Kategori UKM
                        </a>
                        <a class="nav-link" href="kelola_mahasiswa.php">
                            <i class="fas fa-user-graduate me-2"></i> Data Mahasiswa
                        </a>
                        <a class="nav-link" href="kelola_pendaftaran.php">
                            <i class="fas fa-clipboard-list me-2"></i> Pendaftaran
                        </a>
                        <a class="nav-link" href="laporan.php">
                            <i class="fas fa-chart-bar me-2"></i> Laporan
                        </a>
                        <div class="dropdown-divider bg-secondary"></div>
                        <a class="nav-link" href="../index.php" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i> Lihat Website
                        </a>
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Dashboard Admin</h2>
                            <p class="text-muted mb-0">Selamat datang, <?= ($_SESSION['user_role'] === 'superadmin') ? '<strong>Super Admin</strong>' : 'Admin'; ?> <strong><?= htmlspecialchars($nama_ukm) ?></strong>!</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> <?= formatTanggal(date('Y-m-d')) ?>
                            </small>
                        </div>
                    </div>

                    <?php displayAlert(); ?>

                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card border-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: #3498db;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="mb-0"><?= $stats['total_ukm'] ?></h3>
                                            <p class="text-muted mb-0">Total UKM</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card border-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: #28a745;">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="mb-0"><?= $stats['total_mahasiswa'] ?></h3>
                                            <p class="text-muted mb-0">Mahasiswa Terdaftar</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card border-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: #17a2b8;">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="mb-0"><?= $stats['total_pendaftaran'] ?></h3>
                                            <p class="text-muted mb-0">Total Pendaftaran</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card border-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: #ffc107;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="mb-0"><?= $stats['pending_pendaftaran'] ?></h3>
                                            <p class="text-muted mb-0">Pending Approval</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ QUICK ACTIONS + TOMBOL KEMBALI SUPERADMIN -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt text-warning"></i> Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <a href="kelola_kategori.php?action=add" class="btn btn-success w-100">
                                                <i class="fas fa-tag"></i> Tambah Kategori
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <a href="kelola_pendaftaran.php" class="btn btn-warning w-100">
                                                <i class="fas fa-check-circle"></i> Review Pendaftaran
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <a href="laporan.php" class="btn btn-info w-100">
                                                <i class="fas fa-download"></i> Export Laporan
                                            </a>
                                        </div>

                                        <!-- ✅ TOMBOL KEMBALI KE SUPER ADMIN (Hanya untuk Superadmin) -->
                                        <?php if ($_SESSION['user_role'] === 'superadmin'): ?>
                                            <div class="col-12 mt-3">
                                                <a href="../superadmin/dashboard.php" class="btn btn-outline-dark w-100">
                                                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Super Admin
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-clock text-primary"></i> Pendaftaran Terbaru
                                    </h5>
                                    <a href="kelola_pendaftaran.php" class="btn btn-sm btn-outline-primary">
                                        Lihat Semua <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Mahasiswa</th>
                                                    <th>NIM</th>
                                                    <th>UKM</th>
                                                    <th>Tanggal</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recent_pendaftaran)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4 text-muted">
                                                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                            Belum ada pendaftaran
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($recent_pendaftaran as $pendaftaran): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                                        <?= strtoupper(substr(htmlspecialchars($pendaftaran['nama']), 0, 2)) ?>
                                                                    </div>
                                                                    <div class="ms-2">
                                                                        <div class="fw-bold"><?= htmlspecialchars($pendaftaran['nama']) ?></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-light text-dark"><?= htmlspecialchars($pendaftaran['nim']) ?></span>
                                                            </td>
                                                            <td><?= htmlspecialchars($pendaftaran['nama_ukm']) ?></td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?= formatTanggal($pendaftaran['created_at']) ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?= getStatusBadge($pendaftaran['status']) ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="kelola_pendaftaran.php?action=view&id=<?= $pendaftaran['id'] ?>" class="btn btn-outline-primary" title="Lihat Detail">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <?php if ($pendaftaran['status'] == 'pending'): ?>
                                                                        <a href="kelola_pendaftaran.php?action=approve&id=<?= $pendaftaran['id'] ?>" class="btn btn-outline-success" title="Terima"
                                                                           onclick="return confirm('Terima pendaftaran dari <?= htmlspecialchars(addslashes($pendaftaran['nama'])) ?>?')">
                                                                            <i class="fas fa-check"></i>
                                                                        </a>
                                                                        <a href="kelola_pendaftaran.php?action=reject&id=<?= $pendaftaran['id'] ?>" class="btn btn-outline-danger" title="Tolak"
                                                                            onclick="return confirm('Tolak pendaftaran dari <?= htmlspecialchars(addslashes($pendaftaran['nama'])) ?>?')">
                                                                            <i class="fas fa-times"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>