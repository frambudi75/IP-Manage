<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$page_title = 'Change Password';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            $message = "Password updated successfully!";
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 500px; margin: 0 auto;">
    <h1 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Change Password</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Update your security credentials.</p>

    <?php if ($message): ?>
        <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 8px; margin-bottom: 1.5rem;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); border-radius: 8px; margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.5rem;">
        <form method="POST">
            <div class="input-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="input-control" required>
            </div>
            
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="input-control" required>
            </div>

            <div class="input-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="input-control" required>
            </div>

            <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                <a href="index" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
