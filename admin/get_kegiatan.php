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
    $query = "SELECT k.* FROM kegiatan_ukm k
              JOIN ukm u ON k.ukm_id = u.id
              WHERE k.ukm_id = :ukm_id AND u.admin_id = :admin_id
              ORDER BY k.tanggal_mulai DESC";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ukm_id', $ukm_id);
    $stmt->bindParam(':admin_id', $current_admin_id);
    $stmt->execute();
    $kegiatan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($kegiatan_list)) {
        echo '<div class="alert alert-info text-center">Belum ada kegiatan yang ditambahkan untuk UKM ini.</div>';
    } else {
        echo '<table class="table table-striped table-sm">';
        echo '<thead><tr><th>Nama Kegiatan</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>';
        echo '<tbody>';
        foreach ($kegiatan_list as $kegiatan) {
            $status_class = [
                'draft' => 'secondary',
                'published' => 'success',
                'completed' => 'primary'
            ][$kegiatan['status']] ?? 'light';
            
            $periode = formatTanggal($kegiatan['tanggal_mulai']);
            if (!empty($kegiatan['tanggal_selesai']) && $kegiatan['tanggal_mulai'] != $kegiatan['tanggal_selesai']) {
                $periode .= ' s/d ' . formatTanggal($kegiatan['tanggal_selesai']);
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($kegiatan['nama_kegiatan']) . '</td>';
            echo '<td>' . $periode . '</td>';
            echo '<td><span class="badge bg-' . $status_class . '">' . htmlspecialchars(ucfirst($kegiatan['status'])) . '</span></td>';
            echo '<td>';
            echo '<a href="kelola_ukm.php?hapus_kegiatan=' . $kegiatan['id'] . '" class="btn btn-sm btn-danger me-1" onclick="return confirm(\'Yakin hapus kegiatan ini?\')">';
            echo '<i class="fas fa-trash"></i> Hapus</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
} else {
    echo '<div class="alert alert-warning text-center">UKM ID tidak valid.</div>';
}
?>