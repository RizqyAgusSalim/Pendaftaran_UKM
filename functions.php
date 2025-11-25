<?
session_start();

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function uploadFile($file, $target_dir = "uploads/") {
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    if ($file["size"] > 5000000) { // 5MB max
        return false;
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $new_filename;
    }
    
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

function isMahasiswa() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'mahasiswa';
}

function isPengurusUKM($ukm_id) {
    if (!isMahasiswa()) return false;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) FROM pengurus_ukm WHERE ukm_id = ? AND mahasiswa_id = ? AND status = 'aktif'";
    $stmt = $db->prepare($query);
    $stmt->execute([$ukm_id, $_SESSION['user_id']]);
    
    return $stmt->fetchColumn() > 0;
}

function formatTanggal($tanggal) {
    $bulan = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );
    
    $split = explode('-', date('Y-n-j', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = array('message' => $message, 'type' => $type);
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">
                ' . $alert['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['alert']);
    }
}
?>