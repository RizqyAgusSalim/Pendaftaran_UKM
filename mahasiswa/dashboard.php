<?php
// Dashboard Mahasiswa - versi diperbaiki
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai session (penting)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user login dan berperan sebagai mahasiswa
if (!function_exists('isLoggedIn') || !function_exists('isMahasiswa')) {
    // Jika fungsi tidak ditemukan: hentikan agar tidak lanjut
    error_log('Fungsi isLoggedIn/isMahasiswa tidak ditemukan di functions.php');
    header('Location: ../auth/login.php');
    exit;
}

if (!isLoggedIn() || !isMahasiswa()) {
    header('Location: ../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    // Jika koneksi gagal, hentikan dan tampilkan pesan (untuk development)
    die("Error: Tidak dapat terhubung ke database");
}

$mahasiswa_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// Inisialisasi
$mahasiswa = null;
$pendaftaran_saya = [];
$ukm_tersedia = [];
$notifikasi = [];
$total_pendaftaran = 0;
$pending = 0;
$diterima = 0;
$ditolak = 0;

try {
    // Ambil data mahasiswa bila ada
    if ($mahasiswa_id > 0) {
        $sql = "SELECT * FROM mahasiswa WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$mahasiswa_id]);
        $mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Jika tidak ditemukan mahasiswa di DB, buat dummy dari session (development friendly)
    if (!$mahasiswa || empty($mahasiswa)) {
        $mahasiswa = [
            'id' => $mahasiswa_id,
            'nama' => isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Test Mahasiswa',
            'nim' => isset($_SESSION['nim']) ? $_SESSION['nim'] : '2024000000',
            'jurusan' => 'Teknik Informatika',
            'angkatan' => '2024',
            'email' => 'test@student.polinela.ac.id',
            'no_telepon' => '08123456789',
            'alamat' => 'Bandar Lampung'
        ];
    }

    // Safety defaults
    $mahasiswa['nama'] = $mahasiswa['nama'] ?? 'Nama Tidak Diketahui';
    $mahasiswa['nim'] = $mahasiswa['nim'] ?? 'NIM Tidak Diketahui';
    $mahasiswa['jurusan'] = $mahasiswa['jurusan'] ?? 'Jurusan Tidak Diketahui';
    $mahasiswa['angkatan'] = $mahasiswa['angkatan'] ?? date('Y');

    // Ambil pendaftaran mahasiswa
    $sql = "SELECT p.*, u.nama_ukm, u.deskripsi, k.nama_kategori
            FROM pendaftaran p
            JOIN ukm u ON p.ukm_id = u.id
            LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
            WHERE p.mahasiswa_id = ?
            ORDER BY COALESCE(p.created_at, p.updated_at, '') DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$mahasiswa_id]);
    $pendaftaran_saya = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Statistik
    $total_pendaftaran = count($pendaftaran_saya);
    $pending = $diterima = $ditolak = 0;
    foreach ($pendaftaran_saya as $p) {
        $status = strtolower(trim((string)($p['status'] ?? '')));
        switch ($status) {
            case 'pending': $pending++; break;
            case 'diterima': $diterima++; break;
            case 'ditolak': $ditolak++; break;
        }
    }

    // Ambil UKM tersedia: hitung total pendaftar per UKM menggunakan COUNT DISTINCT
    // dan cek apakah user sudah mendaftar di setiap UKM
    $sql = "SELECT u.id, u.nama_ukm, u.deskripsi, k.nama_kategori,
                   COUNT(DISTINCT p.id) AS total_pendaftar,
                   CASE WHEN EXISTS(
                       SELECT 1 FROM pendaftaran pu WHERE pu.ukm_id = u.id AND pu.mahasiswa_id = ?
                   ) THEN 1 ELSE 0 END AS sudah_daftar
            FROM ukm u
            LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
            LEFT JOIN pendaftaran p ON u.id = p.ukm_id
            WHERE u.status = 'aktif'
            GROUP BY u.id, u.nama_ukm, u.deskripsi, k.nama_kategori
            ORDER BY u.nama_ukm
            LIMIT 6";
    $stmt = $db->prepare($sql);
    $stmt->execute([$mahasiswa_id]);
    $ukm_tersedia = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Normalisasi data UKM
    foreach ($ukm_tersedia as &$ukm) {
        $ukm['nama_ukm'] = $ukm['nama_ukm'] ?? 'UKM Tidak Diketahui';
        $ukm['nama_kategori'] = $ukm['nama_kategori'] ?? 'Kategori Tidak Diketahui';
        $ukm['deskripsi'] = $ukm['deskripsi'] ?? 'Tidak ada deskripsi';
        $ukm['total_pendaftar'] = isset($ukm['total_pendaftar']) ? (int)$ukm['total_pendaftar'] : 0;
        $ukm['sudah_daftar'] = isset($ukm['sudah_daftar']) ? (int)$ukm['sudah_daftar'] : 0;
    }
    unset($ukm);

    // Ambil notifikasi (pemberitahuan jika status sudah berubah, bukan pending)
    $sql = "SELECT p.*, u.nama_ukm
            FROM pendaftaran p
            JOIN ukm u ON p.ukm_id = u.id
            WHERE p.mahasiswa_id = ? AND COALESCE(p.status, '') != 'pending'
            ORDER BY COALESCE(p.updated_at, p.created_at) DESC
            LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([$mahasiswa_id]);
    $notifikasi = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    error_log("Error getting mahasiswa dashboard data: " . $e->getMessage());
    // Jika error, siapkan data default minimal agar halaman tetap render
    if (empty($mahasiswa)) {
        $mahasiswa = [
            'nama' => 'Test Mahasiswa',
            'nim' => '2024000000',
            'jurusan' => 'Teknik Informatika',
            'angkatan' => '2024'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Mahasiswa - UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .dashboard-container { padding: 2rem 0; }
        .welcome-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; text-align: center; }
        .stats-card { background: white; border-radius: 15px; padding: 1.5rem; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: none; margin-bottom: 1rem; transition: transform 0.3s ease; }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-number { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 0.5rem; }
        .content-card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        @media (min-width: 992px) { .col-lg-8, .col-lg-4 { width: 100%; } }
        .btn-primary-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 25px; padding: 0.8rem 2rem; font-weight: 600; color: white; text-decoration: none; transition: all 0.3s ease; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); color: white; }
        .empty-state { padding: 3rem; text-align: center; color: #6c757d; }
        .ukm-item-card { background: #f8f9fc; border-radius: 12px; padding: 1.2rem; margin-bottom: 1rem; border: 1px solid #e3e6f0; transition: all 0.3s ease; }
        .ukm-item-card:hover { background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .ukm-icon { width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; margin-right: 1rem; flex-shrink: 0; }
        .ukm-title { font-size: 1rem; font-weight: 600; color: #2d3748; margin-bottom: 0.5rem; line-height: 1.3; }
        .ukm-meta { display: flex; gap: 0.8rem; margin-bottom: 0.8rem; flex-wrap: wrap; }
        .category-badge { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .member-count { color: #718096; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; }
        .ukm-description { color: #4a5568; font-size: 0.85rem; line-height: 1.4; margin-bottom: 1rem; }
        .ukm-action { display: flex; align-items: center; }
        .btn-daftar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.5rem 1rem; border-radius: 20px; text-decoration: none; font-size: 0.8rem; font-weight: 600; transition: all 0.3s ease; border: none; display: inline-flex; align-items: center; }
        .btn-daftar:hover { color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; }
        .status-joined { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; }
        .btn-view-all { background: rgba(102, 126, 234, 0.1); color: #667eea; padding: 0.8rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; display: inline-flex; align-items: center; border: 2px solid rgba(102, 126, 234, 0.2); }
        .btn-view-all:hover { background: #667eea; color: white; border-color: #667eea; transform: translateY(-1px); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-graduation-cap me-2 text-primary"></i>Dashboard Mahasiswa
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Beranda</a>
                <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="container">
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <div class="avatar">
                            <i class="fas fa-user-circle fa-4x text-primary"></i>
                        </div>
                    </div>
                    <div class="col-md-10 text-start">
                        <h2 class="fw-bold">Selamat Datang, <?= htmlspecialchars($mahasiswa['nama'], ENT_QUOTES, 'UTF-8') ?>!</h2>
                        <p class="mb-1">
                            <i class="fas fa-id-card me-2 text-primary"></i>
                            <strong>NIM:</strong> <?= htmlspecialchars($mahasiswa['nim'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-graduation-cap me-2 text-primary"></i>
                            <strong>Jurusan:</strong> <?= htmlspecialchars($mahasiswa['jurusan'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            <strong>Angkatan:</strong> <?= htmlspecialchars($mahasiswa['angkatan'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number"><?= (int)$total_pendaftaran ?></div>
                        <div class="text-muted">Total Pendaftaran</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-warning"><?= (int)$pending ?></div>
                        <div class="text-muted">Menunggu</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-success"><?= (int)$diterima ?></div>
                        <div class="text-muted">Diterima</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?= (int)$ditolak ?></div>
                        <div class="text-muted">Ditolak</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header bg-white border-0 p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">
                                    <i class="fas fa-list-alt text-primary me-2"></i>Pendaftaran Saya
                                </h5>
                                <a href="daftar_ukm.php" class="btn-primary-custom btn-sm">
                                    <i class="fas fa-plus me-1"></i>Daftar UKM
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendaftaran_saya)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <h6>Anda belum mendaftar ke UKM manapun</h6>
                                    <a href="daftar_ukm.php" class="btn-primary-custom mt-2">
                                        <i class="fas fa-plus me-1"></i>Daftar UKM Sekarang
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>UKM</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendaftaran_saya as $p): ?>
                                                <?php
                                                    $nama_ukm = htmlspecialchars($p['nama_ukm'] ?? '-', ENT_QUOTES, 'UTF-8');
                                                    $nama_kategori = htmlspecialchars($p['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8');
                                                    $created_at_raw = $p['created_at'] ?? '';
                                                    $created_at = '-';
                                                    if (!empty($created_at_raw) && strtotime($created_at_raw) !== false) {
                                                        $created_at = date('d/m/Y', strtotime($created_at_raw));
                                                    }
                                                    $status = $p['status'] ?? 'pending';
                                                    $status_text = ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8'));
                                                    $badge_class = 'bg-secondary';
                                                    switch (strtolower($status)) {
                                                        case 'pending': $badge_class = 'bg-warning'; break;
                                                        case 'diterima': $badge_class = 'bg-success'; break;
                                                        case 'ditolak': $badge_class = 'bg-danger'; break;
                                                    }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= $nama_ukm ?></strong><br>
                                                        <small class="text-muted"><?= $nama_kategori ?></small>
                                                    </td>
                                                    <td><?= $created_at ?></td>
                                                    <td><span class="badge <?= $badge_class ?>"><?= $status_text ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-users text-success me-2"></i>UKM Tersedia (Rekomendasi)
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <?php if (empty($ukm_tersedia)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users text-muted fa-3x mb-3"></i>
                                    <p class="text-muted">Belum ada UKM tersedia</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($ukm_tersedia as $ukm): ?>
                                        <?php
                                            $ukm_id = (int)($ukm['id'] ?? 0);
                                            $ukm_nama = htmlspecialchars($ukm['nama_ukm'] ?? 'UKM Tidak Diketahui', ENT_QUOTES, 'UTF-8');
                                            $ukm_kategori = htmlspecialchars($ukm['nama_kategori'] ?? 'Kategori Tidak Diketahui', ENT_QUOTES, 'UTF-8');
                                            $ukm_deskripsi = htmlspecialchars($ukm['deskripsi'] ?? 'Tidak ada deskripsi', ENT_QUOTES, 'UTF-8');
                                            $ukm_total = (int)($ukm['total_pendaftar'] ?? 0);
                                            $ukm_sudah = (int)($ukm['sudah_daftar'] ?? 0);
                                        ?>
                                        <div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                                            <div class="ukm-item-card h-100 d-flex flex-column">
                                                <div class="d-flex align-items-start mb-2">
                                                    <div class="ukm-icon"><i class="fas fa-users"></i></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="ukm-title"><?= $ukm_nama ?></h6>
                                                        <div class="ukm-meta">
                                                            <span class="category-badge"><?= $ukm_kategori ?></span>
                                                            <span class="member-count"><i class="fas fa-user-friends me-1"></i><?= $ukm_total ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="ukm-description flex-grow-1">
                                                    <?= (strlen($ukm_deskripsi) > 80) ? substr($ukm_deskripsi, 0, 80) . '...' : $ukm_deskripsi ?>
                                                </p>
                                                <div class="ukm-action mt-auto">
                                                    <?php if ($ukm_sudah): ?>
                                                        <span class="status-badge status-joined"><i class="fas fa-check me-1"></i>Sudah Bergabung</span>
                                                    <?php else: ?>
                                                        <a href="detail_ukm.php?id=<?= $ukm_id ?>" class="btn-daftar w-100 justify-content-center">
                                                            <i class="fas fa-paper-plane me-1"></i>Lihat Detail
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="text-center mt-3">
                                    <a href="daftar_ukm.php" class="btn-view-all">
                                        <i class="fas fa-arrow-right me-1"></i>Lihat Semua UKM
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> <!-- col-12 -->
            </div> <!-- row g-4 -->
        </div> <!-- container -->
    </div> <!-- dashboard-container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
