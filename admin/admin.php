<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek apakah user adalah admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Error: Tidak dapat terhubung ke database");
}

// Ambil data statistik
try {
    // Total UKM
    $query = "SELECT COUNT(*) as total FROM ukm WHERE status = 'aktif'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_ukm = $stmt->fetch()['total'];
    
    // Total Mahasiswa
    $query = "SELECT COUNT(*) as total FROM mahasiswa";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_mahasiswa = $stmt->fetch()['total'];
    
    // Total Pendaftaran
    $query = "SELECT COUNT(*) as total FROM pendaftaran";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_pendaftaran = $stmt->fetch()['total'];
    
    // Pendaftaran Pending
    $query = "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pendaftaran_pending = $stmt->fetch()['total'];
    
    // Pendaftaran terbaru (10 terakhir)
    $query = "SELECT p.*, m.nama as nama_mahasiswa, m.nim, u.nama_ukm  FROM pendaftaran p 
            JOIN mahasiswa m ON p.mahasiswa_id = m.id 
            JOIN ukm u ON p.ukm_id = u.id 
            ORDER BY p.created_at DESC 
            LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pendaftaran_terbaru = $stmt->fetchAll();
    
    // UKM dengan pendaftar terbanyak
    $query = "SELECT u.nama_ukm, COUNT(p.id) as jumlah_pendaftar 
            FROM ukm u 
            LEFT JOIN pendaftaran p ON u.id = p.ukm_id 
            WHERE u.status = 'aktif'
            GROUP BY u.id, u.nama_ukm 
            ORDER BY jumlah_pendaftar DESC 
            LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ukm_populer = $stmt->fetchAll();
    
    // Statistik per bulan (6 bulan terakhir)
    $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as bulan, 
                    COUNT(*) as jumlah
            FROM pendaftaran 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY bulan DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $statistik_bulanan = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error getting dashboard data: " . $e->getMessage());
    $total_ukm = $total_mahasiswa = $total_pendaftaran = $pendaftaran_pending = 0;
    $pendaftaran_terbaru = $ukm_populer = $statistik_bulanan = [];
}

