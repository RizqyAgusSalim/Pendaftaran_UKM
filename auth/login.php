<?php
// auth/login.php
require_once '../config/database.php';
require_once '../config/functions.php';

if (isLoggedIn()) {
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
                $_SESSION['user_role'] = $admin['role'];

                if (!empty($admin['ukm_id'])) {
                    $_SESSION['ukm_id'] = (int)$admin['ukm_id'];
                }

                if ($admin['role'] === 'superadmin') {
                    redirect('../superadmin/dashboard.php');
                    exit;
                }
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* KIRI: Foto Background */
        .login-left {
            width: 50%;
            position: relative;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            background: url('../assets/polinela.jpg') no-repeat center center;
            background-size: cover;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6); /* overlay gelap agar teks terbaca */
            z-index: 1;
        }

        .login-left .content {
            position: relative;
            z-index: 2;
            max-width: 80%;
        }

        .login-left h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }

        .login-left p {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffcc00;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
        }

        .logo-placeholder {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .logo-placeholder i {
            font-size: 2rem;
            color: #1e3c72;
        }

        /* KANAN: Form Login — Seperti versi awal Anda */
        .login-right {
            width: 50%;
            padding: 3rem;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-right h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
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
            color: white;
        }

        .btn-login:hover {
            background: #2c3e50;
        }

        .btn-register {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 25px;
        }

        .btn-register:hover {
            background: #3498db;
            color: white;
        }

        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
            }
            .login-left,
            .login-right {
                width: 100%;
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- KIRI: Foto Background -->
        <div class="login-left">
            <div class="content">
                <div class="logo-placeholder">
                    <i class="fas fa-university"></i>
                </div>
                <h1>Selamat Datang</h1>
                <p>di Sipadu UKM</p>
            </div>
        </div>

        <!-- KANAN: Form Login (seperti versi awal Anda) -->
        <div class="login-right">
            <h2>SIPADU UKM</h2>

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
                <a href="register.php" class="btn btn-register">
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
                    <strong>SISTEM INFORMASI TERPADU UKM</strong> <br>
                    POLITEKNIK NEGERI LAMPUNG
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>