<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn() || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo "Akses ditolak.";
    exit;
}

$current_admin_id = $_SESSION['user_id'] ?? 0;
$ukm_id = isset($_GET['ukm_id']) ? intval($_GET['ukm_id']) : 0;

$database = new Database();
$db = $database->getConnection();

if ($ukm_id > 0) {
    $query = "SELECT f.*, k.nama_kegiatan FROM foto_kegiatan f
              JOIN kegiatan_ukm k ON f.kegiatan_id = k.id
              JOIN ukm u ON k.ukm_id = u.id
              WHERE u.id = :ukm_id AND u.admin_id = :admin_id
              ORDER BY f.created_at DESC";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ukm_id', $ukm_id);
    $stmt->bindParam(':admin_id', $current_admin_id);
    $stmt->execute();
    $foto_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($foto_list)) {
        echo '<div class="col-12"><div class="alert alert-info text-center">Belum ada foto kegiatan diunggah untuk UKM ini.</div></div>';
    } else {
        foreach ($foto_list as $foto) {
            $image_path = '../uploads/kegiatan/' . htmlspecialchars($foto['file_name']);
            $keterangan = htmlspecialchars($foto['keterangan'] ?? 'Tanpa Keterangan');
            $kegiatan_nama = htmlspecialchars($foto['nama_kegiatan']);

            echo '<div class="col-md-4 col-sm-6 col-12">';
            echo '  <div class="card h-100">';
            echo '      <img src="' . $image_path . '" class="card-img-top" style="height: 200px; object-fit: cover;" alt="' . $keterangan . '">';
            echo '      <div class="card-body p-2">';
            echo '          <p class="card-text small mb-1">' . $keterangan . '</p>';
            echo '          <p class="text-muted small mb-2">Kegiatan: <strong>' . $kegiatan_nama . '</strong></p>';
            echo '          <a href="kelola_ukm.php?hapus_foto=' . $foto['id'] . '" class="btn btn-sm btn-danger w-100" onclick="return confirm(\'Yakin hapus foto ini?\')">';
            echo '              <i class="fas fa-trash"></i> Hapus';
            echo '          </a>';
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
        }
    }
} else {
    echo '<div class="col-12"><div class="alert alert-warning text-center">UKM ID tidak valid.</div></div>';
}
?>