// Handle quick actions
if ($_POST) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'approve':
                    $pendaftaran_id = (int)$_POST['pendaftaran_id'];
                    $query = "UPDATE pendaftaran SET status = 'diterima', catatan_admin = 'Disetujui oleh admin' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$pendaftaran_id]);
                    showAlert('Pendaftaran berhasil disetujui', 'success');
                    break;
                    
                case 'reject':
                    $pendaftaran_id = (int)$_POST['pendaftaran_id'];
                    $catatan = sanitize($_POST['catatan'] ?? 'Ditolak oleh admin');
                    $query = "UPDATE pendaftaran SET status = 'ditolak', catatan_admin = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$catatan, $pendaftaran_id]);
                    showAlert('Pendaftaran berhasil ditolak', 'warning');
                    break;
            }
            
            // Refresh data setelah action
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (PDOException $e) {
            showAlert('Error: ' . $e->getMessage(), 'danger');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s;
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
        .bg-primary-gradient { background: linear-gradient(45deg, #3498db, #2980b9); }
        .bg-success-gradient { background: linear-gradient(45deg, #2ecc71, #27ae60); }
        .bg-warning-gradient { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .bg-danger-gradient { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.02);
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <h5 class="text-white mb-4">
                        <i class="fas fa-university"></i> Admin UKM
                    </h5>
                    <nav class="nav flex-column">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="manage_ukm.php" class="nav-link">
                            <i class="fas fa-users me-2"></i> Kelola UKM
                        </a>
                        <a href="manage_pendaftaran.php" class="nav-link">
                            <i class="fas fa-file-alt me-2"></i> Kelola Pendaftaran
                        </a>
                        <a href="manage_mahasiswa.php" class="nav-link">
                            <i class="fas fa-user-graduate me-2"></i> Data Mahasiswa
                        </a>
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i> Laporan
                        </a>
                        <a href="settings.php" class="nav-link">
                            <i class="fas fa-cogs me-2"></i> Pengaturan
                        </a>
                        <hr class="text-white">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-home me-2"></i> Beranda
                        </a>
                        <a href="../auth/logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col">
                            <h2 class="fw-bold">Dashboard Admin</h2>
                            <p class="text-muted">Selamat datang di sistem manajemen UKM Politeknik Negeri Lampung</p>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                                    <i class="fas fa-plus"></i> Quick Action
                                </button>
                                <button class="btn btn-outline-secondary" onclick="location.reload()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alert -->
                    <?php displayAlert(); ?>

                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-5">
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-primary-gradient">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="fw-bold mb-0"><?= $total_ukm ?></h3>
                                            <p class="text-muted mb-0">Total UKM</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-success-gradient">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="fw-bold mb-0"><?= $total_mahasiswa ?></h3>
                                            <p class="text-muted mb-0">Total Mahasiswa</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-warning-gradient">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="fw-bold mb-0"><?= $total_pendaftaran ?></h3>
                                            <p class="text-muted mb-0">Total Pendaftaran</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-danger-gradient">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h3 class="fw-bold mb-0"><?= $pendaftaran_pending ?></h3>
                                            <p class="text-muted mb-0">Menunggu Persetujuan</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Pendaftaran Terbaru -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-clock text-primary"></i> Pendaftaran Terbaru
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Mahasiswa</th>
                                                    <th>UKM</th>
                                                    <th>Tanggal</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($pendaftaran_terbaru)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4">
                                                            <i class="fas fa-inbox text-muted"></i><br>
                                                            Belum ada pendaftaran
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($pendaftaran_terbaru as $pendaftaran): ?>
                                                        <tr>
                                                            <td>
                                                                <div>
                                                                    <strong><?= htmlspecialchars($pendaftaran['nama_mahasiswa']) ?></strong><br>
                                                                    <small class="text-muted"><?= htmlspecialchars($pendaftaran['nim']) ?></small>
                                                                </div>
                                                            </td>
                                                            <td><?= htmlspecialchars($pendaftaran['nama_ukm']) ?></td>
                                                            <td><?= formatTanggal($pendaftaran['created_at']) ?></td>
                                                            <td>
                                                                <?php
                                                                $status_class = '';
                                                                switch ($pendaftaran['status']) {
                                                                    case 'pending':
                                                                        $status_class = 'warning';
                                                                        break;
                                                                    case 'diterima':
                                                                        $status_class = 'success';
                                                                        break;
                                                                    case 'ditolak':
                                                                        $status_class = 'danger';
                                                                        break;
                                                                }
                                                                ?>
                                                                <span class="badge bg-<?= $status_class ?> badge-status">
                                                                    <?= ucfirst($pendaftaran['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($pendaftaran['status'] == 'pending'): ?>
                                                                    <div class="btn-group" role="group">
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="approve">
                                                                            <input type="hidden" name="pendaftaran_id" value="<?= $pendaftaran['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-success" title="Setujui">
                                                                                <i class="fas fa-check"></i>
                                                                            </button>
                                                                        </form>
                                                                        <button type="button" class="btn btn-sm btn-danger" title="Tolak" 
                                                                                onclick="rejectApplication(<?= $pendaftaran['id'] ?>)">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="manage_pendaftaran.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-arrow-right"></i> Lihat Semua Pendaftaran
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- UKM Populer -->
                        <div class="col-lg-4">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-star text-warning"></i> UKM Populer
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($ukm_populer)): ?>
                                        <p class="text-muted text-center">Belum ada data</p>
                                    <?php else: ?>
                                        <?php foreach ($ukm_populer as $index => $ukm): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-shrink-0">
                                                    <div class="badge bg-primary rounded-pill" style="width: 30px; height: 30px; line-height: 20px;">
                                                        <?= $index + 1 ?>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0"><?= htmlspecialchars($ukm['nama_ukm']) ?></h6>
                                                    <small class="text-muted"><?= $ukm['jumlah_pendaftar'] ?> pendaftar</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Section -->
                    <div class="row g-4 mt-4">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-4">
                                    <i class="fas fa-chart-line text-primary"></i> Statistik Pendaftaran (6 Bulan Terakhir)
                                </h5>
                                <canvas id="registrationChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Modal -->
    <div class="modal fade" id="quickActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <a href="manage_ukm.php?action=add" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus"></i> Tambah UKM Baru
                            </a>
                        </div>
                        <div class="col-12">
                            <a href="manage_pendaftaran.php?filter=pending" class="btn btn-outline-warning w-100">
                                <i class="fas fa-clock"></i> Lihat Pendaftaran Pending
                            </a>
                        </div>
                        <div class="col-12">
                            <a href="reports.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-download"></i> Export Laporan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="rejectForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Tolak Pendaftaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="pendaftaran_id" id="rejectPendaftaranId">
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Alasan Penolakan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak Pendaftaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart Configuration
        const chartData = {
            labels: [
                <?php foreach (array_reverse($statistik_bulanan) as $stat): ?>
                    '<?= date('M Y', strtotime($stat['bulan'] . '-01')) ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Jumlah Pendaftaran',
                data: [
                    <?php foreach (array_reverse($statistik_bulanan) as $stat): ?>
                        <?= $stat['jumlah'] ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        const ctx = document.getElementById('registrationChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Reject function
        function rejectApplication(pendaftaranId) {
            document.getElementById('rejectPendaftaranId').value = pendaftaranId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>