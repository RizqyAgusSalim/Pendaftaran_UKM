<?php
// admin/kelola_pendaftaran.php

// Pastikan path ke file config benar
require_once '../config/database.php';
require_once '../config/functions.php';

// ====================================================
// I. OTORISASI AWAL & PENDEFINISIAN SCOPE ADMIN
// ====================================================

// Cek status login
if (!isLoggedIn()) {
    // Redirect ke halaman login jika belum login
    redirect('../auth/login.php'); 
}

// Cek hak akses admin
if (!isAdmin()) {
    // Redirect atau tampilkan pesan akses ditolak jika bukan admin
    showAlert("Akses ditolak. Anda tidak memiliki izin untuk halaman ini.", "danger");
    redirect('../index.php'); 
}

// Koneksi DB
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    showAlert("Koneksi database gagal.", "danger");
    // Anda bisa redirect ke halaman error atau dashboard dengan alert
    redirect('dashboard.php'); 
}

// Tentukan apakah user adalah Admin UKM (bukan Super Admin)
// Cek sesi 'ukm_id_dikelola'. Nilai null/tidak ada berarti Super Admin (bisa melihat semua).
$ukm_id_admin = $_SESSION['ukm_id_dikelola'] ?? null;
$is_ukm_admin = $ukm_id_admin !== null;

// Tentukan klausa WHERE dasar untuk memfilter kueri SELECT
$where_pendaftaran = $is_ukm_admin ? "WHERE p.ukm_id = :ukm_id_admin" : "";
$where_pendaftaran_param = $is_ukm_admin ? [':ukm_id_admin' => $ukm_id_admin] : [];


// ====================================================
// II. PROSES TERIMA / TOLAK PENDAFTARAN (GARIS PERTAHANAN OTORISASI)
// ====================================================

if (isset($_GET['action']) && isset($_GET['id'])) {
    $pendaftaran_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    $action = $_GET['action'];
    $status_baru = null;
    $catatan = '';
    $message = '';

    // A. Ambil UKM ID dari pendaftaran yang akan diubah
    $query_check_ukm = "SELECT ukm_id FROM pendaftaran WHERE id = :id";
    $stmt_check = $db->prepare($query_check_ukm);
    $stmt_check->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $pendaftaran_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$pendaftaran_data) {
        showAlert("Pendaftaran tidak ditemukan.", "danger");
        redirect('kelola_pendaftaran.php');
    }

    $target_ukm_id = $pendaftaran_data['ukm_id'];

    // B. VALIDASI OTORISASI SERVER
    // Admin UKM HANYA boleh memproses pendaftaran yang ukm_id-nya sesuai dengan ukm_id_dikelola.
    if ($is_ukm_admin && $target_ukm_id != $ukm_id_admin) {
        // Blokir akses jika Admin UKM mencoba mengubah data UKM lain!
        showAlert("Akses ditolak! Anda tidak berwenang mengelola pendaftaran UKM ini.", "danger");
        redirect('kelola_pendaftaran.php');
    }
    
    // C. Tentukan Status dan Catatan (Jika lolos validasi)
    if ($action == 'approve') {
        $status_baru = 'diterima';
        $catatan = 'Diterima oleh Admin melalui aksi cepat.';
        $message = "Pendaftaran berhasil **DITERIMA**!";
    } elseif ($action == 'reject') {
        $status_baru = 'ditolak';
        $catatan = 'Ditolak oleh Admin melalui aksi cepat.';
        $message = "Pendaftaran berhasil **DITOLAK**!";
    }

    // D. Eksekusi Update Status
    if ($status_baru) {
        try {
            $query_update = "UPDATE pendaftaran 
                            SET status = :status, catatan_admin = :catatan 
                            WHERE id = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':status', $status_baru);
            $stmt_update->bindParam(':catatan', $catatan);
            $stmt_update->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
            $stmt_update->execute();

            showAlert($message, "success");

        } catch (PDOException $e) {
            showAlert("Gagal memproses pendaftaran: " . $e->getMessage(), "danger");
        }
    }
    
    redirect("kelola_pendaftaran.php");
}


