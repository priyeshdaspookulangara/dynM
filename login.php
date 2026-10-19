<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Services\AuthService;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthService();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $auth->login($username, $password);
    if ($result['success']) {
        $user = $result['user'];
        if (in_array($user['role'], ['admin', 'super_admin'])) {
            header('Location: /admin/dashboard.php');
        } else {
            header('Location: /user/dashboard.php');
        }
        exit;
    } else {
        $error = $result['message'];
    }
}

$pageTitle = "Login - Direct Selling App";
require_once __DIR__ . '/templates/layouts/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" name="username" class="form-control" required autofocus placeholder="Enter username or email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Enter password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Login</button>
                </form>

                <hr class="my-4">
                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="/register.php">Register Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
