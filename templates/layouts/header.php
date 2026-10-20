<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
use App\Services\AuthService;
$currentUser = AuthService::getCurrentUser();
$config = require __DIR__ . '/../../config.php';
$appName = $config['app']['name'] ?? 'Direct Selling Application';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
        }
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
        }
        /* Top Navigation Bar */
        .top-navbar {
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        /* Sidebar Aside Navigation */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #1e293b;
            color: #94a3b8;
            position: fixed;
            top: 60px;
            bottom: 0;
            left: 0;
            z-index: 1020;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
            padding-top: 1rem;
        }
        .sidebar .nav-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 0.75rem 1.25rem 0.25rem;
            font-weight: 700;
        }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
        }
        .sidebar .nav-link:hover {
            color: #ffffff;
            background-color: #334155;
        }
        .sidebar .nav-link.active {
            color: #38bdf8;
            background-color: #0f172a;
            border-left-color: #38bdf8;
            font-weight: 600;
        }
        .sidebar .nav-link i {
            font-size: 1.15rem;
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        /* Main Layout Wrapper */
        .main-wrapper {
            margin-top: 60px;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s ease-in-out;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0;
                padding: 1.25rem;
            }
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }
        /* Tree Visualizer */
        .tree ul { padding-top: 20px; position: relative; transition: all 0.5s; display: flex; justify-content: center; }
        .tree li { float: left; text-align: center; list-style-type: none; position: relative; padding: 20px 5px 0 5px; transition: all 0.5s; }
        .tree li::before, .tree li::after { content: ''; position: absolute; top: 0; right: 50%; border-top: 2px solid #cbd5e1; width: 50%; height: 20px; }
        .tree li::after { right: auto; left: 50%; border-left: 2px solid #cbd5e1; }
        .tree li:only-child::after, .tree li:only-child::before { display: none; }
        .tree li:only-child { padding-top: 0; }
        .tree li:first-child::before, .tree li:last-child::after { border: 0 none; }
        .tree li:last-child::before { border-right: 2px solid #cbd5e1; border-radius: 0 5px 0 0; }
        .tree li:first-child::after { border-radius: 5px 0 0 0; }
        .tree ul ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 2px solid #cbd5e1; width: 0; height: 20px; }
        .tree li a { border: 1px solid #cbd5e1; padding: 10px 18px; text-decoration: none; color: #1e293b; font-size: 13px; display: inline-block; border-radius: 8px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.3s; }
        .tree li a:hover { background: #f1f5f9; border-color: #0284c7; }
    </style>
</head>
<body>

<!-- Top Bar -->
<header class="top-navbar">
    <div class="d-flex align-items-center">
        <?php if ($currentUser): ?>
            <button class="btn btn-light d-lg-none me-2" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
        <?php endif; ?>
        <a class="text-decoration-none fw-bold fs-5 text-primary" href="/index.php">
            <i class="bi bi-diagram-3-fill me-2"></i><?= htmlspecialchars($appName) ?>
        </a>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if ($currentUser): ?>
            <div class="dropdown">
                <a class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" href="#" id="userDropdown" data-bs-toggle="dropdown">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:36px; height:36px; font-weight:600;">
                        <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                    </div>
                    <div class="d-none d-md-block text-start me-1">
                        <span class="d-block fw-bold small text-dark lh-1"><?= htmlspecialchars($currentUser['username']) ?></span>
                        <span class="badge bg-<?= $currentUser['role'] === 'super_admin' ? 'danger' : ($currentUser['role'] === 'admin' ? 'warning' : 'info') ?> mt-1" style="font-size: 10px;">
                            <?= strtoupper(str_replace('_', ' ', $currentUser['role'])) ?>
                        </span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="/user/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>User Portal</a></li>
                    <?php if (in_array($currentUser['role'], ['admin', 'super_admin'])): ?>
                        <li><a class="dropdown-item text-warning fw-bold" href="/admin/dashboard.php"><i class="bi bi-shield-lock me-2"></i>Admin Portal</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="/login.php" class="btn btn-outline-primary btn-sm px-3">Login</a>
            <a href="/register.php" class="btn btn-primary btn-sm px-3">Register</a>
        <?php endif; ?>
    </div>
</header>

<?php if ($currentUser): ?>
    <!-- Sidebar Aside Navigation -->
    <aside class="sidebar" id="appSidebar">
        <!-- Customer / User Portal Section -->
        <div class="nav-header">Customer Section</div>
        <a href="/user/dashboard.php" class="nav-link <?= strpos($currentScript, '/user/dashboard.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a href="/user/genealogy.php" class="nav-link <?= (strpos($currentScript, '/user/genealogy.php') !== false || strpos($currentScript, '/user/matrix.php') !== false) ? 'active' : '' ?>">
            <i class="bi bi-diagram-3"></i>Genealogy
        </a>
        <a href="/user/referrals.php" class="nav-link <?= strpos($currentScript, '/user/referrals.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-people"></i>My Referrals
        </a>
        <a href="/user/downlines.php" class="nav-link <?= strpos($currentScript, '/user/downlines.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-node-plus"></i>My Downlines
        </a>
        <a href="/user/wallet.php" class="nav-link <?= strpos($currentScript, '/user/wallet.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i>My Wallet
        </a>
        <a href="/user/reports.php" class="nav-link <?= strpos($currentScript, '/user/reports.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph"></i>Reports
        </a>
        <a href="/user/epins.php" class="nav-link <?= strpos($currentScript, '/user/epins.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-key"></i>ePIN Keys
        </a>

        <?php if (in_array($currentUser['role'], ['admin', 'super_admin'])): ?>
            <!-- Admin Control Section -->
            <div class="nav-header text-warning mt-3"><i class="bi bi-shield-lock me-1"></i>Admin Control</div>
            <a href="/admin/dashboard.php" class="nav-link <?= strpos($currentScript, '/admin/dashboard.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i>Admin Dashboard
            </a>
            <a href="/admin/packages.php" class="nav-link <?= strpos($currentScript, '/admin/packages.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>Packages
            </a>
            <a href="/admin/epins.php" class="nav-link <?= strpos($currentScript, '/admin/epins.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated"></i>ePIN Manager
            </a>
            <a href="/admin/users.php" class="nav-link <?= strpos($currentScript, '/admin/users.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>User Manager
            </a>
            <a href="/admin/payouts.php" class="nav-link <?= strpos($currentScript, '/admin/payouts.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>Payout Requests
            </a>
            <a href="/admin/settings.php" class="nav-link <?= strpos($currentScript, '/admin/settings.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>Dynamic Settings
            </a>
        <?php endif; ?>
    </aside>
<?php endif; ?>

<main class="<?= $currentUser ? 'main-wrapper' : 'container py-4 mt-5' ?>">
