<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
use App\Services\AuthService;
$currentUser = AuthService::getCurrentUser();
$config = require __DIR__ . '/../../config.php';
$appName = $config['app']['name'] ?? 'Direct Selling Application';
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
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .content-wrapper {
            flex: 1;
        }
        .navbar-brand {
            font-weight: 700;
            color: #0d6efd !important;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        /* Matrix Tree Styling */
        .tree ul {
            padding-top: 20px; position: relative;
            transition: all 0.5s;
            display: flex;
            justify-content: center;
        }
        .tree li {
            float: left; text-align: center;
            list-style-type: none;
            position: relative;
            padding: 20px 5px 0 5px;
            transition: all 0.5s;
        }
        .tree li::before, .tree li::after {
            content: ''; position: absolute; top: 0; right: 50%;
            border-top: 2px solid #ccc;
            width: 50%; height: 20px;
        }
        .tree li::after {
            right: auto; left: 50%;
            border-left: 2px solid #ccc;
        }
        .tree li:only-child::after, .tree li:only-child::before {
            display: none;
        }
        .tree li:only-child { padding-top: 0;}
        .tree li:first-child::before, .tree li:last-child::after {
            border: 0 none;
        }
        .tree li:last-child::before {
            border-right: 2px solid #ccc;
            border-radius: 0 5px 0 0;
        }
        .tree li:first-child::after {
            border-radius: 5px 0 0 0;
        }
        .tree ul ul::before {
            content: ''; position: absolute; top: 0; left: 50%;
            border-left: 2px solid #ccc;
            width: 0; height: 20px;
        }
        .tree li a {
            border: 1px solid #ccc;
            padding: 8px 15px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
            display: inline-block;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .tree li a:hover {
            background: #e9ecef;
            border-color: #999;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand text-white fw-bold" href="/index.php">
            <i class="bi bi-diagram-3-fill text-primary me-2"></i><?= htmlspecialchars($appName) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php"><i class="bi bi-house me-1"></i>Home</a>
                </li>
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>User Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/matrix.php"><i class="bi bi-diagram-3 me-1"></i>Matrix Tree</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/referrals.php"><i class="bi bi-people me-1"></i>My Team</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/epins.php"><i class="bi bi-key me-1"></i>ePINs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/wallet.php"><i class="bi bi-wallet2 me-1"></i>Wallet & Payouts</a>
                    </li>
                    <?php if (in_array($currentUser['role'], ['admin', 'super_admin'])): ?>
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-shield-lock me-1"></i>Admin Control
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="bi bi-sliders me-2"></i>Admin Dashboard</a></li>
                                <li><a class="dropdown-item" href="/admin/packages.php"><i class="bi bi-box-seam me-2"></i>Packages</a></li>
                                <li><a class="dropdown-item" href="/admin/epins.php"><i class="bi bi-ticket-perforated me-2"></i>ePIN Manager</a></li>
                                <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people-fill me-2"></i>User Manager</a></li>
                                <li><a class="dropdown-item" href="/admin/payouts.php"><i class="bi bi-cash-stack me-2"></i>Payout Requests</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-primary fw-bold" href="/admin/settings.php"><i class="bi bi-gear-fill me-2"></i>Dynamic Settings Engine</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="userMenu" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($currentUser['username']) ?>
                            <span class="badge bg-<?= $currentUser['role'] === 'super_admin' ? 'danger' : ($currentUser['role'] === 'admin' ? 'warning' : 'info') ?> ms-1">
                                <?= strtoupper(str_replace('_', ' ', $currentUser['role'])) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm ms-2 px-3 mt-1 mt-lg-0" href="/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="content-wrapper py-4">
    <div class="container">
