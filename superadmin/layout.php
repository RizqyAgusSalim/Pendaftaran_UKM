<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Super Admin - UKM Polinela' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f5f6fa;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: #2c3e50;
            color: white;
            min-height: 100vh;
            padding-top: 20px;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar h4 {
            padding: 0 12px;
        }
        
        .sidebar a {
            padding: 12px 20px;
            display: block;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background: #1abc9c;
            border-radius: 5px;
            margin: 0 10px;
            padding-left: 15px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* Card Styles */
        .welcome-box {
            background: linear-gradient(135deg, #2980b9, #6dd5fa);
            padding: 25px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-bottom: 24px;
        }
        
        .card-stats {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            cursor: default;
            border: none;
        }
        
        .card-stats:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .section-title {
            border-left: 4px solid #2980b9;
            padding-left: 12px;
            margin: 28px 0 16px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .badge-status {
            font-size: 0.82em;
            padding: 0.3em 0.6em;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 70px 15px 20px;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            /* Overlay when sidebar is open */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .welcome-box h3 {
                font-size: 1.3rem;
            }
            
            .card-stats h5 {
                font-size: 0.9rem;
            }
            
            .card-stats h2 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 70px 10px 15px;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
    <?= $extra_css ?? '' ?>
</head>
<body>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars" id="toggleIcon"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="text-center mb-4">
            <i class="fas fa-crown"></i> Super Admin
        </h4>

        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="kelola_adminUKM.php" class="<?= basename($_SERVER['PHP_SELF']) === 'kelola_adminUKM.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> Kelola Admin & UKM
        </a>
        <a href="kelola_mahasiswa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'kelola_mahasiswa.php' ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i> Kelola Mahasiswa
        </a>
        <a href="../admin/dashboard.php">
            <i class="fas fa-user-shield"></i> Mode Admin
        </a>
        <a href="../auth/logout.php" class="text-danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?= $content ?? '' ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const toggleIcon = document.getElementById('toggleIcon');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Ubah ikon
            if (sidebar.classList.contains('show')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-bars');
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking on a link (mobile)
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
    </script>
    <?= $extra_js ?? '' ?>
</body>
</html>