// ====================================================
// III. AMBIL DATA PENDAFTARAN (DIBATASI OLEH SCOPE)
// ====================================================
$query = "
    SELECT
        p.id,
        p.tanggal_daftar,
        p.alasan_bergabung,
        p.pengalaman_organisasi,
        p.status,
        p.catatan_admin,
        m.nama AS nama_mahasiswa,
        m.nim,
        u.nama_ukm
    FROM pendaftaran p
    LEFT JOIN mahasiswa m ON p.mahasiswa_id = m.id
    LEFT JOIN ukm u ON p.ukm_id = u.id
    {$where_pendaftaran}
    ORDER BY p.tanggal_daftar DESC
";

$stmt = $db->prepare($query);
// Bind parameter jika ini Admin UKM
if ($is_ukm_admin) {
    $stmt->bindParam(':ukm_id_admin', $ukm_id_admin, PDO::PARAM_INT);
}
$stmt->execute();
$pendaftaran = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fungsi helper untuk tampilan badge (Jika belum ada di functions.php)
if (!function_exists('getStatusBadge')) {
    function getStatusBadge(string $status): string
    {
        $status = strtolower($status);
        $text = ucfirst($status);
        $class = match($status){
            'pending' => 'badge-pending text-dark',
            'diterima' => 'badge-diterima',
            'ditolak' => 'badge-ditolak',
            default => 'bg-secondary'
        };
        return "<span class=\"badge {$class}\">{$text}</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pendaftaran UKM - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS DISAMAKAN DENGAN DASHBOARD.PHP */
        body { margin: 0; }
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
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        /* Badge status (kelas dari dashboard.php) */
        .badge-pending { background: #ffc107 !important; color: #212529 !important; }
        .badge-diterima { background: #28a745 !important; color: #fff !important; }
        .badge-ditolak { background: #dc3545 !important; color: #fff !important; }
        .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="p-3 text-center border-bottom border-secondary">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-university"></i> Admin Panel
                        </h5>
                        <small class="text-white-50">Politeknik Negeri Lampung</small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="kelola_pendaftaran.php">
                            <i class="fas fa-clipboard-list me-2"></i> Pendaftaran
                        </a>
                        <div class="dropdown-divider bg-secondary"></div>
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
                            <h2><i class="fas fa-clipboard-list me-2 text-primary"></i> Kelola Pendaftaran UKM</h2>
                            <p class="text-muted mb-0">Tinjau dan proses pendaftaran anggota baru.</p>
                        </div>
                    </div>

                    <?php if ($is_ukm_admin): ?>
                        <div class="alert alert-info">
                            Anda adalah Admin UKM. Data yang ditampilkan **hanya pendaftaran untuk UKM yang Anda kelola (ID: <?= $ukm_id_admin ?>)**.
                        </div>
                    <?php endif; ?>

                    <?php displayAlert(); ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-table text-secondary"></i> Daftar Pendaftaran UKM
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nama Mahasiswa</th>
                                                <th>NIM</th>
                                                <th>UKM Tujuan</th>
                                                <th>Tanggal Daftar</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($pendaftaran)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                        Belum ada data pendaftaran yang masuk.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($pendaftaran as $i => $p): ?>
                                                    <tr>
                                                        <td><?= $i+1 ?></td>
                                                        <td><?= htmlspecialchars($p['nama_mahasiswa']) ?></td>
                                                        <td><?= htmlspecialchars($p['nim']) ?></td>
                                                        <td><?= htmlspecialchars($p['nama_ukm']) ?></td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?= formatTanggal($p['tanggal_daftar']) ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?= getStatusBadge($p['status']) ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary btn-detail" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#detailModal"
                                                                        data-nama="<?= htmlspecialchars($p['nama_mahasiswa']) ?>"
                                                                        data-nim="<?= htmlspecialchars($p['nim']) ?>"
                                                                        data-ukm="<?= htmlspecialchars($p['nama_ukm']) ?>"
                                                                        data-alasan="<?= htmlspecialchars($p['alasan_bergabung']) ?>"
                                                                        data-pengalaman="<?= htmlspecialchars($p['pengalaman_organisasi']) ?>"
                                                                        data-catatan="<?= htmlspecialchars($p['catatan_admin'] ?? 'N/A') ?>"
                                                                        data-status="<?= $p['status'] ?>"
                                                                        data-id="<?= $p['id'] ?>">
                                                                    <i class="fas fa-eye"></i> Detail
                                                                </button>
                                                                <?php if ($p['status'] == 'pending'): ?>
                                                                    <a href="kelola_pendaftaran.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-outline-success btn-konfirmasi" data-action="menerima" title="Terima">
                                                                        <i class="fas fa-check"></i>
                                                                    </a>
                                                                    <a href="kelola_pendaftaran.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-konfirmasi" data-action="menolak" title="Tolak">
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

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detail Pendaftaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            <div class="modal-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th style="width: 30%;">Nama Mahasiswa</th>
                        <td id="modal_nama"></td>
                    </tr>
                    <tr>
                        <th>NIM</th>
                        <td id="modal_nim"></td>
                    </tr>
                    <tr>
                        <th>UKM Tujuan</th>
                        <td id="modal_ukm"></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td id="modal_status"></td>
                    </tr>
                    <tr>
                        <th>Alasan Bergabung</th>
                        <td id="modal_alasan" class="text-wrap"></td>
                    </tr>
                    <tr>
                        <th>Pengalaman Organisasi</th>
                        <td id="modal_pengalaman" class="text-wrap"></td>
                    </tr>
                    <tr>
                        <th>Catatan Admin</th>
                        <td id="modal_catatan" class="text-wrap"></td>
                    </tr>
                </table>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end" id="modal_aksi_cepat">
                </div>
            </div>
        </div>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS untuk mengisi dan menampilkan Modal Detail
        const detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            // Ambil data dari data-attribute tombol
            const nama = button.getAttribute('data-nama');
            const nim = button.getAttribute('data-nim');
            const ukm = button.getAttribute('data-ukm');
            const alasan = button.getAttribute('data-alasan');
            const pengalaman = button.getAttribute('data-pengalaman');
            const catatan = button.getAttribute('data-catatan');
            const status = button.getAttribute('data-status');
            const id = button.getAttribute('data-id');

            // Fungsi untuk membuat Badge HTML
            function getBadgeHtml(status) {
                let statusText = status.charAt(0).toUpperCase() + status.slice(1);
                let badgeClass = '';
                if (status === 'pending') {
                    badgeClass = 'badge-pending text-dark';
                } else if (status === 'diterima') {
                    badgeClass = 'badge-diterima';
                } else if (status === 'ditolak') {
                    badgeClass = 'badge-ditolak';
                }
                return `<span class="badge ${badgeClass}">${statusText}</span>`;
            }

            // Isi konten modal
            document.getElementById('modal_nama').textContent = nama;
            document.getElementById('modal_nim').textContent = nim;
            document.getElementById('modal_ukm').textContent = ukm;
            document.getElementById('modal_status').innerHTML = getBadgeHtml(status);
            document.getElementById('modal_alasan').textContent = alasan;
            document.getElementById('modal_pengalaman').textContent = pengalaman;
            document.getElementById('modal_catatan').textContent = catatan;
            
            // Tampilkan Aksi Cepat (Tombol Terima/Tolak) jika status masih pending
            const modalAksiCepat = document.getElementById('modal_aksi_cepat');
            modalAksiCepat.innerHTML = '';
            // Logika ini hanya menampilkan tombol jika status pending
            if (status === 'pending') { 
                modalAksiCepat.innerHTML = `
                    <a href="kelola_pendaftaran.php?action=approve&id=${id}" class="btn btn-success me-2 btn-konfirmasi" data-action="menerima">
                        <i class="fas fa-check"></i> Terima Pendaftaran
                    </a>
                    <a href="kelola_pendaftaran.php?action=reject&id=${id}" class="btn btn-danger btn-konfirmasi" data-action="menolak">
                        <i class="fas fa-times"></i> Tolak Pendaftaran
                    </a>
                `;
            }
        });

        // Confirmation for action buttons
        document.querySelectorAll('.btn-konfirmasi').forEach(function(link) {
            link.addEventListener('click', function(e) {
                const action = this.getAttribute('data-action');
                if (!confirm(`Apakah Anda yakin ingin ${action} pendaftaran ini? Tindakan ini tidak dapat dibatalkan.`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>