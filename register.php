<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Services\AuthService;
use App\Services\CompensationEngine;
use App\Core\Database;

$error = '';
$success = '';

$db = Database::getConnection();
// Fetch sponsors list
$sponsors = $db->query("SELECT id, username, full_name FROM users ORDER BY id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthService();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $sponsorId = !empty($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : null;
    $epinCode = trim($_POST['epin_code'] ?? '');

    if (strlen($username) < 3 || strlen($password) < 6) {
        $error = 'Username must be at least 3 chars and password at least 6 chars.';
    } else {
        $result = $auth->registerUser($username, $email, $fullName, $password, $sponsorId);
        if ($result['success']) {
            $newUserId = $result['user_id'];
            if (!empty($epinCode)) {
                $engine = new CompensationEngine();
                $actRes = $engine->activateUserWithEpin($newUserId, $epinCode);
                if ($actRes['success']) {
                    $success = 'Registration and ePIN activation successful! You can now login.';
                } else {
                    $success = 'Registration successful! However, ePIN activation failed: ' . $actRes['message'] . '. You can activate later from your dashboard.';
                }
            } else {
                $success = 'Registration successful! Please login and activate your account with an ePIN.';
            }
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = "Register - Direct Selling App";
require_once __DIR__ . '/templates/layouts/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>New Account Registration</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?><br>
                        <a href="/login.php" class="btn btn-sm btn-success mt-2">Proceed to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="register.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required placeholder="Choose username">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required placeholder="John Doe">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="At least 6 characters">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sponsor / Referrer</label>
                            <select name="sponsor_id" class="form-select">
                                <option value="">-- Direct System Sponsor --</option>
                                <?php foreach ($sponsors as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['username']) ?> (<?= htmlspecialchars($s['full_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Activation ePIN Code (Optional)</label>
                            <input type="text" name="epin_code" class="form-control" placeholder="e.g. EPIN-1A2B3C4D-1234">
                            <small class="text-muted">Enter an unused ePIN code to activate your account and join matrix immediately.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Register Now</button>
                    </form>
                <?php endif; ?>

                <hr class="my-4">
                <div class="text-center">
                    <p class="mb-0">Already have an account? <a href="/login.php">Login Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
