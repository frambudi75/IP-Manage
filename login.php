<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IPManager Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-wrapper">
    <div class="auth-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="background: var(--primary); width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; margin-bottom: 1rem;">
                <i data-lucide="network" style="color: white; width: 30px; height: 30px;"></i>
            </div>
            <h2 style="font-size: 1.5rem; color: white;">Welcome Back</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Login to manage your network</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" class="input-control" placeholder="e.g. admin" required autocomplete="username">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" class="input-control" placeholder="••••••••" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.875rem; font-weight: 600;">
                <i data-lucide="log-in"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1.5rem; text-align: center;">
            <p style="color: var(--text-muted); font-size: 0.75rem;">
                Default Credentials: <strong>admin</strong> / <strong>admin123</strong>
            </p>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
