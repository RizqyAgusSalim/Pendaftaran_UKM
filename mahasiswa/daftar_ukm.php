<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// 🟢 PERBAIKAN 1: Handle AJAX di awal — sebelum HTML apapun dikirim
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_divisi' && isset($_GET['ukm_id']) && is_numeric($_GET['ukm_id'])) {
    header('Content-Type: application/json');

    $ukm_id = (int)$_GET['ukm_id'];
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode([]);
        exit;
    }

    // ❗ NAMA TABEL: divisi (sesuai screenshot Anda)
    $stmt = $db->prepare("SELECT id, nama_divisi FROM divisi WHERE ukm_id = ? ORDER BY nama_divisi ASC");
    $stmt->execute([$ukm_id]);
    $divisi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($divisi);
    exit; // ⚠️ Hentikan eksekusi agar tidak kirim HTML
}

// Cek apakah user adalah mahasiswa
if (!isLoggedIn() || !isMahasiswa()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Error: Tidak dapat terhubung ke database");
}

$mahasiswa_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_POST && isset($_POST['daftar_ukm'])) {
    $ukm_id = (int)$_POST['ukm_id'];
    $divisi_id = !empty($_POST['divisi_id']) ? (int)$_POST['divisi_id'] : null;
    $alasan_bergabung = sanitize($_POST['alasan_bergabung']);
    $pengalaman_organisasi = sanitize($_POST['pengalaman_organisasi']);
    
    if (empty($alasan_bergabung)) {
        $error = 'Alasan bergabung harus diisi';
    } else {
        try {
            // Cek apakah sudah pernah mendaftar ke UKM ini
            $query = "SELECT COUNT(*) FROM pendaftaran WHERE mahasiswa_id = ? AND ukm_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$mahasiswa_id, $ukm_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Anda sudah pernah mendaftar ke UKM ini';
            } else {
                // Jika memilih divisi, validasi bahwa divisi benar-benar milik UKM ini
                if ($divisi_id !== null) {
                    $stmt_check = $db->prepare("SELECT id FROM divisi WHERE id = ? AND ukm_id = ?");
                    $stmt_check->execute([$divisi_id, $ukm_id]);
                    if (!$stmt_check->fetch()) {
                        $error = 'Divisi yang dipilih tidak valid';
                    }
                }

                if (!$error) {
                    // Insert pendaftaran baru + divisi_id (bisa NULL)
                    $query = "INSERT INTO pendaftaran (mahasiswa_id, ukm_id, divisi_id, alasan_bergabung, pengalaman_organisasi, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$mahasiswa_id, $ukm_id, $divisi_id, $alasan_bergabung, $pengalaman_organisasi])) {
                        $success = 'Pendaftaran berhasil dikirim! Admin akan meninjau pendaftaran Anda.';

                        // 🔔 TAMBAHAN: KIRIM NOTIFIKASI KE ADMIN UKM
                        try {
                            // Ambil nama UKM dan admin_id
                            $stmt_ukm = $db->prepare("SELECT admin_id, nama_ukm FROM ukm WHERE id = ?");
                            $stmt_ukm->execute([$ukm_id]);
                            $ukm_data = $stmt_ukm->fetch(PDO::FETCH_ASSOC);

                            if ($ukm_data && !empty($ukm_data['admin_id'])) {
                                $admin_id = (int)$ukm_data['admin_id'];
                                $nama_ukm = htmlspecialchars($ukm_data['nama_ukm'], ENT_QUOTES, 'UTF-8');

                                // Simpan notifikasi ke tabel `notifikasi`
                                $stmt_notif = $db->prepare("
                                    INSERT INTO notifikasi (user_id, pesan, url, created_at)
                                    VALUES (?, ?, ?, NOW())
                                ");
                                $pesan = "Mahasiswa baru mendaftar ke UKM Anda: " . $nama_ukm . ". Silakan periksa pendaftaran.";
                                $url = "kelola_pendaftaran.php"; // Sesuaikan jika nama file berbeda
                                $stmt_notif->execute([$admin_id, $pesan, $url]);
                            }
                        } catch (PDOException $e) {
                            // Jangan hentikan alur, cukup log error
                            error_log("Notifikasi gagal dikirim: " . $e->getMessage());
                        }
                    } else {
                        $error = 'Gagal mengirim pendaftaran. Silakan coba lagi.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            error_log("Error pendaftaran UKM: " . $e->getMessage());
        }
    }
}

// 🟢 PERBAIKAN 2: Deteksi ukm_id untuk auto-open modal
$auto_open_ukm_id = null;
if (isset($_GET['ukm_id']) && is_numeric($_GET['ukm_id'])) {
    $auto_open_ukm_id = (int)$_GET['ukm_id'];
}

// Get UKM jika ada parameter 'id' (untuk tampilan detail — opsional)
$selected_ukm = null;
if (isset($_GET['id'])) {
    $ukm_id = (int)$_GET['id'];
    try {
        $query = "SELECT u.*, k.nama_kategori FROM ukm u 
                  LEFT JOIN kategori_ukm k ON u.kategori_id = k.id 
                  WHERE u.id = ? AND u.status = 'aktif'";
        $stmt = $db->prepare($query);
        $stmt->execute([$ukm_id]);
        $selected_ukm = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting selected UKM: " . $e->getMessage());
    }
}

// Get semua UKM yang tersedia (SELALU tampilkan semua)
try {
    $query = "SELECT u.*, k.nama_kategori,
                     COUNT(p.id) as total_pendaftar,
                     CASE WHEN p_user.id IS NOT NULL THEN 1 ELSE 0 END as sudah_daftar
              FROM ukm u 
              LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
              LEFT JOIN pendaftaran p ON u.id = p.ukm_id
              LEFT JOIN pendaftaran p_user ON u.id = p_user.ukm_id AND p_user.mahasiswa_id = ?
              WHERE u.status = 'aktif'
              GROUP BY u.id, u.nama_ukm, k.nama_kategori, p_user.id
              ORDER BY u.nama_ukm";
    $stmt = $db->prepare($query);
    $stmt->execute([$mahasiswa_id]);
    $ukm_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get kategori untuk filter
    $query = "SELECT * FROM kategori_ukm ORDER BY nama_kategori";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $ukm_list = [];
    $kategori_list = [];
    error_log("Error getting UKM list: " . $e->getMessage());
}

// Filter berdasarkan kategori
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : null;
if ($filter_kategori) {
    $ukm_list = array_filter($ukm_list, function($ukm) use ($filter_kategori) {
        return $ukm['kategori_id'] == $filter_kategori;
    });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar UKM - Dashboard Mahasiswa</title>
    <!-- ✅ CDN tanpa spasi -->
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
        .main-container {
            padding: 1.5rem 0;
        }
        .page-header {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        /* ✅ GRID RESPONSIF: pakai CSS Grid dengan fallback */
        .ukm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
        }
        @media (max-width: 575.98px) {
            .ukm-grid {
                grid-template-columns: 1fr;
            }
            .page-header, .filter-section {
                padding: 1.25rem;
            }
            .ukm-header { padding: 1.25rem; }
            .ukm-body { padding: 1.25rem; }
            .ukm-title { font-size: 1.1rem; }
            .ukm-description { font-size: 0.9rem; }
        }
        .ukm-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        .ukm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .ukm-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .ukm-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }
        .ukm-body {
            padding: 1.5rem;
        }
        .category-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: inline-block;
        }
        .ukm-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin: 0;
        }
        .ukm-description {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .ukm-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fc;
            border-radius: 10px;
        }
        .ukm-stats {
            display: flex;
            gap: 0.75rem;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 1rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .btn-daftar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .btn-daftar:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .btn-sudah-daftar {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            width: 100%;
            cursor: not-allowed;
            font-size: 0.95rem;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .form-floating textarea {
            min-height: 100px;
        }
        .filter-btn {
            margin: 0.2rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: 2px solid #dee2e6;
            background: white;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            text-decoration: none;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: white;
            color: #667eea;
        }
        #divisi-section {
            display: none;
        }

        /* ✅ Perbaikan Navbar */
        .navbar-toggler {
            border: none;
            padding: 0.5rem 0.75rem;
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0,0,0,0.5)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        @media (max-width: 767.98px) {
            .navbar-nav {
                margin-top: 1rem;
            }
            .navbar-brand {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- ✅ NAVBAR RESPONSIF -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-arrow-left me-2 text-primary"></i>Dashboard Mahasiswa Polinela
            </a>

            <!-- Toggle Button for Mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-1"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h2 class="fw-bold mb-2">
                    <i class="fas fa-users text-primary me-2"></i>Daftar Unit Kegiatan Mahasiswa
                </h2>
                <p class="text-muted mb-0">Pilih UKM yang sesuai dengan minat dan bakat Anda</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <h6 class="fw-bold mb-2">
                    <i class="fas fa-filter text-primary me-2"></i>Filter Berdasarkan Kategori
                </h6>
                <div class="d-flex flex-wrap justify-content-center">
                    <a href="daftar_ukm.php" class="filter-btn <?= !$filter_kategori ? 'active' : '' ?>">
                        <i class="fas fa-th-large me-1"></i>Semua
                    </a>
                    <?php foreach ($kategori_list as $kategori): ?>
                        <a href="daftar_ukm.php?kategori=<?= $kategori['id'] ?>" 
                           class="filter-btn <?= $filter_kategori == $kategori['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- UKM Grid -->
            <?php if (empty($ukm_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h5>Tidak ada UKM yang ditemukan</h5>
                    <p class="text-muted">Coba ubah filter atau kembali ke halaman utama</p>
                    <a href="daftar_ukm.php" class="btn btn-primary mt-2">
                        <i class="fas fa-refresh me-1"></i>Reset Filter
                    </a>
                </div>
            <?php else: ?>
                <div class="ukm-grid">
                    <?php foreach ($ukm_list as $ukm): ?>
                        <div class="ukm-card">
                            <div class="ukm-header">
                                <div class="category-badge">
                                    <?= htmlspecialchars($ukm['nama_kategori'] ?? 'Umum') ?>
                                </div>
                                <h5 class="ukm-title"><?= htmlspecialchars($ukm['nama_ukm']) ?></h5>
                            </div>
                            <div class="ukm-body">
                                <p class="ukm-description">
                                    <?= htmlspecialchars(substr($ukm['deskripsi'] ?? 'Tidak ada deskripsi.', 0, 120)) ?>...
                                </p>
                                
                                <div class="ukm-meta">
                                    <div class="ukm-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?= $ukm['total_pendaftar'] ?></div>
                                            <div class="stat-label">Anggota</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?= $ukm['max_anggota'] ?? '∞' ?></div>
                                            <div class="stat-label">Max</div>
                                        </div>
                                    </div>
                                    <?php if ($ukm['biaya_pendaftaran'] > 0): ?>
                                        <div class="text-end">
                                            <div class="stat-number"><?= formatRupiah($ukm['biaya_pendaftaran']) ?></div>
                                            <div class="stat-label">Biaya</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-end">
                                            <span class="badge bg-success">GRATIS</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="additional-info mb-3">
                                    <?php if ($ukm['ketua_umum']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-user me-1"></i>
                                            <strong>Ketua:</strong> <?= htmlspecialchars($ukm['ketua_umum']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($ukm['email']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?= htmlspecialchars($ukm['email']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <?php if ($ukm['sudah_daftar']): ?>
                                    <button class="btn-sudah-daftar" disabled>
                                        <i class="fas fa-check me-2"></i>Sudah Terdaftar
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-daftar" 
                                            onclick="openDaftarModal(<?= $ukm['id'] ?>, '<?= htmlspecialchars($ukm['nama_ukm'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-paper-plane me-2"></i>Daftar Sekarang
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Pendaftaran -->
    <div class="modal fade" id="daftarModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-paper-plane me-2"></i>Daftar UKM
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="ukm_id" id="modal_ukm_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Anda akan mendaftar ke UKM: <strong id="modal_ukm_nama"></strong>
                        </div>

                        <!-- Bagian Divisi -->
                        <div id="divisi-section" class="mb-3">
                            <label for="divisi_id" class="form-label">Pilih Divisi (Opsional)</label>
                            <select class="form-select" id="divisi_id" name="divisi_id">
                                <option value="">-- Pilih Divisi --</option>
                            </select>
                            <div class="form-text">Pilih divisi yang sesuai dengan minat Anda.</div>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="alasan_bergabung" name="alasan_bergabung" 
                                      placeholder="Jelaskan alasan Anda ingin bergabung dengan UKM ini" required></textarea>
                            <label for="alasan_bergabung">Alasan Bergabung *</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="pengalaman_organisasi" name="pengalaman_organisasi" 
                                      placeholder="Ceritakan pengalaman organisasi Anda (jika ada)"></textarea>
                            <label for="pengalaman_organisasi">Pengalaman Organisasi (Opsional)</label>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small>
                                Pastikan data yang Anda masukkan sudah benar. Admin akan meninjau pendaftaran Anda 
                                dan memberikan konfirmasi melalui sistem.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" name="daftar_ukm" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Kirim Pendaftaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let daftarModal = null;

        document.addEventListener('DOMContentLoaded', function() {
            const modalElement = document.getElementById('daftarModal');
            if (modalElement) {
                daftarModal = new bootstrap.Modal(modalElement);
            }
        });

        function loadDivisi(ukmId) {
            const divisiSection = document.getElementById('divisi-section');
            const select = document.getElementById('divisi_id');
            select.innerHTML = '<option value="">-- Memuat... --</option>';
            divisiSection.style.display = 'block';

            fetch(`daftar_ukm.php?ajax=get_divisi&ukm_id=${ukmId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    select.innerHTML = '<option value="">-- Pilih Divisi (Opsional) --</option>';
                    if (data.length === 0) {
                        select.innerHTML = '<option value="">UKM ini tidak memiliki divisi</option>';
                        select.disabled = true;
                    } else {
                        select.disabled = false;
                        data.forEach(divisi => {
                            const option = document.createElement('option');
                            option.value = divisi.id;
                            option.textContent = divisi.nama_divisi;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading divisi:', error);
                    select.innerHTML = '<option value="">Gagal memuat divisi</option>';
                    select.disabled = true;
                });
        }

        function openDaftarModal(ukmId, ukmNama) {
            const modalElement = document.getElementById('daftarModal');
            if (!modalElement || !daftarModal) {
                console.error('Modal not initialized!');
                return;
            }

            const ukmIdInput = document.getElementById('modal_ukm_id');
            const ukmNamaElement = document.getElementById('modal_ukm_nama');
            const alasanInput = document.getElementById('alasan_bergabung');
            const pengalamanInput = document.getElementById('pengalaman_organisasi');

            if (ukmIdInput) ukmIdInput.value = ukmId;
            if (ukmNamaElement) ukmNamaElement.textContent = ukmNama || 'UKM ini';
            if (alasanInput) alasanInput.value = '';
            if (pengalamanInput) pengalamanInput.value = '';

            modalElement.addEventListener('shown.bs.modal', function onShown() {
                loadDivisi(ukmId);
                modalElement.removeEventListener('shown.bs.modal', onShown);
            });

            daftarModal.show();
        }

        // 🟢 Auto-open modal
        <?php if ($auto_open_ukm_id): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    openDaftarModal(<?= $auto_open_ukm_id ?>, 'UKM ini');
                }, 500);
            });
        <?php endif; ?>

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>