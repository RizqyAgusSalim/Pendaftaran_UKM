<?php
// auth/login.php
require_once '../config/database.php';
require_once '../config/functions.php';

if (isLoggedIn()) {

    // Jika sudah login, arahkan sesuai role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        redirect('../mahasiswa/dashboard.php');
    }

    if ($_SESSION['user_type'] === 'admin') {

        if ($_SESSION['user_role'] === 'superadmin') {
            redirect('../superadmin/dashboard.php');
        }

        redirect('../admin/dashboard.php');
    }
}

$error = '';

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $database = new Database();
        $db = $database->getConnection();

        if ($db === null) {
            $error = 'Tidak dapat terhubung ke database';
        } else {

            // ---------------------------
            // CEK ADMIN & SUPERADMIN
            // ---------------------------
            $query = "SELECT * FROM admin WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['nama'] = $admin['nama'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['user_role'] = $admin['role']; // admin / superadmin

                // Super Admin
                if ($admin['role'] === 'superadmin') {
                    redirect('../superadmin/dashboard.php');
                }

                // Admin biasa
                redirect('../admin/dashboard.php');
                exit;
            }

            // ---------------------------
            // CEK MAHASISWA
            // ---------------------------
            $query = "SELECT * FROM mahasiswa WHERE nim = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($mahasiswa && password_verify($password, $mahasiswa['password'])) {

                $_SESSION['user_id'] = $mahasiswa['id'];
                $_SESSION['nim'] = $mahasiswa['nim'];
                $_SESSION['nama'] = $mahasiswa['nama'];
                $_SESSION['user_type'] = 'mahasiswa';

                redirect('../mahasiswa/dashboard.php');
                exit;
            }

            $error = 'Username atau password salah';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-floating input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-login {
            background: #3498db;
            border: none;
            border-radius: 25px;
            padding: 12px 0;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-university fa-3x mb-3"></i>
                        <h3>Login Sistem UKM</h3>
                        <p class="mb-0">Politeknik Negeri Lampung</p>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username/NIM" required>
                                <label for="username">
                                    <i class="fas fa-user"></i> Username / NIM
                                </label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-2">Belum punya akun mahasiswa?</p>
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus"></i> Daftar Sekarang
                            </a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                            </a>
                        </div>
                        
                        <hr class="my-4">
                        <div class="text-center">
                            <small class="text-muted">
                                Demo Admin: username=<strong>admin</strong>, password=<strong>password</strong><br>
                                Demo Mahasiswa: daftar dulu atau gunakan NIM yang sudah terdaftar
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>