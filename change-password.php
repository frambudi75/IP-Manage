<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = $_POST['email'];
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        $message = "Profile updated successfully!";
    }

    if (isset($_POST['update_password'])) {
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
}

// Fetch current user data
$stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

$page_title = 'Account Settings';
include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto; width: 100%;">
    <h1 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Account Settings</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Manage your profile and security credentials.</p>

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

    <div class="card" style="padding: 2rem; margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem;">Profile Information</h3>
        <form method="POST">
            <input type="hidden" name="update_profile" value="1">
            <div class="input-group">
                <label>Username</label>
                <input type="text" class="input-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled style="background: var(--surface-light); cursor: not-allowed;">
            </div>
            
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" class="input-control" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required placeholder="user@example.com">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">This email will be used for automated backups.</p>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Update Profile</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem;">Security / Change Password</h3>
        <form method="POST">
            <input type="hidden" name="update_password" value="1">
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

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <a href="index" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
